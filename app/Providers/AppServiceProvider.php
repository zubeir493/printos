<?php

namespace App\Providers;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Policies\PaymentPolicy;
use App\Policies\PaymentAllocationPolicy;
use Filament\Actions\CreateAction;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        CreateAction::configureUsing(fn (CreateAction $action) => $action->createAnother(false));

        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PaymentAllocation::class, PaymentAllocationPolicy::class);

        \App\Models\Payment::observe(\App\Observers\PaymentObserver::class);
        \App\Models\PaymentAllocation::observe(\App\Observers\PaymentAllocationObserver::class);
        \App\Models\StockMovement::observe(\App\Observers\StockMovementObserver::class);
        
        // Totals Automation
        \App\Models\JobOrderTask::observe(\App\Observers\JobOrderTaskObserver::class);
        \App\Models\PurchaseOrderItem::observe(\App\Observers\PurchaseOrderItemObserver::class);
        
        // Accounting Triggers
        \App\Models\PurchaseOrder::observe(\App\Observers\PurchaseOrderObserver::class);
        \App\Models\SalesOrder::observe(\App\Observers\SalesOrderObserver::class);
        
        \App\Models\Employee::observe(\App\Observers\EmployeeObserver::class);
        \App\Models\InventoryItem::observe(\App\Observers\InventoryItemObserver::class);
    }
}
