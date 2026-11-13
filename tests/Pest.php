<?php

use App\Models\Employee;
use App\Models\PayPolicy;
use App\Models\Shift;
use App\Models\WorkSchedule;
use App\Services\Dielines\Templates\BurgerBoxTemplate;
use App\Services\Dielines\Templates\CarryBagTemplate;
use App\Services\Dielines\Templates\ClamshellBurgerBoxTemplate;
use App\Services\Dielines\Templates\FlipTopBurgerBoxTemplate;
use App\Services\Dielines\Templates\FriesScoopTemplate;
use App\Services\Dielines\Templates\GableBoxTemplate;
use App\Services\Dielines\Templates\HangTabCartonTemplate;
use App\Services\Dielines\Templates\LockTabPizzaBoxTemplate;
use App\Services\Dielines\Templates\OpenFoodTrayTemplate;
use App\Services\Dielines\Templates\PizzaBoxTemplate;
use App\Services\Dielines\Templates\RolloverPizzaBoxTemplate;
use App\Services\Dielines\Templates\SandwichWedgeTemplate;
use App\Services\Dielines\Templates\SealEndCartonTemplate;
use App\Services\Dielines\Templates\VialInsertTemplate;
use Carbon\CarbonImmutable;
use Tests\TestCase;

dataset('packaging blueprints', [
    PizzaBoxTemplate::class,
    LockTabPizzaBoxTemplate::class,
    ClamshellBurgerBoxTemplate::class,
    RolloverPizzaBoxTemplate::class,
    FlipTopBurgerBoxTemplate::class,
    BurgerBoxTemplate::class,
    FriesScoopTemplate::class,
    SandwichWedgeTemplate::class,
    CarryBagTemplate::class,
    GableBoxTemplate::class,
    OpenFoodTrayTemplate::class,
    SealEndCartonTemplate::class,
    HangTabCartonTemplate::class,
    VialInsertTemplate::class,
]);

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend(TestCase::class)
 // ->use(Illuminate\Foundation\Testing\RefreshDatabase::class)
    ->in('Feature');

/*
|--------------------------------------------------------------------------
| Expectations
|--------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/

expect()->extend('toBeOne', function () {
    return $this->toBe(1);
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/

function something()
{
    // ..
}

function seedEmployeePayrollWork(Employee $employee): void
{
    $policy = PayPolicy::firstOrCreate(['code' => 'TEST-MONTHLY', 'version' => 1], ['name' => 'Payroll fixture policy']);
    $schedule = WorkSchedule::where('name', 'Payroll fixture schedule')->first();
    if (! $schedule) {
        $schedule = WorkSchedule::create(['name' => 'Payroll fixture schedule']);
        $shift = Shift::factory()->create();
        foreach (range(0, 6) as $weekday) {
            $schedule->days()->create(['day_of_week' => $weekday, 'shift_id' => $weekday === 0 ? null : $shift->id]);
        }
    }
    $employee->workAssignments()->create(['work_schedule_id' => $schedule->id, 'pay_policy_id' => $policy->id, 'effective_from' => $employee->hire_date, 'reason' => 'Payroll test fixture']);
}

function seedPayrollPeriodPunches(Employee $employee, string $from, string $to): void
{
    for ($date = CarbonImmutable::parse($from); $date->lte(CarbonImmutable::parse($to)); $date = $date->addDay()) {
        if ($date->isSunday() || $date->lt($employee->hire_date) || $employee->attendanceSegments()->whereDate('date', $date)->exists()) {
            continue;
        }
        $employee->attendanceSegments()->create(['date' => $date->toDateString(), 'fp_no' => $employee->attendance_device_id, 'clock_in' => '08:00:00', 'clock_out' => '17:00:00', 'status' => 'Present']);
    }
}
