<?php

use App\Enums\ExpenseTrackingType;
use App\Enums\PaymentTransactionType;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Resources\Payments\Pages\ViewPayment;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Account;
use App\Models\Bank;
use App\Models\ExpenseTrackingItem;
use App\Models\JournalEntry;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Tables\Filters\TernaryFilter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('payment form presents payment numbers as generated on save', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    Livewire::test(CreatePayment::class)
        ->assertSee('Auto-generated on save')
        ->assertFormFieldDoesNotExist('payment_number');
});

test('payment form shows insufficient bank balance as a form error', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $bank = createPaymentFormBank('Low Balance Bank', 100);
    $supplier = Partner::factory()->create([
        'is_supplier' => true,
    ]);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
            'partner_id' => $supplier->id,
            'amount' => 250,
            'payment_date' => now()->toDateString(),
            'method' => 'bank',
            'bank_id' => $bank->id,
        ])
        ->call('create')
        ->assertHasFormErrors([
            'bank_id',
        ]);

    expect(Payment::count())->toBe(0)
        ->and((float) $bank->fresh()->current_balance)->toBe(100.0);
});

test('payment form keeps transaction type searchable and drives dependent fields', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    Livewire::test(CreatePayment::class)
        ->assertFormSet([
            'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
        ])
        ->assertFormFieldDoesNotExist('payment_direction')
        ->assertFormFieldExists('transaction_type', function (Select $field): bool {
            expect($field->isSearchable())->toBeTrue()
                ->and($field->getOptions())->toHaveKeys([
                    'Common',
                    'Petty Cash',
                    'Bonds',
                ])
                ->and($field->getOptions()['Common'])
                ->toBe([
                    PaymentTransactionType::CUSTOMER_RECEIPT->value => 'Receive from customer',
                    PaymentTransactionType::SUPPLIER_PAYMENT->value => 'Pay supplier / bill',
                    PaymentTransactionType::DIRECT_EXPENSE->value => 'Pay expense',
                ])
                ->and($field->getOptions()['Petty Cash'])
                ->toBe([
                    PaymentTransactionType::PETTY_CASH_FUNDING->value => 'Fund petty cash',
                    PaymentTransactionType::PETTY_CASH_EXPENSE->value => 'Record petty cash expense',
                ])
                ->and($field->getOptions())
                ->not->toHaveKey('Payroll & Employee Loans')
                ->and($field->getOptions()['Common'])
                ->not->toHaveKey(PaymentTransactionType::PETTY_CASH_EXPENSE->value)
                ->and($field->getOptions())
                ->not->toHaveKey(PaymentTransactionType::CASH_SALE_RECEIPT->value);

            return true;
        })
        ->assertFormFieldIsHidden('expense_account_id')
        ->set('data.transaction_type', PaymentTransactionType::DIRECT_EXPENSE->value)
        ->assertFormSet([
            'transaction_type' => PaymentTransactionType::DIRECT_EXPENSE->value,
        ])
        ->assertFormFieldIsVisible('expense_account_id')
        ->assertFormFieldExists('method', function (Select $field): bool {
            expect($field->getOptions())->not->toHaveKey('petty_cash');

            return true;
        })
        ->set('data.transaction_type', PaymentTransactionType::PETTY_CASH_EXPENSE->value)
        ->assertFormSet([
            'method' => 'petty_cash',
        ])
        ->assertFormFieldIsHidden('method')
        ->assertFormFieldIsHidden('withholding_amount')
        ->assertFormFieldIsHidden('bank_id');
});

test('payment entry forms expose withholding where receipts and supplier payments are created', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    Livewire::test(CreatePayment::class)
        ->assertFormFieldIsVisible('withholding_amount')
        ->set('data.transaction_type', PaymentTransactionType::DIRECT_EXPENSE->value)
        ->assertFormFieldIsHidden('withholding_amount')
        ->set('data.transaction_type', PaymentTransactionType::SUPPLIER_PAYMENT->value)
        ->assertFormFieldIsVisible('withholding_amount');

    foreach ([
        app_path('Filament/Resources/JobOrders/Actions/JobOrderActions.php'),
        app_path('Filament/Resources/PurchaseOrders/Actions/PurchaseOrderActions.php'),
        app_path('Filament/Resources/SalesOrders/Actions/SalesOrderActions.php'),
        app_path('Filament/Resources/JobOrders/RelationManagers/PaymentsRelationManager.php'),
        app_path('Filament/Resources/PurchaseOrders/RelationManagers/PaymentsRelationManager.php'),
        app_path('Filament/Resources/SalesOrders/RelationManagers/PaymentsRelationManager.php'),
    ] as $path) {
        expect(file_get_contents($path))->toContain("TextInput::make('withholding_amount')");
    }
});

