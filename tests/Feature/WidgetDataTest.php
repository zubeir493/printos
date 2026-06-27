<?php

use App\Filament\Design\Widgets\ArtworkPipelineWidget;
use App\Filament\Design\Widgets\DesignSLAStats;
use App\Filament\Finance\Widgets\CashflowActivityWidget;
use App\Filament\Production\Widgets\FloorEfficiencyStats;
use App\Filament\Retail\Widgets\RetailCounterStats;
use App\Filament\Widgets\AdminHealthStats;
use App\Filament\Widgets\BankBalanceMixWidget;
use App\Filament\Widgets\OperationalBottlenecksWidget;
use App\Models\Artwork;
use App\Models\Bank;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\PurchaseOrder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function invokeWidgetMethod(object $widget, string $method): mixed
{
    $reflection = new ReflectionMethod($widget, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($widget);
}

function widgetStatsByLabel(object $widget): array
{
    return collect(invokeWidgetMethod($widget, 'getStats'))
        ->mapWithKeys(fn ($stat): array => [$stat->getLabel() => $stat])
        ->all();
}

test('design stats use design task queue data instead of active job orders', function () {
    $activeJobOrder = JobOrder::factory()->create(['status' => 'active']);
    JobOrder::factory()->create(['status' => 'active']);

    JobOrderTask::create([
        'job_order_id' => $activeJobOrder->id,
        'name' => 'Needs artwork',
        'quantity' => 1,
        'task_cost' => 100,
        'status' => 'design',
    ]);

    JobOrderTask::create([
        'job_order_id' => $activeJobOrder->id,
        'name' => 'Already complete',
        'quantity' => 1,
        'task_cost' => 100,
        'status' => 'completed',
    ]);

    $stats = widgetStatsByLabel(new DesignSLAStats);

    expect($stats['Job Design Queue']->getValue())->toBe(1);
    expect($stats)->toHaveKey('Approved This Week');
    expect($stats)->not->toHaveKey('Pending Internal Approval');
});

test('artwork pipeline separates missing uploads from approval stages', function () {
    $jobOrder = JobOrder::factory()->create(['status' => 'active']);

    JobOrderTask::create([
        'job_order_id' => $jobOrder->id,
        'name' => 'Waiting upload',
        'quantity' => 1,
        'task_cost' => 100,
        'status' => 'design',
    ]);

    $pendingTask = JobOrderTask::create([
        'job_order_id' => $jobOrder->id,
        'name' => 'Pending approval',
        'quantity' => 1,
        'task_cost' => 100,
        'status' => 'design',
    ]);

    $approvedTask = JobOrderTask::create([
        'job_order_id' => $jobOrder->id,
        'name' => 'Approved artwork',
        'quantity' => 1,
        'task_cost' => 100,
        'status' => 'production',
    ]);

    Artwork::create([
        'job_order_task_id' => $pendingTask->id,
        'filename' => 'pending.pdf',
        'is_approved' => false,
    ]);

    Artwork::create([
        'job_order_task_id' => $approvedTask->id,
        'filename' => 'approved.pdf',
        'is_approved' => true,
    ]);

    $data = invokeWidgetMethod(new ArtworkPipelineWidget, 'getData')->toArray();

    expect(collect($data['items'])->pluck('value', 'label')->all())->toBe([
        'Waiting upload' => 1.0,
        'Awaiting approval' => 1.0,
        'Approved' => 1.0,
    ]);
});

test('bank balance mix widget reports active bank balances and is registered on admin and finance panels', function () {
    Bank::create([
        'name' => 'Main',
        'code' => 'MAIN',
        'account_number' => '1001',
        'account_holder_name' => 'PrintOS',
        'bank_name' => 'Awash',
        'current_balance' => 1500,
        'status' => 'active',
    ]);

    Bank::create([
        'name' => 'Savings',
        'code' => 'SAVE',
        'account_number' => '1002',
        'account_holder_name' => 'PrintOS',
        'bank_name' => 'CBE',
        'current_balance' => 2500,
        'status' => 'active',
    ]);

    Bank::create([
        'name' => 'Closed',
        'code' => 'CLOSED',
        'account_number' => '1003',
        'account_holder_name' => 'PrintOS',
        'bank_name' => 'Inactive Bank',
        'current_balance' => 9000,
        'status' => 'closed',
    ]);

    $data = invokeWidgetMethod(new BankBalanceMixWidget, 'getData')->toArray();
    $items = collect($data['items'])->pluck('value', 'label')->all();

    expect($items)->toBe([
        'Savings' => 2500.0,
        'Main' => 1500.0,
    ]);

    expect(file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php')))->toContain(BankBalanceMixWidget::class);
    expect(file_get_contents(app_path('Providers/Filament/FinancePanelProvider.php')))->toContain(BankBalanceMixWidget::class);
});

test('production queue stat counts task statuses that actually represent production queue work', function () {
    $jobOrder = JobOrder::factory()->create(['status' => 'active']);

    JobOrderTask::create([
        'job_order_id' => $jobOrder->id,
        'name' => 'Ready',
        'quantity' => 1,
        'task_cost' => 100,
        'status' => 'production',
    ]);

    JobOrderTask::create([
        'job_order_id' => $jobOrder->id,
        'name' => 'Legacy pending',
        'quantity' => 1,
        'task_cost' => 100,
        'status' => 'pending',
    ]);

    JobOrderTask::create([
        'job_order_id' => $jobOrder->id,
        'name' => 'Done',
        'quantity' => 1,
        'task_cost' => 100,
        'status' => 'completed',
    ]);

    $stats = widgetStatsByLabel(new FloorEfficiencyStats);

    expect($stats['Tasks in Queue']->getValue())->toBe(2);
});

test('admin and retail stats do not display synthetic or duplicated sparkline charts', function () {
    $adminStats = widgetStatsByLabel(new AdminHealthStats);
    $retailStats = widgetStatsByLabel(new RetailCounterStats);

    expect($adminStats['Cash Conversion Cycle']->getChart())->toBeNull();
    expect($adminStats['Delayed Dispatches']->getChart())->toBeNull();
    expect($adminStats['Inventory Shrinkage (30d)']->getChart())->toBeNull();
    expect($retailStats['Counter Sales Today']->getChart())->toBeNull();
});

test('operational bottlenecks widget counts open purchase orders using valid workflow statuses', function () {
    $supplier = Partner::create([
        'name' => 'Widget Supplier',
        'is_supplier' => true,
    ]);

    PurchaseOrder::create([
        'po_number' => 'PO-WIDGET-001',
        'partner_id' => $supplier->id,
        'order_date' => now(),
        'status' => 'draft',
        'subtotal' => 100,
        'total' => 100,
    ]);

    PurchaseOrder::create([
        'po_number' => 'PO-WIDGET-002',
        'partner_id' => $supplier->id,
        'order_date' => now(),
        'status' => 'approved',
        'subtotal' => 200,
        'total' => 200,
    ]);

    PurchaseOrder::create([
        'po_number' => 'PO-WIDGET-003',
        'partner_id' => $supplier->id,
        'order_date' => now(),
        'status' => 'received',
        'subtotal' => 300,
        'total' => 300,
    ]);

    $items = collect(invokeWidgetMethod(new OperationalBottlenecksWidget, 'getData')->toArray()['items'])
        ->pluck('value', 'label')
        ->all();

    expect($items['Open purchase orders'])->toBe(2.0);
});

test('cashflow activity heatmap counts daily inbound and outbound payments', function () {
    Payment::factory()->create([
        'payment_date' => today(),
        'amount' => 321,
        'direction' => 'inbound',
    ]);

    Payment::factory()->create([
        'payment_date' => today(),
        'amount' => 99,
        'direction' => 'outbound',
    ]);

    Payment::factory()->create([
        'payment_date' => today(),
        'amount' => 50,
        'direction' => 'outbound',
        'voided_at' => now(),
    ]);

    $data = invokeWidgetMethod(new CashflowActivityWidget, 'getData')->toArray();

    expect($data['entries'][today()->toDateString()])->toBe(420.0);
});

test('dashboard providers register filawidgets replacements', function () {
    $expectations = [
        'AdminPanelProvider.php' => ['ExecutivePulseWidget::class', 'OperationalBottlenecksWidget::class', 'BankBalanceMixWidget::class'],
        'FinancePanelProvider.php' => ['BankBalanceMixWidget::class', 'ReceivablesRiskWidget::class', 'InvoiceCollectionRateWidget::class', 'CashflowActivityWidget::class'],
        'SalesPanelProvider.php' => ['SalesMomentumWidget::class', 'SalesStageMixWidget::class', 'SalesCompletionRateWidget::class'],
        'ProductionPanelProvider.php' => ['MachineOutputPaceWidget::class', 'MachineLoadMixWidget::class', 'ProductionCompletionWidget::class'],
        'WarehousePanelProvider.php' => ['LogisticsActivityWidget::class', 'WipAvailabilityWidget::class', 'StockMovementPulseWidget::class'],
        'DesignPanelProvider.php' => ['ArtworkPipelineWidget::class', 'DesignQueueWidget::class', 'DesignCompletionRateWidget::class'],
        'HrPanelProvider.php' => ['WorkforceMixWidget::class', 'AttendanceActivityWidget::class', 'LeaveApprovalRateWidget::class'],
        'RetailPanelProvider.php' => ['CounterDemandWidget::class', 'RefillPressureWidget::class'],
        'OperationsPanelProvider.php' => ['JobPipelineMixWidget::class', 'PriorityJobValueWidget::class'],
    ];

    foreach ($expectations as $provider => $widgets) {
        $contents = file_get_contents(app_path("Providers/Filament/{$provider}"));

        foreach ($widgets as $widget) {
            expect($contents)->toContain($widget);
        }
    }
});

test('dashboard table widgets disable the global search bar', function () {
    $filamentPath = app_path('Filament');
    $missing = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($filamentPath)) as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        $contents = file_get_contents($file->getPathname());

        if (! str_contains($contents, 'TableWidget as BaseWidget')) {
            continue;
        }

        if (! str_contains($contents, '->searchable(false)')) {
            $missing[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($missing)->toBeEmpty();
});
