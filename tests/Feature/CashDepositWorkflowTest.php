<?php

use App\Filament\Resources\CashDeposits\Pages\CreateCashDeposit;
use App\Filament\Resources\CashDeposits\Pages\EditCashDeposit;
use App\Filament\Resources\CashDeposits\Pages\ListCashDeposits;
use App\Filament\Resources\CashDeposits\Pages\ViewCashDeposit;
use App\Models\Account;
use App\Models\Bank;
use App\Models\BankTransaction;
use App\Models\CashDeposit;
use App\Models\JournalEntry;
use App\Models\JournalItem;
use App\Models\User;
use App\Services\Accounting\PostCashDeposit;
use App\Services\Accounting\ReverseCashDeposit;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('posting a cash deposit moves cash into the selected bank and posts a balanced journal', function (): void {
    $cashAccount = createCashBalance(1000);
    $bank = createDepositBank(500);
    $user = User::factory()->create(['role' => UserRole::Finance]);
    $deposit = CashDeposit::factory()->create([
        'bank_id' => $bank->id,
        'cash_account_id' => $cashAccount->id,
        'amount' => 300,
        'deposit_date' => '2026-08-19',
    ]);

    app(PostCashDeposit::class)->handle($deposit, $user);

    $deposit = $deposit->fresh();
    $journal = $deposit->journalEntries()->whereNull('reversal_of_journal_entry_id')->firstOrFail();

    expect($deposit->status)->toBe(CashDeposit::STATUS_POSTED)
        ->and($deposit->posted_by)->toBe($user->id)
        ->and((float) $bank->fresh()->current_balance)->toBe(800.0)
        ->and((float) $bank->fresh()->calculated_balance)->toBe(800.0)
        ->and((float) BankTransaction::where('source_type', 'cash_deposit')->sum('balance_delta'))->toBe(300.0)
        ->and((float) $journal->total_debit)->toBe(300.0)
        ->and((float) $journal->total_credit)->toBe(300.0);

    expect(JournalItem::where('journal_entry_id', $journal->id)
        ->where('account_id', Account::where('code', Account::CODE_BANK)->value('id'))
        ->value('debit'))->toEqual('300.00')
        ->and(JournalItem::where('journal_entry_id', $journal->id)
            ->where('account_id', $cashAccount->id)
            ->value('credit'))->toEqual('300.00');
});

test('posting an other sources deposit goes directly to the bank without a cash balance', function (): void {
    $incomeAccount = CashDeposit::otherIncomeAccount();
    $bank = createDepositBank(500);
    $deposit = CashDeposit::factory()->create([
        'deposit_type' => CashDeposit::TYPE_OTHER_SOURCES,
        'bank_id' => $bank->id,
        'cash_account_id' => null,
        'income_account_id' => null,
        'amount' => 300,
    ]);

    app(PostCashDeposit::class)->handle($deposit);

    $journal = $deposit->fresh()->journalEntries()->firstOrFail();

    expect($deposit->fresh()->status)->toBe(CashDeposit::STATUS_POSTED)
        ->and((float) $bank->fresh()->current_balance)->toBe(800.0)
        ->and(JournalItem::where('journal_entry_id', $journal->id)
            ->where('account_id', $incomeAccount->id)
            ->value('credit'))->toEqual('300.00')
        ->and(BankTransaction::where('source_type', 'cash_deposit')
            ->where('source_id', $deposit->id)
            ->value('counterparty'))->toBe('Other Income');
});

test('posting an other income deposit goes directly to the bank without a cash balance', function (): void {
    $incomeAccount = Account::getSystemAccount(
        Account::CODE_OTHER_INCOME,
        'Other Income',
        'Revenue',
    );
    $bank = createDepositBank(500);
    $deposit = CashDeposit::factory()->create([
        'deposit_type' => CashDeposit::TYPE_OTHER_INCOME,
        'bank_id' => $bank->id,
        'cash_account_id' => null,
        'income_account_id' => $incomeAccount->id,
        'amount' => 300,
    ]);

    app(PostCashDeposit::class)->handle($deposit);

    $deposit = $deposit->fresh();
    $journal = $deposit->journalEntries()->firstOrFail();

    expect($deposit->status)->toBe(CashDeposit::STATUS_POSTED)
        ->and((float) $bank->fresh()->current_balance)->toBe(800.0)
        ->and(JournalItem::where('journal_entry_id', $journal->id)
            ->where('account_id', $incomeAccount->id)
            ->value('credit'))->toEqual('300.00')
        ->and(BankTransaction::where('source_type', 'cash_deposit')
            ->where('source_id', $deposit->id)
            ->value('counterparty'))->toBe('Other Income');

    app(ReverseCashDeposit::class)->handle($deposit, 'Incorrect income source');

    expect((float) $bank->fresh()->current_balance)->toBe(500.0)
        ->and(JournalItem::where('journal_entry_id', $deposit->fresh()->journalEntries()->latest('id')->value('id'))
            ->where('account_id', $incomeAccount->id)
            ->value('debit'))->toEqual('300.00');
});

