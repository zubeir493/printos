<?php

namespace App\Filament\Design\Widgets;

use App\Filament\Widgets\Concerns\HasFilaWidgetMetrics;
use App\Models\Artwork;
use App\Models\JobOrderTask;
use LaravelDaily\FilaWidgets\Data\SparklineTableRowData;
use LaravelDaily\FilaWidgets\Data\SparklineTableWidgetData;
use LaravelDaily\FilaWidgets\Widgets\SparklineTableWidget;

class DesignQueueWidget extends SparklineTableWidget
{
    use HasFilaWidgetMetrics;

    protected static ?int $sort = 3;

    protected ?string $widgetLabel = 'Design Queue';

    protected string $widgetFormat = 'number';

    protected int $widgetPrecision = 0;

    protected function getData(): SparklineTableWidgetData
    {
        $periods = $this->comparisonPeriods();
        $designerId = auth()->id();
        $assigned = JobOrderTask::query()
            ->when($designerId, fn ($query) => $query->where('designer_id', $designerId))
            ->where('status', 'design');
        $overdue = JobOrderTask::query()
            ->join('job_orders', 'job_order_tasks.job_order_id', '=', 'job_orders.id')
            ->when($designerId, fn ($query) => $query->where('job_order_tasks.designer_id', $designerId))
            ->where('job_order_tasks.status', 'design')
            ->whereDate('job_orders.due_date', '<', today());
        $pendingUploads = JobOrderTask::query()
            ->whereNotIn('status', ['completed', 'cancelled'])
            ->whereDoesntHave('artworks');
        $pendingApproval = Artwork::query()->where('is_approved', false);

        return SparklineTableWidgetData::fromRows(
            new SparklineTableRowData('Assigned tasks', $this->countDuring($assigned, $periods['current']), $this->countDuring($assigned, $periods['previous']), $this->sparkline($assigned, 'COUNT(*)', precision: 0), 'number', 0),
            new SparklineTableRowData('Overdue tasks', $this->countDuring($overdue, $periods['current'], 'job_order_tasks.created_at'), $this->countDuring($overdue, $periods['previous'], 'job_order_tasks.created_at'), $this->sparkline($overdue, 'COUNT(*)', 'job_order_tasks.created_at', 0), 'number', 0, color: 'danger'),
            new SparklineTableRowData('Uploads pending', $this->countDuring($pendingUploads, $periods['current']), $this->countDuring($pendingUploads, $periods['previous']), $this->sparkline($pendingUploads, 'COUNT(*)', precision: 0), 'number', 0, color: 'warning'),
            new SparklineTableRowData('Approvals pending', $this->countDuring($pendingApproval, $periods['current']), $this->countDuring($pendingApproval, $periods['previous']), $this->sparkline($pendingApproval, 'COUNT(*)', precision: 0), 'number', 0),
        );
    }
}
