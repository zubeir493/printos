<?php

use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Models\Shift;
use App\Services\Hr\ImportAttendanceSegmentCsv;
use Database\Seeders\AttendanceDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('attendance demo seeder matches uploaded attendance csv employees', function (): void {
    $this->seed([
        AttendanceDemoSeeder::class,
        AttendanceDemoSeeder::class,
    ]);

    expect(Employee::query()->whereIn('attendance_device_id', ['22', '96', '3', '9'])->count())->toBe(4);

    $path = storage_path('app/attendance-demo-upload.csv');

    file_put_contents($path, implode("\n", [
        'FP No,Emp Code,FullName,Date,Schedule,On Duty,Off Duty,Clock In,Clock Out,Late(M),Early(M),Status,OT,OT Hrs,OT In,OT Out,Exception,Count,M-In,M-Out,In Day(Hr),mod',
        ',,,,,,,,,,,,,,,,,,,,,',
        '22,I-1,Abas Seman mohammed,05-02-26,Morning(Shift),8:00 AM,12:30 AM,7:56 AM,5:06 PM,,,,Y,3:36,,,,0.5,1,1,9:09,0',
        '96,I-121,Abrar Kiyar Kemal,05-11-26,Morning(Shift),8:00 AM,12:30 AM,8:05 AM,12:32 AM,6,,Late,,,,,Late,0.5,1,1,4:26,0',
        '3,I-18,Abdulkerim Abdulmelik Amdega,05-04-26,Morning(Shift),8:00 AM,12:30 AM,,,,,Absent,,,,,,0.5,1,1,,0',
        '9,I-32,Abdulhalim Hassen abubeker,05-04-26,1-9 night,7:00 PM,3:00 AM,6:55 PM,,,240,Early,,,,,Early,1,1,1,,0',
    ]));

    $result = app(ImportAttendanceSegmentCsv::class)->handle($path);

    expect($result['import']->successful_rows)->toBe(4)
        ->and($result['import']->failed_rows)->toBe(0)
        ->and(AttendanceSegment::query()->count())->toBe(4)
        ->and(Shift::query()->whereIn('name', ['Morning(Shift)', '1-9 night'])->count())->toBe(2);
});
