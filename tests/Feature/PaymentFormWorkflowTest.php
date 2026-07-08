<?php

use App\Enums\ExpenseTrackingType;
use App\Enums\PaymentTransactionType;
use App\Filament\Resources\Payments\Pages\CreatePayment;
use App\Filament\Resources\Payments\Pages\ListPayments;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('payment form previews the next payment number', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $customer = Partner::factory()->create([
        'is_customer' => true,
    ]);

    Payment::create([
        'partner_id' => $customer->id,
        'payment_date' => now(),
        'amount' => 100,
        'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
        'method' => 'cash',
    ]);

    Livewire::test(CreatePayment::class)
        ->assertFormSet([
            'payment_number' => 'PAY-000002',
        ]);
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
            'payment_direction' => PaymentTransactionType::DIRECTION_OUTBOUND,
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

test('payment form filters transaction types by direction and keeps the transaction type searchable', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    Livewire::test(CreatePayment::class)
        ->assertFormSet([
            'payment_direction' => PaymentTransactionType::DIRECTION_INBOUND,
            'transaction_type' => PaymentTransactionType::CUSTOMER_RECEIPT->value,
        ])
        ->assertFormFieldExists('transaction_type', function (Select $field): bool {
            expect($field->isSearchable())->toBeTrue()
                ->and($field->getOptions())->toHaveKey(PaymentTransactionType::CUSTOMER_RECEIPT->value)
                ->and($field->getOptions())->not->toHaveKey(PaymentTransactionType::DIRECT_EXPENSE->value);

            return true;
        })
        ->assertFormFieldIsHidden('expense_account_id')
        ->set('data.payment_direction', PaymentTransactionType::DIRECTION_OUTBOUND)
        ->assertFormSet([
            'transaction_type' => PaymentTransactionType::DIRECT_EXPENSE->value,
        ])
        ->assertFormFieldExists('transaction_type', function (Select $field): bool {
            expect($field->getOptions())->toHaveKey(PaymentTransactionType::DIRECT_EXPENSE->value)
                ->and($field->getOptions())->not->toHaveKey(PaymentTransactionType::CUSTOMER_RECEIPT->value);

            return true;
        })
        ->assertFormFieldIsVisible('expense_account_id');
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
        ->set('data.payment_direction', PaymentTransactionType::DIRECTION_OUTBOUND)
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
            'payment_direction' => PaymentTransactionType::DIRECTION_OUTBOUND,
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

test('payments table tabs are all income and expenses', function (): void {
    $tabs = (new ListPayments)->getTabs();

    expect(array_keys($tabs))->toBe(['all', 'income', 'expenses']);
});

function createPaymentFormBank(string $name, float $currentBalance): Bank
{
    return Bank::create([
        'name' => $name,
        'code' => str($name)->headline()->replace(' ', '-')->upper()->limit(20, '')->toString(),
        'account_number' => fake()->numerify('##########'),
        'account_holder_name' => 'PrintOS',
        'bank_name' => $name,
        'branch' => 'Main',
        'current_balance' => $currentBalance,
        'status' => 'active',
    ]);
}