test('expense account default tracking reveals the relevant structured tracking field', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $expenseAccount = Account::create([
        'code' => '6101-005',
        'name' => 'Fuel Expense',
        'type' => 'Expense',
        'default_tracking_type' => ExpenseTrackingType::VEHICLE->value,
    ]);

    Livewire::test(CreatePayment::class)
        ->set('data.transaction_type', PaymentTransactionType::DIRECT_EXPENSE->value)
        ->set('data.expense_account_id', $expenseAccount->id)
        ->assertFormSet([
            'expense_tracking_type' => ExpenseTrackingType::VEHICLE->value,
        ])
        ->assertFormFieldIsVisible('expense_tracking_item_id')
        ->assertFormFieldIsHidden('expense_tracking_employee_id')
        ->assertFormFieldIsHidden('expense_tracking_bid_id');
});

test('tracked direct expenses are created through payments and posted to the ledger', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $bank = createPaymentFormBank('Expense Bank', 5000);
    $expenseAccount = Account::create([
        'code' => '6101-005',
        'name' => 'Fuel Expense',
        'type' => 'Expense',
        'default_tracking_type' => ExpenseTrackingType::VEHICLE->value,
    ]);
    $vehicle = ExpenseTrackingItem::create([
        'type' => ExpenseTrackingType::VEHICLE->value,
        'code' => '3-00270',
        'name' => 'Delivery Vehicle',
    ]);

    Livewire::test(CreatePayment::class)
        ->fillForm([
            'transaction_type' => PaymentTransactionType::DIRECT_EXPENSE->value,
            'amount' => 650,
            'payment_date' => '2026-07-08',
            'method' => 'bank',
            'bank_id' => $bank->id,
            'expense_account_id' => $expenseAccount->id,
            'expense_tracking_type' => ExpenseTrackingType::VEHICLE->value,
            'expense_tracking_item_id' => $vehicle->id,
            'reference' => 'Fuel receipt',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $payment = Payment::query()->with(['expenseTrackingItem'])->firstOrFail();
    $journalEntry = JournalEntry::query()
        ->where('source_type', Payment::class)
        ->where('source_id', $payment->id)
        ->firstOrFail();

    expect($payment->direction)->toBe(PaymentTransactionType::DIRECTION_OUTBOUND)
        ->and($payment->transaction_type)->toBe(PaymentTransactionType::DIRECT_EXPENSE->value)
        ->and($payment->expense_account_id)->toBe($expenseAccount->id)
        ->and($payment->expenseTrackingLabel())->toBe('Vehicle: 3-00270 - Delivery Vehicle')
        ->and((float) $bank->fresh()->current_balance)->toBe(4350.0);

    $this->assertDatabaseHas('journal_items', [
        'journal_entry_id' => $journalEntry->id,
        'account_id' => $expenseAccount->id,
        'debit' => 650.00,
    ]);
});

test('petty cash expenses use the separate petty cash workflow', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $expenseAccount = Account::create([
        'code' => '5300-010',
        'name' => 'Office Supplies',
        'type' => 'Expense',
        'default_tracking_type' => ExpenseTrackingType::NONE->value,
    ]);

    Livewire::test(CreatePayment::class)
        ->set('data.transaction_type', PaymentTransactionType::PETTY_CASH_EXPENSE->value)
        ->assertFormFieldIsHidden('petty_cash_account_id')
        ->assertFormFieldIsVisible('expense_account_id')
        ->fillForm([
            'transaction_type' => PaymentTransactionType::PETTY_CASH_EXPENSE->value,
            'amount' => 125,
            'payment_date' => '2026-07-08',
            'expense_account_id' => $expenseAccount->id,
            'reference' => 'Stationery receipt',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $payment = Payment::query()->firstOrFail();
    $journalEntry = JournalEntry::query()
        ->where('source_type', Payment::class)
        ->where('source_id', $payment->id)
        ->firstOrFail();
    $pettyCashAccount = Account::query()->where('code', '1090')->firstOrFail();

    expect($payment->transaction_type)->toBe(PaymentTransactionType::PETTY_CASH_EXPENSE->value)
        ->and($payment->method)->toBe('petty_cash')
        ->and($payment->paymentSourceLabel())->toBe('Petty Cash')
        ->and($payment->petty_cash_account_id)->toBeNull();

    $this->assertDatabaseHas('journal_items', [
        'journal_entry_id' => $journalEntry->id,
        'account_id' => $expenseAccount->id,
        'debit' => 125.00,
    ]);

    $this->assertDatabaseHas('journal_items', [
        'journal_entry_id' => $journalEntry->id,
        'account_id' => $pettyCashAccount->id,
        'credit' => 125.00,
    ]);
});

test('payments table tabs are all inbound and outbound', function (): void {
    $tabs = (new ListPayments)->getTabs();

    expect(array_keys($tabs))->toBe(['all', 'inbound', 'outbound'])
        ->and(ListPayments::TABLE_TABS)->toBe([
            'all' => 'All',
            'inbound' => 'Inbound',
            'outbound' => 'Outbound',
        ]);
});

test('payments table tabs render in the table toolbar', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    Livewire::test(ListPayments::class)
        ->assertSeeHtml('payments-toolbar-tabs')
        ->assertDontSeeHtml('resourceTabs')
        ->assertSeeInOrder(['All', 'Inbound', 'Outbound']);
});

test('payments table tabs filter by payment direction', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $inboundPayment = Payment::withoutEvents(fn (): Payment => Payment::factory()->create([
        'direction' => PaymentTransactionType::DIRECTION_INBOUND,
        'transaction_type' => PaymentTransactionType::EMPLOYEE_LOAN_REPAYMENT->value,
    ]));
    $outboundPayment = Payment::withoutEvents(fn (): Payment => Payment::factory()->create([
        'direction' => PaymentTransactionType::DIRECTION_OUTBOUND,
        'transaction_type' => PaymentTransactionType::DIRECT_EXPENSE->value,
    ]));

    Livewire::test(ListPayments::class)
        ->set('activeTab', 'inbound')
        ->assertCanSeeTableRecords([$inboundPayment])
        ->assertCanNotSeeTableRecords([$outboundPayment])
        ->set('activeTab', 'outbound')
        ->assertCanSeeTableRecords([$outboundPayment])
        ->assertCanNotSeeTableRecords([$inboundPayment]);
});

