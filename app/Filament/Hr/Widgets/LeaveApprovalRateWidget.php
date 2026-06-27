<?php

namespace App\Filament\Hr\Widgets;

use App\Models\LeaveRequest;
use LaravelDaily\FilaWidgets\Data\CompletionRateWidgetData;
use LaravelDaily\FilaWidgets\Widgets\CompletionRateWidget;

class LeaveApprovalRateWidget extends CompletionRateWidget
{
    protected static ?int $sort = 4;

    protected ?string $widgetLabel = 'Leave Approval Rate';

    protected function getData(): CompletionRateWidgetData
    {
        $total = LeaveRequest::query()->count();
        $approved = LeaveRequest::query()->where('status', 'approved')->count();

        if ($total === 0) {
            return new CompletionRateWidgetData(0, isEmpty: true);
        }

        return new CompletionRateWidgetData(
            value: round(($approved / $total) * 100, 1),
            description: "{$approved} of {$total} leave requests approved",
        );
    }

    protected function getThresholds(): array
    {
        return [
            ['threshold' => 50, 'color' => 'danger', 'label' => 'Backlog'],
            ['threshold' => 80, 'color' => 'warning', 'label' => 'Pending'],
            ['threshold' => 100, 'color' => 'success', 'label' => 'Current'],
        ];
    }
}
