<?php

use App\Filament\Design\Widgets\ArtworkPipelineChart;
use App\Filament\Design\Widgets\DesignSLAStats;
use App\Filament\Production\Widgets\FloorEfficiencyStats;
use App\Filament\Retail\Widgets\RetailCounterStats;
use App\Filament\Widgets\AdminHealthStats;
use App\Filament\Widgets\BankBalancesChart;
use App\Models\Artwork;
use App\Models\Bank;
use App\Models\JobOrder;
use App\Models\JobOrderTask;
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

test('artwork pipeline separates missing uploads from approval stages and uses doughnut chart', function () {
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

    $widget = new ArtworkPipelineChart;
    $data = invokeWidgetMethod($widget, 'getData');

    expect($data['datasets'][0]['data'])->toBe([1, 1, 1]);
    expect($data['labels'])->toBe([
        'Waiting Upload (1)',
        'Awaiting Approval (1)',
        'Approved (1)',
    ]);
    expect(invokeWidgetMethod($widget, 'getType'))->toBe('doughnut');
});

test('bank balances widget reports active bank balances and is registered on admin and finance panels', function () {
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

    $widget = new BankBalancesChart;
    $data = invokeWidgetMethod($widget, 'getData');

    expect($widget->getHeading())->toContain('4.00K');
    expect($data['datasets'][0]['data'])->toBe([2500.0, 1500.0]);
    expect($data['labels'][0])->toContain('Savings');
    expect($data['labels'][1])->toContain('Main');

    expect(file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php')))->toContain(BankBalancesChart::class);
    expect(file_get_contents(app_path('Providers/Filament/FinancePanelProvider.php')))->toContain(BankBalancesChart::class);
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