test('posting is rejected without enough cash and leaves the ledger unchanged', function (): void {
    $cashAccount = createCashBalance(100);
    $bank = createDepositBank(500);
    $deposit = CashDeposit::factory()->create([
        'bank_id' => $bank->id,
        'cash_account_id' => $cashAccount->id,
        'amount' => 300,
    ]);

    expect(fn () => app(PostCashDeposit::class)->handle($deposit))
        ->toThrow(RuntimeException::class, 'Insufficient cash available');

    expect($deposit->fresh()->status)->toBe(CashDeposit::STATUS_PENDING)
        ->and((float) $bank->fresh()->current_balance)->toBe(500.0)
        ->and($deposit->journalEntries()->count())->toBe(0);
});

test('reversing a posted deposit restores cash and bank balances with a linked journal', function (): void {
    $cashAccount = createCashBalance(1000);
    $bank = createDepositBank(500);
    $deposit = CashDeposit::factory()->create([
        'bank_id' => $bank->id,
        'cash_account_id' => $cashAccount->id,
        'amount' => 300,
    ]);

    app(PostCashDeposit::class)->handle($deposit);
    app(ReverseCashDeposit::class)->handle($deposit->fresh(), 'Duplicate bank slip');

    $deposit = $deposit->fresh();
    $journals = $deposit->journalEntries()->orderBy('id')->get();

    expect($deposit->status)->toBe(CashDeposit::STATUS_REVERSED)
        ->and($deposit->reversal_reason)->toBe('Duplicate bank slip')
        ->and((float) $bank->fresh()->current_balance)->toBe(500.0)
        ->and((float) $bank->fresh()->transaction_balance)->toBe(0.0)
        ->and($journals)->toHaveCount(2)
        ->and($journals->last()->reversal_of_journal_entry_id)->toBe($journals->first()->id);
});

test('a posted deposit cannot be posted or reversed twice', function (): void {
    $cashAccount = createCashBalance(1000);
    $deposit = CashDeposit::factory()->create([
        'bank_id' => createDepositBank(500)->id,
        'cash_account_id' => $cashAccount->id,
        'amount' => 100,
    ]);

    app(PostCashDeposit::class)->handle($deposit);

    expect(fn () => app(PostCashDeposit::class)->handle($deposit->fresh()))
        ->toThrow(RuntimeException::class, 'Only pending cash deposits');

    app(ReverseCashDeposit::class)->handle($deposit->fresh(), 'Correction');

    expect(fn () => app(ReverseCashDeposit::class)->handle($deposit->fresh(), 'Again'))
        ->toThrow(RuntimeException::class, 'Only posted cash deposits');
});

test('finance can create a pending deposit from the Filament form', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $cashAccount = createCashBalance(1000);
    $bank = createDepositBank();

    Livewire::test(CreateCashDeposit::class)
        ->fillForm([
            'bank_id' => $bank->id,
            'amount' => 250,
            'deposit_date' => '2026-08-19',
            'reference' => 'SLIP-100',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $deposit = CashDeposit::firstOrFail();

    expect($deposit->status)->toBe(CashDeposit::STATUS_PENDING)
        ->and($deposit->deposit_number)->toStartWith('CD-')
        ->and($deposit->cash_account_id)->toBe($cashAccount->id);
});

test('finance can create a pending deposit from other sources without selecting an account', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $bank = createDepositBank();
    $component = Livewire::test(CreateCashDeposit::class);
    $incomeAccount = Account::query()->where('code', Account::CODE_OTHER_INCOME)->firstOrFail();

    $component
        ->fillForm([
            'deposit_type' => CashDeposit::TYPE_OTHER_SOURCES,
            'bank_id' => $bank->id,
            'amount' => 250,
            'deposit_date' => '2026-08-19',
            'reference' => 'SLIP-OTHER-100',
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect();

    $deposit = CashDeposit::firstOrFail();

    expect($deposit->deposit_type)->toBe(CashDeposit::TYPE_OTHER_SOURCES)
        ->and($deposit->cash_account_id)->toBeNull()
        ->and($deposit->income_account_id)->toBe($incomeAccount->id);
});

test('cash deposit form hides generated and automatically selected fields', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));

    Livewire::test(CreateCashDeposit::class)
        ->assertFormFieldDoesNotExist('deposit_number')
        ->assertFormFieldDoesNotExist('cash_account_id')
        ->assertFormFieldDoesNotExist('income_account_id');
});

