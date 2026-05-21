<?php

namespace App\Services\Hr;

use App\Models\AttendanceLog;
use App\Models\Employee;
use RuntimeException;

class RecordManualAttendanceLog
{
    public function handle(Employee $employee, string $punchedAt, string $type, string $reason, ?int $userId = null): AttendanceLog
    {
        if (trim($reason) === '') {
            throw new RuntimeException('Manual attendance entries require a reason.');
        }

        return AttendanceLog::create([
            'employee_id' => $employee->id,
            'punched_at' => $punchedAt,
            'type' => $type,
            'source' => 'manual',
            'reason' => $reason,
            'created_by' => $userId,
        ]);
    }
}
