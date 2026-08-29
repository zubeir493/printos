<?php

use App\Enums\PaymentTransactionType;
use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Filament\Resources\SalesOrders\RelationManagers\PaymentsRelationManager;
use App\Models\Account;
use App\Models\Bank;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\Setting;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SalesOrderPaymentService;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

test('paid-now bank checkout completes the order and posts to the selected bank', function (): void {
    $fixture = createSalesOrderPaymentFixture();
    $bank = createSalesOrderPaymentBank();

    $order = SalesOrder::create([
        'warehouse_id' => $fixture['warehouse']->id,
        'partner_id' => $fixture['customer']->id,
        'order_date' => now(),
        'payment_mode' => 'cash',
        'payment_method' => 'bank',
        'bank_id' => $bank->id,
        'payment_reference' => 'TRX-001',
        'subtotal' => 100,
        'total' => 100,
        'status' => SalesOrder::STATUS_DRAFT,
    ]);
    createSalesOrderPaymentItem($order, $fixture['item']);

    $order->update(['status' => SalesOrder::STATUS_COMPLETED]);

    $payment = $order->fresh()->payments()->sole();
    $bankAccount = Account::getSystemAccount(Account::CODE_BANK, 'Bank Current Account', 'Asset');

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_COMPLETED)
        ->and($payment->transaction_type)->toBe(PaymentTransactionType::CASH_SALE_RECEIPT->value)
        ->and($payment->method)->toBe('bank')
        ->and($payment->bank_id)->toBe($bank->id)
        ->and($payment->reference)->toBe('TRX-001')
        ->and((float) $bank->fresh()->current_balance)->toBe(1100.0)
        ->and((float) InventoryBalance::query()
            ->where('inventory_item_id', $fixture['item']->id)
            ->where('warehouse_id', $fixture['warehouse']->id)
            ->value('quantity_on_hand'))->toBe(9.0)
        ->and($order->fresh()->balance)->toBe(0.0);

    expect(Account::getSystemAccount(Account::CODE_CASH, 'Cash in Hand', 'Asset')->journalItems()
        ->whereHas('journalEntry', fn ($query) => $query
            ->where('source_type', SalesOrder::class)
            ->where('source_id', $order->id))
        ->where('debit', 100)
        ->exists())->toBeFalse()
        ->and($bankAccount->journalItems()
            ->whereHas('journalEntry', fn ($query) => $query
                ->where('source_type', SalesOrder::class)
                ->where('source_id', $order->id))
            ->where('debit', 100)
            ->exists())->toBeTrue();
});

test('paid-now cheque checkout uses the selected bank lifecycle', function (): void {
    $fixture = createSalesOrderPaymentFixture();
    $bank = createSalesOrderPaymentBank('Cheque Bank');

    $order = SalesOrder::create([
        'warehouse_id' => $fixture['warehouse']->id,
        'partner_id' => $fixture['customer']->id,
        'order_date' => now(),
        'payment_mode' => 'cash',
        'payment_method' => 'cheque',
        'bank_id' => $bank->id,
        'subtotal' => 100,
        'total' => 100,
        'status' => SalesOrder::STATUS_DRAFT,
    ]);
    createSalesOrderPaymentItem($order, $fixture['item']);

    $order->update(['status' => SalesOrder::STATUS_COMPLETED]);

    expect($order->fresh()->payments()->sole()->method)->toBe('cheque')
        ->and((float) $bank->fresh()->current_balance)->toBe(1100.0);
});

