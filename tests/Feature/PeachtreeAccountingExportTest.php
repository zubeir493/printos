<?php

use App\Filament\Pages\Settings as SettingsPage;
use App\Filament\Resources\AccountingExports\AccountingExportResource;
use App\Filament\Resources\AccountingExports\Pages\ListAccountingExports;
use App\Filament\Resources\Accounts\Pages\ListAccounts;
use App\Models\Account;
use App\Models\AccountingAccountMapping;
use App\Models\AccountingExport;
use App\Models\AccountingIntegration;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\User;
use App\Services\Accounting\GenerateAccountingExport;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set('filesystems.private_disk', 'local');
    Storage::fake('local');
});

function createMappedPeachtreeJournal(
    AccountingIntegration $integration,
    string $reference = 'PAY-001',
    string $date = '2026-07-10',
    string $postedAt = '2026-07-10 16:00:00',
    bool $createMappings = true,
): JournalEntry {
    $cash = Account::factory()->create(['name' => 'Cash at bank', 'code' => fake()->unique()->numerify('10##')]);
    $receivable = Account::factory()->create(['name' => 'Accounts Receivable', 'code' => fake()->unique()->numerify('12##')]);

    if ($createMappings) {
        AccountingAccountMapping::factory()->create([
            'accounting_integration_id' => $integration->id,
            'account_id' => $cash->id,
            'external_account_id' => '1102-015',
        ]);
        AccountingAccountMapping::factory()->create([
            'accounting_integration_id' => $integration->id,
            'account_id' => $receivable->id,
            'external_account_id' => '1103-001',
        ]);
    }

    $entry = JournalEntry::factory()->create([
        'date' => $date,
        'reference' => $reference,
        'narration' => 'Customer payment',
        'total_debit' => 3377.50,
        'total_credit' => 3377.50,
        'status' => 'posted',
        'posted_at' => $postedAt,
        'voided_at' => null,
    ]);

    JournalItem::factory()->create([
        'journal_entry_id' => $entry->id,
        'account_id' => $cash->id,
        'debit' => 3377.50,
        'credit' => 0,
    ]);
    JournalItem::factory()->create([
        'journal_entry_id' => $entry->id,
        'account_id' => $receivable->id,
        'debit' => 0,
        'credit' => 3377.50,
    ]);

    return $entry;
}

function xlsxEntry(string $path, string $entry): string
{
    $zip = new ZipArchive;
    expect($zip->open($path))->toBeTrue();

    $contents = $zip->getFromName($entry);
    $zip->close();

    expect($contents)->not->toBeFalse();

    return $contents;
}

test('it creates the exact Peachtree workbook and never exports a journal twice', function (): void {
    $integration = AccountingIntegration::factory()->create([
        'provider' => AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP,
        'name' => 'Peachtree Desktop',
    ]);
    $journal = createMappedPeachtreeJournal($integration);

    $export = app(GenerateAccountingExport::class)->handle($integration, now()->setDate(2026, 7, 11));

    expect($export)
        ->not->toBeNull()
        ->status->toBe(AccountingExport::STATUS_COMPLETED)
        ->journal_count->toBe(1)
        ->row_count->toBe(2)
        ->checksum->toHaveLength(64);
    expect($export->journalEntries->pluck('id')->all())->toBe([$journal->id]);
    Storage::disk('local')->assertExists($export->file_path);

    $workbookPath = Storage::disk('local')->path($export->file_path);
    $reader = ReaderFactory::createFromFile($workbookPath);
    $reader->open($workbookPath);
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        expect($sheet->getName())->toBe('General Journal');
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    expect($rows[0])->toBe(['Date', 'Account ID', 'Reference', 'Trans Description', 'Debit Amt', 'Credit Amt']);

    if ($rows[1][0] instanceof DateTimeInterface) {
        expect($rows[1][0]->format('n/j/Y'))->toBe('7/10/2026');
    } else {
        expect($rows[1][0])->toBeInt()->toBeGreaterThan(40000);
    }

    expect($rows[1][1])->toBe('1102-015')
        ->and($rows[1][2])->toBe('JV')
        ->and($rows[1][3])->toBe('Customer payment')
        ->and($rows[1][4])->toBe(3377.5)
        ->and($rows[1][5])->toBe('')
        ->and($rows[2][1])->toBe('1103-001')
        ->and($rows[2][4])->toBe('')
        ->and($rows[2][5])->toBe(3377.5);

    expect(xlsxEntry($workbookPath, 'xl/styles.xml'))->toContain('m/d/yyyy')
        ->and(xlsxEntry($workbookPath, 'xl/worksheets/sheet1.xml'))->toMatch('/<c r="A2"[^>]*s="\d+"[^>]*>/')
        ->and(xlsxEntry($workbookPath, 'xl/worksheets/sheet1.xml'))->toMatch('/<c r="A3"[^>]*s="\d+"[^>]*>/');

    expect(app(GenerateAccountingExport::class)->handle($integration, now()->setDate(2026, 7, 12)))->toBeNull();
});

