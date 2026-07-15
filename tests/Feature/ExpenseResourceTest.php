<?php

use App\Enums\ExpenseTrackingType;
use App\Enums\PaymentTransactionType;
use App\Filament\Resources\Expenses\ExpenseResource;
use App\Filament\Resources\Expenses\Pages\ListExpenses;
use App\Models\Account;
use App\Models\ExpenseTrackingItem;
use App\Models\Payment;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Support\Enums\Width;
use Filament\Tables\Enums\FiltersLayout;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('expenses resource is read only and scoped to expense payments', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $expenseAccount = Account::create([
        'code' => '5200-010',
        'name' => 'Rent Expense',
        'type' => 'Expense',
    ]);

    $expense = Payment::withoutEvents(fn (): Payment => Payment::factory()->create([
        'transaction_type' => PaymentTransactionType::DIRECT_EXPENSE->value,
        'direction' => PaymentTransactionType::DIRECTION_OUTBOUND,
        'expense_account_id' => $expenseAccount->id,
    ]));
    $pettyCashExpense = Payment::withoutEvents(fn (): Payment => Payment::factory()->create([
        'transaction_type' => PaymentTransactionType::PETTY_CASH_EXPENSE->value,
        'direction' => PaymentTransactionType::DIRECTION_OUTBOUND,
        'method' => 'cash',
        'expense_account_id' => $expenseAccount->id,
    ]));
    $supplierPayment = Payment::withoutEvents(fn (): Payment => Payment::factory()->create([
        'transaction_type' => PaymentTransactionType::SUPPLIER_PAYMENT->value,
        'direction' => PaymentTransactionType::DIRECTION_OUTBOUND,
    ]));

    expect(ExpenseResource::canCreate())->toBeFalse()
        ->and(ExpenseResource::canEdit($expense))->toBeFalse()
        ->and(ExpenseResource::canDelete($expense))->toBeFalse();

    Livewire::test(ListExpenses::class)
        ->assertCanSeeTableRecords([$expense, $pettyCashExpense])
        ->assertCanNotSeeTableRecords([$supplierPayment])
        ->assertTableColumnStateSet('expenseAccount.name', 'Rent Expense', $pettyCashExpense)
        ->assertTableColumnStateSet('payment_source', 'Petty Cash', $pettyCashExpense)
        ->filterTable('payment_source', 'petty_cash')
        ->assertCanSeeTableRecords([$pettyCashExpense])
        ->assertCanNotSeeTableRecords([$expense]);
});

test('expenses table filters by tracking item and expense account', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $expenseAccount = Account::create([
        'code' => '6101-100',
        'name' => 'Vehicle Expense',
        'type' => 'Expense',
        'default_tracking_type' => ExpenseTrackingType::VEHICLE->value,
    ]);
    $otherExpenseAccount = Account::create([
        'code' => '6101-200',
        'name' => 'Office Expense',
        'type' => 'Expense',
        'default_tracking_type' => ExpenseTrackingType::DEPARTMENT->value,
    ]);
    $vehicle = ExpenseTrackingItem::create([
        'type' => ExpenseTrackingType::VEHICLE->value,
        'code' => 'VH-001',
        'name' => 'Delivery Van',
    ]);
    $department = ExpenseTrackingItem::create([
        'type' => ExpenseTrackingType::DEPARTMENT->value,
        'code' => 'DEP-001',
        'name' => 'Admin',
    ]);

    $vehicleExpense = Payment::withoutEvents(fn (): Payment => Payment::factory()->create([
        'transaction_type' => PaymentTransactionType::DIRECT_EXPENSE->value,
        'direction' => PaymentTransactionType::DIRECTION_OUTBOUND,
        'expense_account_id' => $expenseAccount->id,
        'expense_tracking_type' => ExpenseTrackingType::VEHICLE->value,
        'expense_tracking_item_id' => $vehicle->id,
    ]));
    $departmentExpense = Payment::withoutEvents(fn (): Payment => Payment::factory()->create([
        'transaction_type' => PaymentTransactionType::DIRECT_EXPENSE->value,
        'direction' => PaymentTransactionType::DIRECTION_OUTBOUND,
        'expense_account_id' => $otherExpenseAccount->id,
        'expense_tracking_type' => ExpenseTrackingType::DEPARTMENT->value,
        'expense_tracking_item_id' => $department->id,
    ]));

    Livewire::test(ListExpenses::class)
        ->filterTable('expense_tracking_item_id', $vehicle->id)
        ->assertCanSeeTableRecords([$vehicleExpense])
        ->assertCanNotSeeTableRecords([$departmentExpense])
        ->resetTableFilters()
        ->filterTable('expense_account_id', $expenseAccount->id)
        ->assertCanSeeTableRecords([$vehicleExpense])
        ->assertCanNotSeeTableRecords([$departmentExpense]);
});

test('expenses table uses a wide four column filter modal', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $component = Livewire::test(ListExpenses::class);
    $table = $component->instance()->getTable();

    expect($table->getFiltersLayout())->toBe(FiltersLayout::Modal)
        ->and($table->getFiltersFormColumns())->toBe(4)
        ->and($table->getFiltersFormWidth())->toBe(Width::SevenExtraLarge)
        ->and($table->getFiltersTriggerAction()->hasModalCloseButton())->toBeTrue()
        ->and($table->getFiltersTriggerAction()->getModalCancelAction())->toBeNull();
});