test('credit orders can record an initial payment without releasing stock', function (): void {
    $fixture = createSalesOrderPaymentFixture();
    $bank = createSalesOrderPaymentBank('Initial Payment Bank');

    $order = SalesOrder::create([
        'warehouse_id' => $fixture['warehouse']->id,
        'partner_id' => $fixture['customer']->id,
        'order_date' => now(),
        'payment_mode' => 'credit',
        'subtotal' => 200,
        'total' => 200,
        'status' => SalesOrder::STATUS_DRAFT,
    ]);
    createSalesOrderPaymentItem($order, $fixture['item'], 2, 100, 200);

    app(SalesOrderPaymentService::class)->processMultiplePayments($order, [[
        'amount' => 75,
        'method' => 'bank',
        'bank_id' => $bank->id,
        'reference' => 'INITIAL-001',
    ]]);

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_DEPOSIT_RECEIVED)
        ->and((float) $order->fresh()->paid_amount)->toBe(75.0)
        ->and((float) $order->fresh()->balance)->toBe(125.0)
        ->and((float) InventoryBalance::query()
            ->where('inventory_item_id', $fixture['item']->id)
            ->where('warehouse_id', $fixture['warehouse']->id)
            ->value('quantity_on_hand'))->toBe(10.0)
        ->and((float) $bank->fresh()->current_balance)->toBe(1075.0);

    $order->update(['status' => SalesOrder::STATUS_SUBMITTED]);

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_SUBMITTED)
        ->and((float) InventoryBalance::query()
            ->where('inventory_item_id', $fixture['item']->id)
            ->where('warehouse_id', $fixture['warehouse']->id)
            ->value('quantity_on_hand'))->toBe(8.0);
});

test('credit orders with full initial payment complete only after item submission', function (): void {
    $fixture = createSalesOrderPaymentFixture();

    $order = SalesOrder::create([
        'warehouse_id' => $fixture['warehouse']->id,
        'partner_id' => $fixture['customer']->id,
        'order_date' => now(),
        'payment_mode' => 'credit',
        'subtotal' => 100,
        'total' => 100,
        'status' => SalesOrder::STATUS_DRAFT,
    ]);
    createSalesOrderPaymentItem($order, $fixture['item']);

    app(SalesOrderPaymentService::class)->processMultiplePayments($order, [[
        'amount' => 100,
        'method' => 'cash',
    ]]);

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_DEPOSIT_RECEIVED)
        ->and((float) $order->fresh()->balance)->toBe(0.0)
        ->and((float) InventoryBalance::query()
            ->where('inventory_item_id', $fixture['item']->id)
            ->where('warehouse_id', $fixture['warehouse']->id)
            ->value('quantity_on_hand'))->toBe(10.0);

    $order->update([
        'status' => $order->isPaidInFull()
            ? SalesOrder::STATUS_COMPLETED
            : SalesOrder::STATUS_SUBMITTED,
    ]);

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_COMPLETED)
        ->and((float) InventoryBalance::query()
            ->where('inventory_item_id', $fixture['item']->id)
            ->where('warehouse_id', $fixture['warehouse']->id)
            ->value('quantity_on_hand'))->toBe(9.0);
});

test('sales order creation form exposes checkout and initial payment fields', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('sales'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Sales,
    ]));

    Livewire::test(CreateSalesOrder::class)
        ->assertFormFieldExists('payment_mode')
        ->assertFormFieldExists('payment_method')
        ->assertFormFieldExists('bank_id')
        ->assertFormFieldExists('due_date')
        ->assertFormFieldExists('initial_payment_amount')
        ->assertFormFieldExists('initial_payment_method')
        ->assertFormFieldExists('initial_payment_bank_id');
});

test('sales order form shows settlement-specific fields', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('sales'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Sales,
    ]));

    Livewire::test(CreateSalesOrder::class)
        ->fillForm([
            'payment_mode' => 'cash',
            'payment_method' => 'cash',
        ])
        ->assertFormFieldVisible('payment_method')
        ->assertFormFieldHidden('bank_id')
        ->assertFormFieldHidden('due_date')
        ->assertFormFieldHidden('initial_payment_amount')
        ->fillForm([
            'payment_mode' => 'credit',
            'has_initial_payment' => false,
        ])
        ->assertFormFieldHidden('initial_payment_amount')
        ->fillForm([
            'has_initial_payment' => true,
            'initial_payment_method' => 'cash',
            'initial_payment_amount' => 50,
        ])
        ->assertFormFieldVisible('initial_payment_amount')
        ->assertFormFieldHidden('initial_payment_bank_id')
        ->assertFormFieldHidden('initial_payment_reference')
        ->fillForm([
            'initial_payment_method' => 'bank',
        ])
        ->assertFormFieldHidden('payment_method')
        ->assertFormFieldHidden('bank_id')
        ->assertFormFieldVisible('due_date')
        ->assertFormFieldVisible('initial_payment_amount')
        ->assertFormFieldVisible('initial_payment_bank_id')
        ->assertFormFieldVisible('initial_payment_reference');
});

