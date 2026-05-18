<?php

namespace App\Providers;

use App\Livewire\ExceptionHandlerHook;
use App\Models\Artwork;
use App\Models\BankTransfer;
use App\Models\Employee;
use App\Models\InventoryItem;
use App\Models\JobOrderTask;
use App\Models\MaterialRequest;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Observers\ArtworkObserver;
use App\Observers\BankTransferObserver;
use App\Observers\EmployeeObserver;
use App\Observers\InventoryItemObserver;
use App\Observers\JobOrderTaskObserver;
use App\Observers\MaterialRequestObserver;
use App\Observers\PaymentAllocationObserver;
use App\Observers\PaymentObserver;
use App\Observers\PurchaseOrderItemObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\SalesOrderObserver;
use App\Observers\StockMovementObserver;
use App\Policies\PaymentAllocationPolicy;
use App\Policies\PaymentPolicy;
use Filament\Actions\CreateAction;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Facades\FilamentView;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Livewire\Livewire;
use NotificationChannels\WebPush\Events\NotificationFailed as WebPushNotificationFailed;
use NotificationChannels\WebPush\Events\NotificationSent as WebPushNotificationSent;

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
        // In production: force HTTPS for all generated URLs and prevent lazy loading.
        // In local: enable strict mode to surface mass-assignment and lazy-loading issues early.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
            Model::preventLazyLoading();
        } elseif ($this->app->environment('local')) {
            Model::shouldBeStrict();
        }

        CreateAction::configureUsing(fn (CreateAction $action) => $action->createAnother(false));
        FilamentTimezone::set(config('app.timezone'));

        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn (): string => Blade::render('@include(\'filament.webpush\')'),
        );

        Event::listen(WebPushNotificationSent::class, function (WebPushNotificationSent $event): void {
            Log::info('Web push notification sent', [
                'subscription_id' => $event->subscription->id,
                'subscribable_type' => $event->subscription->subscribable_type,
                'subscribable_id' => $event->subscription->subscribable_id,
                'endpoint' => str($event->subscription->endpoint)->limit(80)->toString(),
            ]);
        });

        Event::listen(WebPushNotificationFailed::class, function (WebPushNotificationFailed $event): void {
            Log::warning('Web push notification failed', [
                'subscription_id' => $event->subscription->id,
                'subscribable_type' => $event->subscription->subscribable_type,
                'subscribable_id' => $event->subscription->subscribable_id,
                'endpoint' => str($event->subscription->endpoint)->limit(80)->toString(),
                'reason' => $event->report->getReason(),
                'status_code' => $event->report->getResponse()?->getStatusCode(),
            ]);
        });

        Livewire::componentHook(ExceptionHandlerHook::class);

        Gate::policy(Payment::class, PaymentPolicy::class);
        Gate::policy(PaymentAllocation::class, PaymentAllocationPolicy::class);

        Payment::observe(PaymentObserver::class);
        BankTransfer::observe(BankTransferObserver::class);
        PaymentAllocation::observe(PaymentAllocationObserver::class);
        StockMovement::observe(StockMovementObserver::class);

        // Totals Automation
        JobOrderTask::observe(JobOrderTaskObserver::class);
        PurchaseOrderItem::observe(PurchaseOrderItemObserver::class);

        // Accounting Triggers
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        SalesOrder::observe(SalesOrderObserver::class);

        Employee::observe(EmployeeObserver::class);
        InventoryItem::observe(InventoryItemObserver::class);
        Artwork::observe(ArtworkObserver::class);
        MaterialRequest::observe(MaterialRequestObserver::class);
    }
}
