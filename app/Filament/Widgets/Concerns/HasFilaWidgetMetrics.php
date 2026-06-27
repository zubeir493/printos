<?php

namespace App\Filament\Widgets\Concerns;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use LaravelDaily\FilaWidgets\Support\DateRangeFilter;
use LaravelDaily\FilaWidgets\Support\SparklineSeries;

trait HasFilaWidgetMetrics
{
    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    protected function currentPeriod(): array
    {
        return DateRangeFilter::fromFilter($this->getRangeFilter())->currentPeriod();
    }

    /**
     * @return array{current: array{0: CarbonImmutable, 1: CarbonImmutable}, previous: array{0: CarbonImmutable, 1: CarbonImmutable}}
     */
    protected function comparisonPeriods(): array
    {
        [$currentStart, $currentEnd] = $this->currentPeriod();
        $days = $currentStart->diffInDays($currentEnd) + 1;
        $previousEnd = $currentStart->subSecond();
        $previousStart = $currentStart->subDays($days);

        return [
            'current' => [$currentStart, $currentEnd],
            'previous' => [$previousStart, $previousEnd],
        ];
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $period
     */
    protected function countDuring(Builder $query, array $period, string $dateColumn = 'created_at'): float
    {
        return (float) (clone $query)->whereBetween($dateColumn, $period)->count();
    }

    /**
     * @param  array{0: CarbonImmutable, 1: CarbonImmutable}  $period
     */
    protected function sumDuring(Builder $query, array $period, string $column, string $dateColumn = 'created_at'): float
    {
        return (float) (clone $query)->whereBetween($dateColumn, $period)->sum($column);
    }

    protected function sparkline(Builder $query, string $aggregate, string $dateColumn = 'created_at', int $precision = 2): array
    {
        [$start, $end] = $this->currentPeriod();

        return SparklineSeries::daily($start, $end, $query, $aggregate, $dateColumn, $precision);
    }

    protected function heatmap(Builder $query, string $aggregate = 'COUNT(*)', string $dateColumn = 'created_at', int $precision = 2): array
    {
        [$start, $end] = $this->currentPeriod();

        return (clone $query)
            ->whereBetween($dateColumn, [$start, $end])
            ->selectRaw("DATE({$dateColumn}) as date, {$aggregate} as value")
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('value', 'date')
            ->map(fn ($value): float => round((float) $value, $precision))
            ->all();
    }
}