test('sales order form recalculates totals live when items change', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('sales'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Sales,
    ]));

    $fixture = createSalesOrderPaymentFixture();
    Setting::getSettings()->update([
        'vat_enabled' => true,
        'vat_rate' => 15,
    ]);

    $component = Livewire::test(CreateSalesOrder::class);
    $itemKey = array_key_first($component->get('data.salesOrderItems'));

    $component
        ->set("data.salesOrderItems.{$itemKey}.inventory_item_id", $fixture['item']->id)
        ->assertFormSet([
            'subtotal' => 100,
            'tax_amount' => 15,
            'total' => 115,
        ])
        ->set("data.salesOrderItems.{$itemKey}.quantity", 2)
        ->assertFormSet([
            'subtotal' => 200,
            'tax_amount' => 30,
            'total' => 230,
        ]);
});

test('sales order form validates bank accounts for bank settlements', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('sales'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Sales,
    ]));

    $fixture = createSalesOrderPaymentFixture();
    $items = [[
        'inventory_item_id' => $fixture['item']->id,
        'quantity' => 1,
        'unit_price' => 100,
        'total' => 100,
        'unit_label' => 'unit',
    ]];

    Livewire::test(CreateSalesOrder::class)
        ->fillForm([
            'partner_id' => $fixture['customer']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'order_date' => now()->toDateString(),
            'payment_mode' => 'cash',
            'payment_method' => 'bank',
            'salesOrderItems' => $items,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
        ])
        ->call('create')
        ->assertHasFormErrors(['bank_id' => 'required']);

    Livewire::test(CreateSalesOrder::class)
        ->fillForm([
            'partner_id' => $fixture['customer']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'order_date' => now()->toDateString(),
            'payment_mode' => 'credit',
            'has_initial_payment' => true,
            'initial_payment_amount' => 50,
            'initial_payment_method' => 'cheque',
            'salesOrderItems' => $items,
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
        ])
        ->call('create')
        ->assertHasFormErrors(['initial_payment_bank_id' => 'required']);
});

test('paid-now orders cannot receive a relation-manager payment', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Finance,
    ]));

    $fixture = createSalesOrderPaymentFixture();
    $order = SalesOrder::create([
        'warehouse_id' => $fixture['warehouse']->id,
        'partner_id' => $fixture['customer']->id,
        'order_date' => now(),
        'payment_mode' => 'cash',
        'payment_method' => 'cash',
        'total' => 100,
        'status' => SalesOrder::STATUS_COMPLETED,
    ]);

    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => ViewSalesOrder::class,
    ])->assertTableActionHidden('create');
});

test('later bank payments complete submitted credit orders', function (): void {
    $fixture = createSalesOrderPaymentFixture();
    $bank = createSalesOrderPaymentBank('Later Payment Bank');

    $order = SalesOrder::create([
        'warehouse_id' => $fixture['warehouse']->id,
        'partner_id' => $fixture['customer']->id,
        'order_date' => now(),
        'payment_mode' => 'credit',
        'subtotal' => 100,
        'total' => 100,
        'status' => SalesOrder::STATUS_DRAFT,
    ]);
    createSalesOrderPaymentItem($order, $fixture['item']);
    $order->update(['status' => SalesOrder::STATUS_SUBMITTED]);

    app(SalesOrderPaymentService::class)->processMultiplePayments($order, [[
        'amount' => 100,
        'method' => 'bank',
        'bank_id' => $bank->id,
        'reference' => 'LATER-001',
    ]]);

    expect($order->fresh()->status)->toBe(SalesOrder::STATUS_COMPLETED)
        ->and((float) $bank->fresh()->current_balance)->toBe(1100.0)
        ->and((float) $order->fresh()->balance)->toBe(0.0);
});

