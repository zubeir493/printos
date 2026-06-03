<?php

use App\Filament\Resources\JobOrders\Pages\CreateJobOrder;
use App\Filament\Resources\JobOrders\Pages\EditJobOrder;
use App\Filament\Resources\JobOrders\Pages\ViewJobOrder;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\EditPurchaseOrder;
use App\Filament\Resources\PurchaseOrders\Pages\ViewPurchaseOrder;
use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\EditSalesOrder;
use App\Filament\Resources\SalesOrders\Pages\ViewSalesOrder;
use App\Models\JobOrder;
use App\Models\Partner;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Warehouse;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('hides generated order number fields in the main order forms', function (string $page, string $field, string $panel): void {
    Filament::setCurrentPanel(Filament::getPanel($panel));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    $fields = Livewire::test($page)
        ->instance()
        ->form
        ->getFlatFields(withHidden: true);

    expect($fields)->not->toHaveKey($field);
})->with([
    'sales order' => [CreateSalesOrder::class, 'order_number', 'sales'],
    'job order' => [CreateJobOrder::class, 'job_order_number', 'operations'],
    'purchase order' => [CreatePurchaseOrder::class, 'po_number', 'operations'],
]);

it('uses order numbers as edit and view page titles', function (string $panel, object $record, string $editPage, string $viewPage, string $number): void {
    Filament::setCurrentPanel(Filament::getPanel($panel));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));

    expect(Livewire::test($editPage, ['record' => $record->id])->instance()->getTitle())
        ->toBe('Edit '.$number)
        ->and(Livewire::test($viewPage, ['record' => $record->id])->instance()->getTitle())
        ->toBe($number);
})->with([
    'sales order' => fn (): array => [
        'sales',
        SalesOrder::create([
            'order_number' => 'SO-TITLE-001',
            'warehouse_id' => Warehouse::factory()->create()->id,
            'partner_id' => Partner::factory()->create(['is_customer' => true])->id,
            'order_date' => now(),
            'due_date' => now()->addDays(30),
            'payment_mode' => 'cash',
            'subtotal' => 0,
            'tax_amount' => 0,
            'total' => 0,
            'status' => SalesOrder::STATUS_DRAFT,
        ]),
        EditSalesOrder::class,
        ViewSalesOrder::class,
        'SO-TITLE-001',
    ],
    'job order' => fn (): array => [
        'operations',
        JobOrder::factory()->create(['job_order_number' => 'JO-TITLE-001']),
        EditJobOrder::class,
        ViewJobOrder::class,
        'JO-TITLE-001',
    ],
    'purchase order' => fn (): array => [
        'operations',
        PurchaseOrder::factory()->create(['po_number' => 'PO-TITLE-001']),
        EditPurchaseOrder::class,
        ViewPurchaseOrder::class,
        'PO-TITLE-001',
    ],
]);