test('payments table keeps only the general payment filters', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $component = Livewire::test(ListPayments::class)
        ->assertTableFilterExists('payment_date_range')
        ->assertTableFilterExists('transaction_type')
        ->assertTableFilterExists('posted_status');

    expect($component->instance()->getTable()->getFilters())
        ->not->toHaveKey('direction')
        ->not->toHaveKey('method')
        ->and($component->instance()->getTable()->getFilters()['posted_status'])
        ->toBeInstanceOf(TernaryFilter::class);
});

test('payments are view and void only after posting', function (): void {
    $payment = Payment::factory()->create();

    expect(PaymentResource::getPages())
        ->not->toHaveKey('edit')
        ->and(PaymentResource::canEdit($payment))->toBeFalse()
        ->and(PaymentResource::canDelete($payment))->toBeFalse();
});

test('payment table and view page expose the same void action', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $payment = Payment::withoutEvents(fn (): Payment => Payment::factory()->create([
        'voided_at' => null,
    ]));

    JournalEntry::create([
        'date' => now(),
        'reference' => $payment->payment_number,
        'source_type' => Payment::class,
        'source_id' => $payment->id,
        'narration' => 'Payment posted',
        'total_debit' => 100,
        'total_credit' => 100,
        'status' => 'posted',
        'posted_at' => now(),
    ]);

    Livewire::test(ListPayments::class)
        ->assertTableActionVisible('void', $payment);

    Livewire::test(ViewPayment::class, ['record' => $payment->id])
        ->assertActionVisible('void');
});

function createPaymentFormBank(string $name, float $currentBalance): Bank
{
    return Bank::create([
        'name' => $name,
        'code' => str($name)->headline()->replace(' ', '-')->upper()->limit(20, '')->toString(),
        'account_number' => fake()->numerify('##########'),
        'account_holder_name' => 'Packledge',
        'bank_name' => $name,
        'branch' => 'Main',
        'current_balance' => $currentBalance,
        'status' => 'active',
    ]);
}