test('it uses account codes as Peachtree IDs when no override is configured', function (): void {
    $integration = AccountingIntegration::factory()->create([
        'provider' => AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP,
    ]);
    $journal = createMappedPeachtreeJournal($integration, createMappings: false);
    $accountCodes = $journal->journalItems()->with('account')->get()->pluck('account.code')->all();

    $export = app(GenerateAccountingExport::class)->handle($integration, now()->setDate(2026, 7, 11));

    $reader = ReaderFactory::createFromFile(Storage::disk('local')->path($export->file_path));
    $reader->open(Storage::disk('local')->path($export->file_path));
    $rows = [];
    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = $row->toArray();
        }
    }
    $reader->close();

    expect($rows[1][1])->toBe($accountCodes[0])
        ->and($rows[2][1])->toBe($accountCodes[1]);
});

test('it fails atomically for missing account codes and can retry after correction', function (): void {
    $integration = AccountingIntegration::factory()->create([
        'provider' => AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP,
    ]);
    $journal = createMappedPeachtreeJournal($integration, createMappings: false);
    $journal->journalItems()->with('account')->first()->account->update(['code' => '']);

    $failed = app(GenerateAccountingExport::class)->handle($integration, now()->setDate(2026, 7, 11));

    expect($failed->status)->toBe(AccountingExport::STATUS_FAILED)
        ->and($failed->error_message)->toContain('Missing Peachtree account codes or mappings')
        ->and($failed->journalEntries)->toHaveCount(0);
    Storage::disk('local')->assertMissing($failed->file_path ?? 'missing');

    AccountingAccountMapping::factory()->create([
        'accounting_integration_id' => $integration->id,
        'account_id' => $journal->journalItems()->value('account_id'),
        'external_account_id' => '1102-015',
    ]);
    $retried = app(GenerateAccountingExport::class)->handle($integration, $failed->cutoff_at, null, $failed);

    expect($retried->id)->toBe($failed->id)
        ->and($retried->status)->toBe(AccountingExport::STATUS_COMPLETED)
        ->and($retried->journalEntries)->toHaveCount(1);
});

test('late backdated journals are included in the next export', function (): void {
    $integration = AccountingIntegration::factory()->create([
        'provider' => AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP,
    ]);
    createMappedPeachtreeJournal($integration, postedAt: '2026-07-10 16:00:00');
    $first = app(GenerateAccountingExport::class)->handle($integration, now()->setDate(2026, 7, 10)->endOfDay());
    $late = createMappedPeachtreeJournal($integration, 'LATE-001', '2026-07-09', '2026-07-11 09:00:00');

    $second = app(GenerateAccountingExport::class)->handle($integration, now()->setDate(2026, 7, 11)->endOfDay());

    expect($first->journal_count)->toBe(1)
        ->and($second->journal_count)->toBe(1)
        ->and($second->journalEntries->sole()->id)->toBe($late->id);
});

test('unbalanced journals block the whole export', function (): void {
    $integration = AccountingIntegration::factory()->create([
        'provider' => AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP,
    ]);
    $journal = createMappedPeachtreeJournal($integration);
    $journal->journalItems()->where('credit', '>', 0)->update(['credit' => 3000]);

    $export = app(GenerateAccountingExport::class)->handle($integration, now()->setDate(2026, 7, 11));

    expect($export->status)->toBe(AccountingExport::STATUS_FAILED)
        ->and($export->error_message)->toContain('not balanced')
        ->and($export->journalEntries)->toHaveCount(0);
});

