<?php

namespace App\Filament\Exports;

use App\Models\AttendanceSegment;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

class AttendanceSegmentExporter extends Exporter
{
    use RunsExportsSynchronously;

    protected static ?string $model = AttendanceSegment::class;

    public static function getColumns(): array
    {
        return [
            ExportColumn::make('employee.full_name')
                ->label('Employee'),
            ExportColumn::make('date')
                ->label('Date'),
            ExportColumn::make('fp_no')
                ->label('FP No'),
            ExportColumn::make('schedule_name')
                ->label('Schedule'),
            ExportColumn::make('scheduled_start')
                ->label('On Duty'),
            ExportColumn::make('scheduled_end')
                ->label('Off Duty'),
            ExportColumn::make('clock_in')
                ->label('Clock In'),
            ExportColumn::make('clock_out')
                ->label('Clock Out'),
            ExportColumn::make('late_minutes')
                ->label('Late Minutes'),
            ExportColumn::make('early_minutes')
                ->label('Early Minutes'),
            ExportColumn::make('worked_minutes')
                ->label('Worked Minutes'),
            ExportColumn::make('overtime_minutes')
                ->label('Overtime Minutes'),
            ExportColumn::make('day_fraction')
                ->label('Day Fraction'),
            ExportColumn::make('status')
                ->label('Status'),
            ExportColumn::make('exception')
                ->label('Exception'),
            ExportColumn::make('correction_reason')
                ->label('Correction Reason'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        $body = 'Your attendance segment export has completed and '.Number::format($export->successful_rows).' '.str('row')->plural($export->successful_rows).' exported.';

        if ($failedRowsCount = $export->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to export.';
        }

        return $body;
    }
}
