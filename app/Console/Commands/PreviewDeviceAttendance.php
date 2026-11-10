<?php

namespace App\Console\Commands;

use App\Models\Employee;
use App\Services\Hr\ReadZktecoAttendance;
use Illuminate\Console\Command;
use Throwable;

class PreviewDeviceAttendance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'attendance:device-preview {--limit=10 : Maximum sample rows to show} {--users : Compare configured AC No./FP No. values with enrolled device users}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Read device punches and preview exact employee mapping without storing or changing anything';

    /**
     * Execute the console command.
     */
    public function handle(ReadZktecoAttendance $reader): int
    {
        try {
            $rows = $reader->handle();
            $employees = Employee::query()->pluck('id', 'attendance_device_id');
            $unmatched = collect($rows)->pluck('user_id')->unique()->filter(fn (string $id): bool => ! isset($employees[$id]));
            $this->info(count($rows).' punches read. '.$unmatched->count().' unmatched device user IDs.');
            $this->line('Device date range: '.(collect($rows)->min('timestamp') ?? 'None').' to '.(collect($rows)->max('timestamp') ?? 'None'));
            $this->line('Direction codes: '.collect($rows)->countBy('raw_direction')->map(fn (int $count, int $code): string => $code.' = '.$count)->implode(', '));
            $this->line('Match user_id (employee number) to attendance_device_id / FP No; uid is only an internal device slot.');
            $this->table(['Device user_id / FP No', 'Internal uid', 'Device time', 'Direction', 'ERP employee ID'],
                collect($rows)->sortByDesc('timestamp')->take(max(1, (int) $this->option('limit')))->map(fn (array $row): array => [
                    $row['user_id'], $row['uid'] ?? '', $row['timestamp'], $row['raw_direction'], $employees[$row['user_id']] ?? 'UNMATCHED',
                ])->all());

            if ($this->option('users')) {
                $users = collect($reader->users())->keyBy('user_id');
                $this->table(['ERP Employee ID (internal)', 'AC No./FP No.', 'ERP name', 'Device name'],
                    Employee::whereNotNull('attendance_device_id')->get()->map(fn (Employee $employee): array => [
                        $employee->employee_id, $employee->attendance_device_id, $employee->full_name,
                        $users->get($employee->attendance_device_id)['name'] ?? 'NOT ENROLLED',
                    ])->all());
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
