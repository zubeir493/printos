<?php

namespace App\Services\Hr;

use App\Models\AttendanceImport;
use App\Models\AttendanceImportRow;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\Shift;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportAttendanceSegmentCsv
{
    /**
     * @return array{import: AttendanceImport, period_start: string|null, period_end: string|null}
     */
    public function handle(string $path, ?int $userId = null): array
    {
        $rows = $this->readAssocRows($path);

        return DB::transaction(function () use ($path, $userId, $rows): array {
            $import = AttendanceImport::create([
                'file_name' => basename($path),
                'file_path' => $path,
                'report_type' => 'attendance_segments',
                'status' => 'processing',
                'created_by' => $userId,
            ]);

            $successfulRows = 0;
            $failedRows = 0;
            $dates = [];
            $affectedEmployeeIds = [];

            foreach ($rows as $rowNumber => $row) {
                if ($this->isBlankRow($row)) {
                    continue;
                }

                $importRow = AttendanceImportRow::create([
                    'attendance_import_id' => $import->id,
                    'row_number' => $rowNumber + 2,
                    'raw_data' => $row,
                    'status' => 'pending',
                ]);

                try {
                    $normalized = $this->normalizeRow($row);
                    $employee = Employee::query()
                        ->where('attendance_device_id', $normalized['fp_no'])
                        ->first();

                    if (! $employee) {
                        throw new RuntimeException("No employee found for FP No {$normalized['fp_no']}.");
                    }

                    $shift = $this->syncShift($normalized);
                    $normalized = $this->applyShiftDefaults($normalized, $shift);

                    $segment = AttendanceSegment::create([
                        ...$normalized,
                        'attendance_import_id' => $import->id,
                        'attendance_import_row_id' => $importRow->id,
                        'employee_id' => $employee->id,
                        'shift_id' => $shift?->id,
                        'raw_data' => $row,
                    ]);

                    $importRow->update([
                        'status' => 'imported',
                    ]);

                    $dates[] = $segment->date->toDateString();
                    $affectedEmployeeIds[] = $employee->id;
                    $successfulRows++;
                } catch (\Throwable $exception) {
                    $failedRows++;
                    $importRow->update([
                        'status' => 'failed',
                        'error_message' => $exception->getMessage(),
                    ]);
                }
            }

            $periodStart = $dates === [] ? null : min($dates);
            $periodEnd = $dates === [] ? null : max($dates);

            if ($periodStart && $periodEnd) {
                app(RebuildAttendanceDailySummaries::class)->forPeriod(array_unique($affectedEmployeeIds), $periodStart, $periodEnd);
            }

            $import->update([
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'status' => $failedRows > 0 ? 'completed_with_errors' : 'completed',
                'total_rows' => $successfulRows + $failedRows,
                'successful_rows' => $successfulRows,
                'failed_rows' => $failedRows,
                'processed_at' => now(),
            ]);

            return [
                'import' => $import->refresh(),
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
            ];
        });
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function readAssocRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException("Unable to open attendance CSV {$path}.");
        }

        try {
            $headers = fgetcsv($handle);

            if (! $headers) {
                throw new RuntimeException('Attendance CSV is empty.');
            }

            $headers = array_map(fn ($header): string => $this->cleanHeader((string) $header), $headers);
            $rows = [];

            while (($row = fgetcsv($handle)) !== false) {
                $assoc = [];

                foreach ($headers as $index => $header) {
                    $assoc[$header] = $row[$index] ?? null;
                }

                $rows[] = $assoc;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function cleanHeader(string $header): string
    {
        $header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;

        return trim($header);
    }

    /**
     * @param  array<string, string|null>  $row
     */
    private function isBlankRow(array $row): bool
    {
        return collect($row)->filter(fn ($value): bool => trim((string) $value) !== '')->isEmpty();
    }

    /**
     * @param  array<string, string|null>  $row
     * @return array<string, mixed>
     */
    private function normalizeRow(array $row): array
    {
        $fpNo = trim((string) ($row['FP No'] ?? ''));
        $date = trim((string) ($row['Date'] ?? ''));

        if ($fpNo === '') {
            throw new RuntimeException('FP No is required.');
        }

        if ($date === '') {
            throw new RuntimeException('Date is required.');
        }

        $scheduleName = trim((string) ($row['Schedule'] ?? ''));
        $status = trim((string) ($row['Status'] ?? '')) ?: null;
        $exception = trim((string) ($row['Exception'] ?? '')) ?: null;
        $workedMinutes = $this->durationToMinutes($row['In Day(Hr)'] ?? null);
        $scheduledStart = $this->parseTime($row['On Duty'] ?? null);
        $scheduledEnd = $this->parseTime($row['Off Duty'] ?? null);
        $clockIn = $this->parseTime($row['Clock In'] ?? null);
        $clockOut = $this->parseTime($row['Clock Out'] ?? null);

        if (($clockIn && ! $clockOut) || (! $clockIn && $clockOut)) {
            $clockIn ??= $scheduledStart;
            $clockOut ??= $scheduledEnd;
        }

        if ($workedMinutes === 0 && $clockIn && $clockOut) {
            $workedMinutes = $this->minutesBetween($clockIn, $clockOut);
        }

        return [
            'date' => $this->parseDate($date),
            'fp_no' => $fpNo,
            'schedule_name' => $scheduleName ?: null,
            'scheduled_start' => $scheduledStart,
            'scheduled_end' => $scheduledEnd,
            'clock_in' => $clockIn,
            'clock_out' => $clockOut,
            'late_minutes' => $this->integer($row['Late(M)'] ?? null),
            'early_minutes' => $this->integer($row['Early(M)'] ?? null),
            'worked_minutes' => $workedMinutes,
            'overtime_minutes' => $this->durationToMinutes($row['OT Hrs'] ?? null),
            'day_fraction' => $this->decimal($row['Count'] ?? null),
            'status' => $status,
            'exception' => $exception,
        ];
    }

    /**
     * @param  array<string, mixed>  $normalized
     */
    private function syncShift(array $normalized): ?Shift
    {
        if (! $normalized['schedule_name']) {
            return null;
        }

        $shift = Shift::query()->firstOrNew([
            'name' => $normalized['schedule_name'],
        ]);

        if (! $shift->exists) {
            $shift->fill([
                'start_time' => $normalized['scheduled_start'] ?? '00:00:00',
                'end_time' => $normalized['scheduled_end'] ?? '00:00:00',
                'expected_minutes' => $this->expectedShiftMinutes(
                    $normalized['scheduled_start'] ?? null,
                    $normalized['scheduled_end'] ?? null,
                ),
            ]);
        } else {
            $updates = [];

            if ($normalized['scheduled_start'] && $shift->start_time !== $normalized['scheduled_start']) {
                $updates['start_time'] = $normalized['scheduled_start'];
            }

            if ($normalized['scheduled_end'] && $shift->end_time !== $normalized['scheduled_end']) {
                $updates['end_time'] = $normalized['scheduled_end'];
            }

            if ($updates !== []) {
                $updates['expected_minutes'] = $this->expectedShiftMinutes(
                    $updates['start_time'] ?? $shift->start_time,
                    $updates['end_time'] ?? $shift->end_time,
                );

                $shift->fill($updates);
            }
        }

        $shift->save();

        return $shift;
    }

    /**
     * @param  array<string, mixed>  $normalized
     * @return array<string, mixed>
     */
    private function applyShiftDefaults(array $normalized, ?Shift $shift): array
    {
        if (! $shift) {
            return $normalized;
        }

        $normalized['scheduled_start'] ??= $shift->start_time;
        $normalized['scheduled_end'] ??= $shift->end_time;

        if (($normalized['clock_in'] && ! $normalized['clock_out']) || (! $normalized['clock_in'] && $normalized['clock_out'])) {
            $normalized['clock_in'] ??= $shift->start_time;
            $normalized['clock_out'] ??= $shift->end_time;
        }

        if ($normalized['worked_minutes'] === 0 && $normalized['clock_in'] && $normalized['clock_out']) {
            $normalized['worked_minutes'] = $this->minutesBetween($normalized['clock_in'], $normalized['clock_out']);
        }

        return $normalized;
    }

    private function expectedShiftMinutes(?string $start, ?string $end): int
    {
        if (! $start || ! $end) {
            return 480;
        }

        return max(1, $this->minutesBetween($start, $end));
    }

    private function parseDate(string $value): string
    {
        return CarbonImmutable::createFromFormat('m-d-y', $value)->toDateString();
    }

    private function parseTime(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return CarbonImmutable::createFromFormat('g:i A', $value)->format('H:i:s');
    }

    private function integer(mixed $value): int
    {
        return (int) round($this->decimal($value));
    }

    private function decimal(mixed $value): float
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function durationToMinutes(mixed $value): int
    {
        $value = trim((string) $value);

        if ($value === '') {
            return 0;
        }

        if (! str_contains($value, ':')) {
            return $this->integer($value);
        }

        [$hours, $minutes] = array_map('intval', explode(':', $value, 2));

        return ($hours * 60) + $minutes;
    }

    private function minutesBetween(string $start, string $end): int
    {
        $startTime = CarbonImmutable::createFromFormat('H:i:s', $start);
        $endTime = CarbonImmutable::createFromFormat('H:i:s', $end);

        if ($endTime->lt($startTime)) {
            $endTime = $endTime->addDay();
        }

        return (int) $startTime->diffInMinutes($endTime);
    }
}
