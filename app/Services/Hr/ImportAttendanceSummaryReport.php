<?php

namespace App\Services\Hr;

use App\Models\AttendanceImport;
use App\Models\AttendanceImportRow;
use App\Models\AttendancePeriodSummary;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ImportAttendanceSummaryReport
{
    /**
     * @param  array{period_start?: string, period_end?: string}  $options
     */
    public function handle(string $path, string $shiftType, ?int $userId = null, array $options = []): AttendanceImport
    {
        if (! in_array($shiftType, ['normal', 'night'], true)) {
            throw new RuntimeException('Attendance summary shift type must be normal or night.');
        }

        $rows = $this->readRows($path);
        $period = $this->resolvePeriod($rows, $options);

        return DB::transaction(function () use ($path, $shiftType, $userId, $rows, $period): AttendanceImport {
            $import = AttendanceImport::create([
                'file_name' => basename($path),
                'file_path' => $path,
                'report_type' => 'period_summary',
                'shift_type' => $shiftType,
                'period_start' => $period['start'],
                'period_end' => $period['end'],
                'status' => 'processing',
                'created_by' => $userId,
            ]);

            $successfulRows = 0;
            $failedRows = 0;

            foreach ($this->extractEmployeeRows($rows) as $rowNumber => $row) {
                $rawRow = $this->normalizeReportRow($row);
                $importRow = AttendanceImportRow::create([
                    'attendance_import_id' => $import->id,
                    'row_number' => $rowNumber + 1,
                    'raw_data' => $rawRow,
                    'status' => 'pending',
                ]);

                $employee = Employee::query()
                    ->where('attendance_device_id', $rawRow['attendance_device_id'])
                    ->orWhere('employee_id', $rawRow['attendance_device_id'])
                    ->first();

                if (! $employee) {
                    $failedRows++;
                    $importRow->update([
                        'status' => 'failed',
                        'error_message' => "No employee found for AC No {$rawRow['attendance_device_id']}.",
                    ]);

                    continue;
                }

                AttendancePeriodSummary::updateOrCreate(
                    [
                        'employee_id' => $employee->id,
                        'period_start' => $period['start'],
                        'period_end' => $period['end'],
                        'shift_type' => $shiftType,
                    ],
                    [
                        'attendance_import_id' => $import->id,
                        'work_days' => $rawRow['work_days'],
                        'actual_days' => $rawRow['actual_days'],
                        'absent_days' => $rawRow['absent_days'],
                        'late_minutes' => $rawRow['late_minutes'],
                        'early_minutes' => $rawRow['early_minutes'],
                        'lunch_minutes' => $rawRow['lunch_minutes'],
                        'overtime_minutes' => $rawRow['overtime_minutes'],
                        'holiday_days' => $rawRow['holiday_days'],
                        'leave_days' => $rawRow['leave_days'],
                        'dayoff_days' => $rawRow['dayoff_days'],
                        'work_time_hours' => $rawRow['work_time_hours'],
                        'work_percentage' => $rawRow['work_percentage'],
                        'raw_data' => $rawRow,
                    ],
                );

                $successfulRows++;
                $importRow->update(['status' => 'imported']);
            }

            $import->update([
                'status' => $failedRows > 0 ? 'completed_with_errors' : 'completed',
                'total_rows' => $successfulRows + $failedRows,
                'successful_rows' => $successfulRows,
                'failed_rows' => $failedRows,
                'processed_at' => now(),
            ]);

            return $import;
        });
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readRows(string $path): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            throw new RuntimeException("Unable to open attendance report {$path}.");
        }

        $rows = [];

        try {
            while (($row = fgetcsv($handle)) !== false) {
                $rows[] = $row;
            }
        } finally {
            fclose($handle);
        }

        return $rows;
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows
     * @return array{start: string, end: string}
     */
    private function resolvePeriod(array $rows, array $options): array
    {
        if (isset($options['period_start'], $options['period_end'])) {
            return [
                'start' => CarbonImmutable::parse($options['period_start'])->toDateString(),
                'end' => CarbonImmutable::parse($options['period_end'])->toDateString(),
            ];
        }

        foreach ($rows as $row) {
            $line = implode(' ', array_filter($row));

            if (preg_match('/Date Range\s*:-\s*(\d{2}\/\d{2}\/\d{4})\s*-\s*(\d{2}\/\d{2}\/\d{4})/i', $line, $matches)) {
                return [
                    'start' => CarbonImmutable::createFromFormat('d/m/Y', $matches[1])->toDateString(),
                    'end' => CarbonImmutable::createFromFormat('d/m/Y', $matches[2])->toDateString(),
                ];
            }
        }

        throw new RuntimeException('Attendance report period could not be detected.');
    }

    /**
     * @param  array<int, array<int, string|null>>  $rows
     * @return array<int, array<int, string|null>>
     */
    private function extractEmployeeRows(array $rows): array
    {
        return Arr::where($rows, function (array $row): bool {
            $serial = trim((string) ($row[0] ?? ''));
            $acNo = trim((string) ($row[2] ?? ''));

            return ctype_digit($serial) && $acNo !== '' && strcasecmp($acNo, 'AC No') !== 0;
        });
    }

    /**
     * @param  array<int, string|null>  $row
     * @return array<string, mixed>
     */
    private function normalizeReportRow(array $row): array
    {
        return [
            'serial_number' => trim((string) $row[0]),
            'full_name' => trim((string) $row[1]),
            'attendance_device_id' => trim((string) $row[2]),
            'department' => trim((string) $row[3]),
            'division' => trim((string) $row[4]),
            'work_days' => $this->decimal($row[6] ?? null),
            'actual_days' => $this->decimal($row[7] ?? null),
            'absent_days' => $this->decimal($row[8] ?? null),
            'late_minutes' => $this->minutes($row[9] ?? null),
            'early_minutes' => $this->minutes($row[10] ?? null),
            'lunch_minutes' => $this->minutes($row[11] ?? null),
            'overtime_minutes' => $this->durationToMinutes($row[12] ?? null),
            'holiday_days' => $this->decimal($row[13] ?? null),
            'leave_days' => $this->decimal($row[14] ?? null),
            'dayoff_days' => $this->decimal($row[20] ?? null),
            'work_time_hours' => $this->decimal($row[22] ?? null),
            'work_percentage' => $this->decimal($row[23] ?? null),
        ];
    }

    private function decimal(mixed $value): float
    {
        return (float) trim((string) $value);
    }

    private function minutes(mixed $value): int
    {
        return (int) round($this->decimal($value));
    }

    private function durationToMinutes(mixed $value): int
    {
        $value = trim((string) $value);

        if (! str_contains($value, ':')) {
            return $this->minutes($value);
        }

        [$hours, $minutes] = array_map('intval', explode(':', $value, 2));

        return ($hours * 60) + $minutes;
    }
}
