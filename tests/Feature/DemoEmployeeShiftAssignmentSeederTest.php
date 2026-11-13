<?php

use App\Models\Employee;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use App\Models\Shift;
use App\Models\WorkSchedule;
use Database\Seeders\DemoEmployeeShiftAssignmentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('assigns one repeating demo shift per employee from their hire date without duplicating existing assignments', function (): void {
    $policy = PayPolicy::factory()->create(['effective_from' => null]);
    $shift = Shift::factory()->create(['start_time' => '07:30:00', 'end_time' => '16:30:00', 'break_start' => null, 'break_end' => null, 'break_minutes' => 0]);
    $employees = Employee::factory()->count(3)->create();
    $this->seed(DemoEmployeeShiftAssignmentSeeder::class);
    $this->seed(DemoEmployeeShiftAssignmentSeeder::class);

    expect(EmployeeWorkAssignment::count())->toBe(3)->and(WorkSchedule::count())->toBe(1);
    foreach ($employees as $employee) {
        $assignment = $employee->workAssignments()->sole();
        expect($assignment->effective_from->toDateString())->toBe($employee->hire_date->toDateString())
            ->and($assignment->effective_to)->toBeNull()->and($assignment->pay_policy_id)->toBe($policy->id);
        foreach ($assignment->workSchedule->days as $day) {
            expect($day->shift_ids)->toBe($day->day_of_week === 0 ? [] : [$shift->id]);
        }
    }
});