test('only admin and finance users can download completed exports', function (): void {
    $integration = AccountingIntegration::factory()->create([
        'provider' => AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP,
        'enabled' => true,
    ]);
    Storage::disk('local')->put('accounting-exports/peachtree.xlsx', 'workbook');
    $export = AccountingExport::factory()->create([
        'accounting_integration_id' => $integration->id,
        'file_path' => 'accounting-exports/peachtree.xlsx',
        'file_name' => 'peachtree.xlsx',
    ]);

    $finance = User::factory()->create(['role' => UserRole::Finance]);
    $sales = User::factory()->create(['role' => UserRole::Sales]);

    $response = $this->actingAs($finance)->get(route('accounting-exports.download', $export))->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('attachment')
        ->and($response->headers->get('Content-Type'))->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');

    Filament::setCurrentPanel(Filament::getPanel('finance'));
    Livewire::test(ListAccountingExports::class)
        ->assertTableActionHasUrl('download', route('accounting-exports.download', $export), $export)
        ->assertTableActionShouldOpenUrlInNewTab('download', $export);

    $this->actingAs($sales)->get(route('accounting-exports.download', $export))->assertRedirect();
});

test('the integration settings mappings and export history surfaces render', function (): void {
    $admin = User::factory()->create(['role' => UserRole::Admin]);
    $finance = User::factory()->create(['role' => UserRole::Finance]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs($admin);
    expect(AccountingExportResource::shouldRegisterNavigation())->toBeFalse();

    Livewire::test(SettingsPage::class)
        ->assertSee('Accounting Integrations')
        ->assertSee('Peachtree Desktop')
        ->assertSee('Xero')
        ->assertSee('QuickBooks')
        ->assertSee('Sage 50 Cloud')
        ->assertSee('SQL Accounting')
        ->assertSee('Daily Excel journal export')
        ->set('data.peachtree_enabled', true)
        ->set('data.xero_enabled', true)
        ->set('data.timezone', 'Africa/Addis_Ababa')
        ->call('save')
        ->assertHasNoErrors();

    expect(AccountingIntegration::peachtreeDesktop())
        ->enabled->toBeTrue()
        ->timezone->toBe('Africa/Addis_Ababa');
    expect(AccountingExportResource::shouldRegisterNavigation())->toBeTrue();
    expect(AccountingIntegration::integrationFor(AccountingIntegration::PROVIDER_XERO))
        ->enabled->toBeTrue();

    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs($finance);
    Livewire::test(ListAccounts::class)->assertOk();
    Livewire::test(ListAccountingExports::class)
        ->assertSee('Peachtree exports')
        ->assertSee('Generate export');
});

test('account override action follows active accounting integrations', function (): void {
    $finance = User::factory()->create(['role' => UserRole::Finance]);
    $account = Account::factory()->create(['code' => '4100']);
    $peachtree = AccountingIntegration::integrationFor(AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP);
    $xero = AccountingIntegration::integrationFor(AccountingIntegration::PROVIDER_XERO);

    $peachtree->update(['enabled' => false]);
    $xero->update(['enabled' => true]);

    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs($finance);

    Livewire::test(ListAccounts::class)
        ->mountTableAction('mapAccountingAccounts', $account)
        ->assertMountedActionModalSee('Xero Account Override')
        ->assertMountedActionModalDontSee('Peachtree Desktop Account Override');

    Livewire::test(ListAccounts::class)
        ->callTableAction('mapAccountingAccounts', $account, [
            "integration_{$xero->id}" => 'X-4100',
        ])
        ->assertHasNoFormErrors();

    expect($xero->mappings()->where('account_id', $account->id)->value('external_account_id'))->toBe('X-4100');

    $peachtree->update(['enabled' => true]);

    Livewire::test(ListAccounts::class)
        ->mountTableAction('mapAccountingAccounts', $account)
        ->assertMountedActionModalSee('Xero Account Override')
        ->assertMountedActionModalSee('Peachtree Desktop Account Override');
});
