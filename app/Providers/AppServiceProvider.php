<?php

namespace App\Providers;

use App\Filament\Resources\AttendanceSegments\Pages\ManageAttendanceSegments;
use App\Filament\Resources\Payments\Pages\ListPayments;
use App\Filament\Support\RelationManagerToolbarTabs;
use App\Filament\Support\TableBadgeFormatter;
use App\Livewire\ExceptionHandlerHook;
use App\Models\Artwork;
use App\Models\BankTransfer;
use App\Models\Dispatch;
use App\Models\Employee;
use App\Models\InventoryBalance;
use App\Models\InventoryItem;
use App\Models\JobOrderTask;
use App\Models\MaterialRequest;
use App\Models\Payment;
use App\Models\PayrollRunEmployee;
use App\Models\ProductionPlan;
use App\Models\ProductionReport;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use App\Models\SalesOrder;
use App\Models\StockMovement;
use App\Models\TextFile;
use App\Observers\ArtworkObserver;
use App\Observers\BankTransferObserver;
use App\Observers\DispatchObserver;
use App\Observers\EmployeeObserver;
use App\Observers\InventoryBalanceObserver;
use App\Observers\InventoryItemObserver;
use App\Observers\JobOrderTaskObserver;
use App\Observers\MaterialRequestObserver;
use App\Observers\PaymentObserver;
use App\Observers\PayrollRunEmployeeObserver;
use App\Observers\ProductionPlanObserver;
use App\Observers\ProductionReportObserver;
use App\Observers\PurchaseOrderItemObserver;
use App\Observers\PurchaseOrderObserver;
use App\Observers\SalesOrderObserver;
use App\Observers\StockMovementObserver;
use App\Observers\TextFileObserver;
use App\Policies\PaymentPolicy;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Forms\Components\FileUpload;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Support\Facades\FilamentView;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\View\TablesRenderHook;
use Filament\View\PanelsRenderHook;
use Illuminate\Auth\Events\Login;
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
        // Keep strict model checks in local development so production reads remain resilient.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        } elseif ($this->app->environment('local')) {
            Model::shouldBeStrict();
        }

        ActionGroup::configureUsing(fn (ActionGroup $group) => $group->color('gray'));
        CreateAction::configureUsing(fn (CreateAction $action) => $action->createAnother(false));
        FilamentIcon::register([
            ActionsIconAlias::IMPORT_ACTION_GROUPED => Heroicon::ArrowDownTray,
            ActionsIconAlias::EXPORT_ACTION_GROUPED => Heroicon::ArrowUpTray,
        ]);
        FileUpload::configureUsing(fn (FileUpload $component) => $component->preventFilePathTampering());
        TextColumn::configureUsing(
            fn (TextColumn $column) => $column->formatStateUsing(
                fn (TextColumn $column, mixed $state): mixed => $column->isBadge()
                    ? TableBadgeFormatter::format($state)
                    : $state
            )
        );
        FilamentTimezone::set(config('app.timezone'));

        FilamentView::registerRenderHook(
            PanelsRenderHook::SCRIPTS_AFTER,
            fn (): string => Blade::render('@include(\'filament.webpush\')'),
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_SEARCH_AFTER,
            fn (): string => view('filament.tables.attendance-employee-selector')->render(),
            ManageAttendanceSegments::class,
        );

        FilamentView::registerRenderHook(
            TablesRenderHook::TOOLBAR_START,
            fn (): string => view('filament.tables.payments-toolbar-tabs')->render(),
            ListPayments::class,
        );

        foreach (RelationManagerToolbarTabs::renderHookScopes() as $relationManager) {
            FilamentView::registerRenderHook(
                TablesRenderHook::TOOLBAR_START,
                fn (array $scopes): string => view('filament.tables.relation-manager-toolbar-tabs', [
                    'activeManager' => $activeManager = $scopes[0] ?? null,
                    'tabs' => RelationManagerToolbarTabs::tabsForManager($activeManager),
                ])->render(),
                $relationManager,
            );
        }

        Event::listen(Login::class, function (Login $event): void {
            session()->flash('show_webpush_permission_prompt', true);
        });

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

        Payment::observe(PaymentObserver::class);
        BankTransfer::observe(BankTransferObserver::class);
        StockMovement::observe(StockMovementObserver::class);

        // Totals Automation
        JobOrderTask::observe(JobOrderTaskObserver::class);
        PurchaseOrderItem::observe(PurchaseOrderItemObserver::class);

        // Accounting Triggers
        PurchaseOrder::observe(PurchaseOrderObserver::class);
        SalesOrder::observe(SalesOrderObserver::class);

        Employee::observe(EmployeeObserver::class);
        PayrollRunEmployee::observe(PayrollRunEmployeeObserver::class);
        InventoryItem::observe(InventoryItemObserver::class);
        InventoryBalance::observe(InventoryBalanceObserver::class);
        Artwork::observe(ArtworkObserver::class);
        TextFile::observe(TextFileObserver::class);
        MaterialRequest::observe(MaterialRequestObserver::class);
        ProductionPlan::observe(ProductionPlanObserver::class);
        ProductionReport::observe(ProductionReportObserver::class);
        Dispatch::observe(DispatchObserver::class);
    }
}