test('credit order records its initial payment during creation', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('sales'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Sales,
    ]));

    $fixture = createSalesOrderPaymentFixture();
    $bank = createSalesOrderPaymentBank('Creation Payment Bank');

    Livewire::test(CreateSalesOrder::class)
        ->fillForm([
            'partner_id' => $fixture['customer']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'order_date' => now()->toDateString(),
            'payment_mode' => 'credit',
            'due_date' => now()->addDays(30)->toDateString(),
            'has_initial_payment' => true,
            'initial_payment_amount' => 40,
            'initial_payment_method' => 'bank',
            'initial_payment_bank_id' => $bank->id,
            'initial_payment_reference' => 'CREATION-001',
            'salesOrderItems' => [[
                'inventory_item_id' => $fixture['item']->id,
                'quantity' => 1,
                'unit_price' => 100,
                'total' => 100,
                'unit_label' => 'unit',
            ]],
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $order = SalesOrder::query()->sole();
    $payment = $order->payments()->sole();

    expect($order->status)->toBe(SalesOrder::STATUS_DEPOSIT_RECEIVED)
        ->and((float) $payment->amount)->toBe(40.0)
        ->and($payment->method)->toBe('bank')
        ->and($payment->bank_id)->toBe($bank->id)
        ->and($payment->reference)->toBe('CREATION-001')
        ->and((float) InventoryBalance::query()
            ->where('inventory_item_id', $fixture['item']->id)
            ->where('warehouse_id', $fixture['warehouse']->id)
            ->value('quantity_on_hand'))->toBe(10.0);
});

test('creating a paid-now bank order completes it through the checkout form', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('sales'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Sales,
    ]));

    $fixture = createSalesOrderPaymentFixture();
    $bank = createSalesOrderPaymentBank('Checkout Bank');

    Livewire::test(CreateSalesOrder::class)
        ->fillForm([
            'partner_id' => $fixture['customer']->id,
            'warehouse_id' => $fixture['warehouse']->id,
            'order_date' => now()->toDateString(),
            'payment_mode' => 'cash',
            'payment_method' => 'bank',
            'bank_id' => $bank->id,
            'payment_reference' => 'CHECKOUT-001',
            'salesOrderItems' => [[
                'inventory_item_id' => $fixture['item']->id,
                'quantity' => 1,
                'unit_price' => 100,
                'total' => 100,
                'unit_label' => 'unit',
            ]],
            'subtotal' => 100,
            'tax_amount' => 0,
            'total' => 100,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(SalesOrder::query()->sole()->status)->toBe(SalesOrder::STATUS_COMPLETED)
        ->and(Payment::query()->sole()->bank_id)->toBe($bank->id);
});

function createSalesOrderPaymentFixture(): array
{
    Setting::getSettings()->update(['vat_enabled' => false]);

    $warehouse = Warehouse::factory()->create();
    $customer = Partner::factory()->customer()->create();
    $item = InventoryItem::factory()->create([
        'type' => 'finished_good',
        'is_sellable' => true,
        'purchase_unit' => null,
        'price' => 100,
        'average_cost' => 50,
    ]);

    InventoryBalance::factory()->create([
        'inventory_item_id' => $item->id,
        'warehouse_id' => $warehouse->id,
        'quantity_on_hand' => 10,
    ]);

    return compact('warehouse', 'customer', 'item');
}

function createSalesOrderPaymentItem(
    SalesOrder $order,
    InventoryItem $item,
    float $quantity = 1,
    float $unitPrice = 100,
    float $total = 100,
): SalesOrderItem {
    return SalesOrderItem::create([
        'sales_order_id' => $order->id,
        'inventory_item_id' => $item->id,
        'quantity' => $quantity,
        'unit_price' => $unitPrice,
        'total' => $total,
    ]);
}

function createSalesOrderPaymentBank(string $name = 'Sales Bank'): Bank
{
    return Bank::create([
        'name' => $name,
        'code' => str($name)->upper()->replace(' ', '-')->toString(),
        'account_number' => fake()->unique()->numerify('##########'),
        'account_holder_name' => 'Packledge',
        'bank_name' => $name,
        'current_balance' => 1000,
        'status' => 'active',
    ]);
}
