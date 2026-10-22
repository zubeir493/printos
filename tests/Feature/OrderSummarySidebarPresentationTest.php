<?php

use App\Filament\Resources\JobOrders\Pages\CreateJobOrder;
use App\Filament\Resources\Proformas\Pages\CreateProforma;
use App\Filament\Resources\PurchaseOrders\Pages\CreatePurchaseOrder;
use App\Filament\Resources\SalesOrders\Pages\CreateSalesOrder;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Filament\Forms\Components\Placeholder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('uses placeholder summary rows on order form sidebars', function (string $page): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Livewire::test($page)
        ->assertSchemaComponentExists('summary_subtotal', 'form', fn ($component): bool => $component instanceof Placeholder)
        ->assertSchemaComponentExists('summary_tax_amount', 'form', fn ($component): bool => $component instanceof Placeholder)
        ->assertSchemaComponentExists('summary_total', 'form', fn ($component): bool => $component instanceof Placeholder)
        ->assertFormFieldExists('subtotal')
        ->assertFormFieldExists('tax_amount')
        ->assertFormFieldExists('total');
})->with([
    'job order form' => [CreateJobOrder::class],
    'proforma form' => [CreateProforma::class],
    'purchase order form' => [CreatePurchaseOrder::class],
    'sales order form' => [CreateSalesOrder::class],
]);
