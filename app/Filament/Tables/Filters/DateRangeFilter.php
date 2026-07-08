<?php

namespace App\Filament\Tables\Filters;

use Illuminate\Database\Eloquent\Builder;
use Malzariey\FilamentDaterangepickerFilter\Filters\DateRangeFilter as MalzarieyDateRangeFilter;

class DateRangeFilter
{
    public static function make(string $name, string $column, ?string $label = null, ?string $endColumn = null): MalzarieyDateRangeFilter
    {
        $endColumn ??= $column;

        return MalzarieyDateRangeFilter::make($name)
            ->label($label ?? str($name)->replace('_', ' ')->headline()->toString())
            ->useColumn($column)
            ->format('Y-m-d')
            ->disableRanges()
            ->alwaysShowCalendar()
            ->autoApply()
            ->modifyQueryUsing(fn (Builder $query, $startDate, $endDate): Builder => $query
                ->when($startDate, fn (Builder $query): Builder => $query->whereDate($column, '>=', $startDate))
                ->when($endDate, fn (Builder $query): Builder => $query->whereDate($endColumn, '<=', $endDate)));
    }

    public static function makeForRelation(string $name, string $relation, string $column, ?string $label = null, ?string $endColumn = null): MalzarieyDateRangeFilter
    {
        $endColumn ??= $column;

        return MalzarieyDateRangeFilter::make($name)
            ->label($label ?? str($name)->replace('_', ' ')->headline()->toString())
            ->useColumn($column)
            ->format('Y-m-d')
            ->disableRanges()
            ->alwaysShowCalendar()
            ->autoApply()
            ->modifyQueryUsing(fn (Builder $query, $startDate, $endDate): Builder => $query
                ->whereHas($relation, fn (Builder $query): Builder => $query
                    ->when($startDate, fn (Builder $query): Builder => $query->whereDate($column, '>=', $startDate))
                    ->when($endDate, fn (Builder $query): Builder => $query->whereDate($endColumn, '<=', $endDate))));
    }
}