test('cash sales option shows the current cash on hand balance', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    createCashBalance(1234.5);

    $component = Livewire::test(CreateCashDeposit::class);
    $fields = $component->instance()->form->getFlatFields(withHidden: true);
    $depositType = $fields['deposit_type'];

    expect($depositType)->toBeInstanceOf(Select::class)
        ->and($depositType->getOptions()[CashDeposit::TYPE_CASH_TRANSFER])
        ->toContain('1,234.50');
});

test('cash deposit pages use the generated deposit number in their titles', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $deposit = CashDeposit::factory()->create([
        'deposit_number' => 'CD-TITLE-001',
        'bank_id' => createDepositBank()->id,
        'cash_account_id' => createCashBalance(1000)->id,
    ]);

    expect(Livewire::test(ViewCashDeposit::class, ['record' => $deposit->id])->instance()->getTitle())
        ->toBe('Cash deposit #CD-TITLE-001')
        ->and(Livewire::test(EditCashDeposit::class, ['record' => $deposit->id])->instance()->getTitle())
        ->toBe('Edit #CD-TITLE-001');
});

test('cash deposit creation does not offer create another', function (): void {
    $property = new ReflectionProperty(CreateCashDeposit::class, 'canCreateAnother');

    expect($property->getValue())->toBeFalse();
});

test('cash deposit authorization is limited to finance and administrators', function (): void {
    $deposit = CashDeposit::factory()->create([
        'bank_id' => createDepositBank()->id,
        'cash_account_id' => createCashBalance(1000)->id,
    ]);

    expect(Gate::forUser(User::factory()->create(['role' => UserRole::Finance]))->allows('post', $deposit))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create(['role' => UserRole::Admin]))->allows('post', $deposit))->toBeTrue()
        ->and(Gate::forUser(User::factory()->create(['role' => UserRole::Sales]))->allows('post', $deposit))->toBeFalse();
});

test('cash deposit status actions are available on view and edit pages', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $deposit = CashDeposit::factory()->create([
        'bank_id' => createDepositBank(500)->id,
        'cash_account_id' => createCashBalance(1000)->id,
        'amount' => 300,
    ]);

    Livewire::test(EditCashDeposit::class, ['record' => $deposit->id])
        ->assertActionVisible('post')
        ->assertActionHidden('reverse');

    Livewire::test(ViewCashDeposit::class, ['record' => $deposit->id])
        ->assertActionVisible('post')
        ->assertActionHidden('reverse')
        ->callAction('post')
        ->assertNotified('Cash deposit posted');

    expect($deposit->fresh()->status)->toBe(CashDeposit::STATUS_POSTED);

    Livewire::test(ViewCashDeposit::class, ['record' => $deposit->id])
        ->assertActionVisible('reverse')
        ->assertActionHidden('post')
        ->callAction('reverse', ['reason' => 'Duplicate deposit'])
        ->assertNotified('Cash deposit reversed');

    expect($deposit->fresh()->status)->toBe(CashDeposit::STATUS_REVERSED);
});

test('cash deposit table does not show a view action', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    $deposit = CashDeposit::factory()->create([
        'bank_id' => createDepositBank()->id,
        'cash_account_id' => createCashBalance(1000)->id,
    ]);

    Livewire::test(ListCashDeposits::class)
        ->assertActionDoesNotExist(TestAction::make('view')->table($deposit));
});

function createDepositBank(float $balance = 0): Bank
{
    return Bank::create([
        'name' => 'Operating Bank '.fake()->unique()->numerify('###'),
        'code' => fake()->unique()->bothify('BANK-####'),
        'account_number' => fake()->unique()->numerify('##########'),
        'account_holder_name' => 'Printerp',
        'bank_name' => 'Commercial Bank',
        'current_balance' => $balance,
        'status' => 'active',
    ]);
}

function createCashBalance(float $amount, ?Account $cashAccount = null): Account
{
    $cashAccount ??= Account::getSystemAccount(Account::CODE_CASH, 'Cash in Hand', 'Asset');
    $revenueAccount = Account::getSystemAccount('4000', 'Sales Revenue', 'Revenue');
    $journal = JournalEntry::create([
        'date' => now(),
        'reference' => fake()->unique()->bothify('CASH-####'),
        'total_debit' => $amount,
        'total_credit' => $amount,
        'status' => 'posted',
        'posted_at' => now(),
    ]);

    JournalItem::create([
        'journal_entry_id' => $journal->id,
        'account_id' => $cashAccount->id,
        'debit' => $amount,
        'credit' => 0,
    ]);
    JournalItem::create([
        'journal_entry_id' => $journal->id,
        'account_id' => $revenueAccount->id,
        'debit' => 0,
        'credit' => $amount,
    ]);

    return $cashAccount;
}
