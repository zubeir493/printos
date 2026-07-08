<?php

namespace App\Filament\Imports;

use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\Hr\RebuildAttendanceDailySummaries;
use Carbon\CarbonImmutable;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\ImportColumn;
use Filament\Actions\Imports\Importer;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Support\Number;
use Throwable;

class AttendanceSegmentImporter extends Importer
{
    protected static ?string $model = AttendanceSegment::class;

    public static function getColumns(): array
    {
        return [
            ImportColumn::make('date')
                ->example('2026-01-31')
                ->requiredMapping()
                ->castStateUsing(fn (?string $state): ?string => self::parseDate($state))
                ->rules(['required', 'date']),
            ImportColumn::make('fp_no')
                ->label('FP No')
                ->example('12')
                ->requiredMapping()
                ->rules(['required', 'max:255']),
            ImportColumn::make('schedule_name')
                ->label('Schedule')
                ->rules(['max:255']),
            ImportColumn::make('scheduled_start')
                ->label('On Duty')
                ->castStateUsing(fn (?string $state): ?string => self::parseTime($state)),
            ImportColumn::make('scheduled_end')
                ->label('Off Duty')
                ->castStateUsing(fn (?string $state): ?string => self::parseTime($state)),
            ImportColumn::make('clock_in')
                ->label('Clock In')
                ->castStateUsing(fn (?string $state): ?string => self::parseTime($state)),
            ImportColumn::make('clock_out')
                ->label('Clock Out')
                ->castStateUsing(fn (?string $state): ?string => self::parseTime($state)),
            ImportColumn::make('late_minutes')
                ->label('Late(M)')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): int => self::integer($state))
                ->rules(['required', 'integer']),
            ImportColumn::make('early_minutes')
                ->label('Early(M)')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): int => self::integer($state))
                ->rules(['required', 'integer']),
            ImportColumn::make('worked_minutes')
                ->label('In Day(Hr)')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): int => self::durationToMinutes($state))
                ->rules(['required', 'integer']),
            ImportColumn::make('overtime_minutes')
                ->label('OT Hrs')
                ->requiredMapping()
                ->castStateUsing(fn (mixed $state): int => self::durationToMinutes($state))
                ->rules(['required', 'integer']),
            ImportColumn::make('day_fraction')
                ->label('Count')
                ->requiredMapping()
                ->numeric()
                ->rules(['required', 'numeric']),
            ImportColumn::make('status')
                ->rules(['max:255']),
            ImportColumn::make('exception')
                ->rules(['max:255']),
            ImportColumn::make('correction_reason'),
        ];
    }

    public function resolveRecord(): AttendanceSegment
    {
        $employee = $this->employee();

        return AttendanceSegment::firstOrNew([
            'employee_id' => $employee->id,
            'date' => $this->data['date'],
            'fp_no' => $this->data['fp_no'],
        ]);
    }

    protected function afterFill(): void
    {
        $shift = $this->syncShift();

        $this->record->employee_id = $this->employee()->id;
        $this->record->shift_id = $shift?->id;
        $this->record->raw_data = $this->originalData;

        if ($shift) {
            $this->record->scheduled_start ??= $shift->start_time;
            $this->record->scheduled_end ??= $shift->end_time;
        }

        if (($this->record->clock_in && ! $this->record->clock_out) || (! $this->record->clock_in && $this->record->clock_out)) {
            $this->record->clock_in ??= $this->record->scheduled_start;
            $this->record->clock_out ??= $this->record->scheduled_end;
        }

        if ((int) $this->record->worked_minutes === 0 && $this->record->clock_in && $this->record->clock_out) {
            $this->record->worked_minutes = self::minutesBetween($this->record->clock_in, $this->record->clock_out);
        }
    }

    protected function afterSave(): void
    {
        app(RebuildAttendanceDailySummaries::class)->forSegments(collect([$this->record]));
    }

    public function getJobConnection(): ?string
    {
        return 'sync';
    }

    private function employee(): Employee
    {
        $fpNo = trim((string) ($this->data['fp_no'] ?? ''));

        $employee = Employee::query()
            ->where('attendance_device_id', $fpNo)
            ->first();

        if (! $employee) {
            throw new RowImportFailedException("No employee found for FP No {$fpNo}.");
        }

        return $employee;
    }

    private function syncShift(): ?Shift
    {
        $scheduleName = trim((string) ($this->data['schedule_name'] ?? ''));

        if ($scheduleName === '') {
            return null;
        }

        $shift = Shift::firstOrNew([
            'name' => $scheduleName,
        ]);

        $shift->fill([
            'start_time' => $this->data['scheduled_start'] ?? $shift->start_time ?? '00:00:00',
            'end_time' => $this->data['scheduled_end'] ?? $shift->end_time ?? '00:00:00',
        ]);

        $shift->expected_minutes = self::minutesBetween($shift->start_time, $shift->end_time);
        $shift->save();

        return $shift;
    }

    private static function parseDate(?string $state): ?string
    {
        if (blank($state)) {
            return null;
        }

        foreach (['Y-m-d', 'm-d-y', 'm/d/Y', 'm/d/y'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, trim($state))->toDateString();
            } catch (Throwable) {
            }
        }

        return CarbonImmutable::parse($state)->toDateString();
    }

    private static function parseTime(mixed $state): ?string
    {
        $state = trim((string) $state);

        if ($state === '') {
            return null;
        }

        foreach (['H:i:s', 'H:i', 'g:i A'] as $format) {
            try {
                return CarbonImmutable::createFromFormat($format, $state)->format('H:i:s');
            } catch (Throwable) {
            }
        }

        return CarbonImmutable::parse($state)->format('H:i:s');
    }

    private static function integer(mixed $state): int
    {
        return (int) round((float) $state);
    }

    private static function durationToMinutes(mixed $state): int
    {
        $state = trim((string) $state);

        if ($state === '') {
            return 0;
        }

        if (! str_contains($state, ':')) {
            return self::integer($state);
        }

        [$hours, $minutes] = array_map('intval', explode(':', $state, 2));

        return ($hours * 60) + $minutes;
    }

    private static function minutesBetween(string $start, string $end): int
    {
        $startTime = CarbonImmutable::createFromFormat('H:i:s', $start);
        $endTime = CarbonImmutable::createFromFormat('H:i:s', $end);

        if ($endTime->lt($startTime)) {
            $endTime = $endTime->addDay();
        }

        return max(1, (int) $startTime->diffInMinutes($endTime));
    }

    public static function getCompletedNotificationBody(Import $import): string
    {
        $body = 'Your attendance segment import has completed and '.Number::format($import->successful_rows).' '.str('row')->plural($import->successful_rows).' imported.';

        if ($failedRowsCount = $import->getFailedRowsCount()) {
            $body .= ' '.Number::format($failedRowsCount).' '.str('row')->plural($failedRowsCount).' failed to import.';
        }

        return $body;
    }
}
