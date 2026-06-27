<?php

use App\Filament\Resources\JournalEntries\Pages\CreateJournalEntry;
use App\Filament\Resources\JournalEntries\Pages\ListJournalEntries;
use App\Filament\Resources\JournalEntries\Pages\ViewJournalEntry;
use App\Models\Account;
use App\Models\JournalEntry;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('manual journal entries must balance before they are created', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $cash = Account::factory()->create(['type' => 'Asset']);
    $revenue = Account::factory()->create(['type' => 'Revenue']);

    Livewire::test(CreateJournalEntry::class)
        ->fillForm([
            'reference' => 'MAN-001',
            'date' => '2026-05-18',
            'JournalItems' => [
                [
                    'account_id' => $cash->id,
                    'debit' => 100,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenue->id,
                    'debit' => 0,
                    'credit' => 90,
                ],
            ],
        ])
        ->call('create')
        ->assertHasErrors();

    expect(JournalEntry::count())->toBe(0);
});

test('manual journal entry totals are recalculated on the server', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $cash = Account::factory()->create(['type' => 'Asset']);
    $revenue = Account::factory()->create(['type' => 'Revenue']);

    Livewire::test(CreateJournalEntry::class)
        ->fillForm([
            'reference' => 'MAN-002',
            'date' => '2026-05-18',
            'total_debit' => 999,
            'total_credit' => 1,
            'JournalItems' => [
                [
                    'account_id' => $cash->id,
                    'debit' => 100,
                    'credit' => 0,
                ],
                [
                    'account_id' => $revenue->id,
                    'debit' => 0,
                    'credit' => 100,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $journalEntry = JournalEntry::firstOrFail();

    expect((float) $journalEntry->total_debit)->toBe(100.0)
        ->and((float) $journalEntry->total_credit)->toBe(100.0)
        ->and($journalEntry->status)->toBe('posted')
        ->and($journalEntry->posted_at)->not->toBeNull();
});

test('journal entries cannot be bulk deleted from the table', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    Livewire::test(ListJournalEntries::class)
        ->assertActionDoesNotExist(TestAction::make('delete')->table()->bulk());
});

test('journal entry view loads attachment state without missing attribute errors', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    expect(Schema::hasColumn('journal_entries', 'attachment'))->toBeTrue();

    $journalEntry = JournalEntry::create([
        'reference' => 'MAN-003',
        'date' => '2026-05-18',
        'attachment' => null,
        'total_debit' => 100,
        'total_credit' => 100,
        'status' => 'posted',
    ]);

    Livewire::test(ViewJournalEntry::class, ['record' => $journalEntry->id])
        ->assertSuccessful();
});
