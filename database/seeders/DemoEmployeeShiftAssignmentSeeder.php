<?php

namespace Database\Seeders;

use App\Models\Employee;
use App\Models\PayPolicy;
use App\Models\Shift;
use App\Services\Hr\ResolveAssignmentPattern;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

class DemoEmployeeShiftAssignmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            $shift = Shift::query()->where('start_time', '07:30:00')->where('end_time', '16:30:00')->firstOrFail();
            $days = collect(range(0, 6))->map(fn (int $weekday): array => [
                'day_of_week' => $weekday,
                'shift_ids' => $weekday === 0 ? [] : [$shift->id],
            ])->all();
            $schedule = app(ResolveAssignmentPattern::class)->handle($days);

            foreach (Employee::query()->whereDoesntHave('workAssignments')->orderBy('id')->get() as $employee) {
                $from = $employee->hire_date->toDateString();
                $policy = PayPolicy::forDate($from) ?? throw new LogicException('No payroll policy covers the employee hire date.');
                $employee->workAssignments()->create([
                    'work_schedule_id' => $schedule->id,
                    'pay_policy_id' => $policy->id,
                    'effective_from' => $from,
                    'reason' => 'Demo shift assignment',
                ]);
            }
        });
    }
}
