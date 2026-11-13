<?php

use App\Filament\Pages\Settings;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\RelationManagers\WorkAssignmentsRelationManager;
use App\Filament\Resources\PayPolicies\Pages\ManagePayPolicies;
use App\Filament\Resources\PayPolicies\PayPolicyResource;
use App\Filament\Resources\PayrollOvertimeEntries\Pages\ManagePayrollOvertimeEntries;
use App\Filament\Resources\PayrollRuns\Pages\EditPayrollRun;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Filament\Resources\WorkSchedules\Pages\ManageWorkSchedules;
use App\Filament\Resources\WorkSchedules\WorkScheduleResource;
use App\Models\Employee;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use App\Models\PayrollRun;
use App\Models\Shift;
use App\Models\User;
use App\Services\Hr\CalculatePayrollRun;
use App\Services\Hr\ResolveAssignmentPattern;
use App\Services\Hr\SavePayrollPolicy;
use App\UserRole;
use Database\Seeders\HrWorkSetupSeeder;
use Database\Seeders\PayrollTaxRuleSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    $this->seed(HrWorkSetupSeeder::class);
});

it('redirects retired setup pages and renders overtime in its panel', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    Livewire::test(ManageWorkSchedules::class)->assertRedirect(ShiftResource::getUrl());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ManagePayPolicies::class)->assertRedirect(Settings::getUrl(parameters: ['section' => 'payroll']));
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    Livewire::test(ManagePayrollOvertimeEntries::class)->assertSuccessful()->assertSee('Generate from attendance');
});

it('revises company rules through finance settings', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    Livewire::test(Settings::class)->fillForm(['pay_rules.standard_daily_minutes' => 7])->call('save')->assertHasNoFormErrors();
    expect(PayPolicy::latest('id')->firstOrFail()->standard_daily_minutes)->toBe(420);
});

it('prevents HR from changing financial policy rules', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    expect(Settings::canAccess())->toBeFalse();
});

it('versions company rules while preserving earlier assignment history', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create(['effective_from' => today()->subMonth()->toDateString()]);
    $policy = $assignment->payPolicy;
    $revision = app(SavePayrollPolicy::class)->handle(['day_overtime_multiplier' => 2], today()->toDateString());
    expect($assignment->fresh()->pay_policy_id)->toBe($policy->id)
        ->and((float) $revision->day_overtime_multiplier)->toBe(2.0)
        ->and((float) $policy->fresh()->day_overtime_multiplier)->toBe(1.5)
        ->and($revision->standard_daily_minutes)->toBe(480);
    $next = app(SavePayrollPolicy::class)->handle(['day_overtime_multiplier' => 2.25], today()->toDateString());
    expect($next->version)->toBe($revision->version + 1);
});

it('reuses identical repeating weeks without copying employee assignments', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $schedule = $assignment->workSchedule->load('days.shift', 'days.additionalShifts');
    $days = $schedule->days->map(fn ($day): array => ['day_of_week' => $day->day_of_week, 'shift_ids' => $day->shift_ids])->all();
    $resolved = app(ResolveAssignmentPattern::class)->handle($days, $schedule->id);
    expect($resolved->id)->toBe($schedule->id)->and($resolved->assignments()->count())->toBe(1);
});

it('keeps payroll and work setup resources in the flat app navigation', function (): void {
    expect(PayPolicyResource::getNavigationGroup())->toBeNull()
        ->and(WorkScheduleResource::getNavigationGroup())->toBeNull()
        ->and(ShiftResource::getNavigationGroup())->toBeNull();
});

it('renders dated assignments and revises salary allowance and pension together', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    $employee = Employee::factory()->create();
    EmployeeWorkAssignment::factory()->create(['employee_id' => $employee->id]);
    Livewire::test(WorkAssignmentsRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => EditEmployee::class])->assertSuccessful()->assertSee('Shifts');
    Livewire::test(EditEmployee::class, ['record' => $employee->id])->callAction('reviseSalary', [
        'basic_salary' => 15000, 'transport_allowance' => 800, 'pension_enabled' => false,
        'effective_date' => today()->toDateString(), 'reason' => 'Revised employment terms',
    ])->assertHasNoActionErrors();
    expect((float) $employee->fresh()->transport_allowance)->toBe(800.0)
        ->and($employee->fresh()->pension_enabled)->toBeFalse();
});

it('ignores forged calculated amounts while saving an employee payroll adjustment', function (): void {
    $this->seed(PayrollTaxRuleSeeder::class);
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $employee = Employee::factory()->create();
    EmployeeWorkAssignment::factory()->create(['employee_id' => $employee->id]);
    $run = PayrollRun::factory()->create();
    app(CalculatePayrollRun::class)->handle($run, [$employee->id]);
    $row = $run->employees()->firstOrFail();
    $salary = $row->basic_salary;
    Livewire::test(EditPayrollRun::class, ['record' => $run->id])
        ->call('showPayrollDetails', $row->id)
        ->set('detailForm.basic_salary', '999999')->set('detailForm.overtime_amount', '99999')
        ->set('detailForm.loan', '99999')->set('detailForm.bonus', '100')
        ->call('savePayrollDetails')->assertHasNoErrors();
    expect($row->fresh()->basic_salary)->toBe($salary)
        ->and((float) $row->fresh()->overtime_amount)->toBe(0.0)
        ->and((float) $row->fresh()->loan)->toBe(0.0)
        ->and((float) $row->fresh()->bonus)->toBe(100.0);
});

it('creates a complete weekly schedule and assigns it through the employee workspace', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    $shift = Shift::factory()->create();
    $days = collect([1, 2, 3, 4, 5, 6, 0])->map(fn (int $day): array => ['day_of_week' => $day, 'shift_ids' => $day === 3 ? [] : [$shift->id]])->all();
    $schedule = app(ResolveAssignmentPattern::class)->handle($days);
    $employee = Employee::factory()->create();
    $policy = PayPolicy::firstOrFail();
    $assignmentForm = Livewire::test(WorkAssignmentsRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => EditEmployee::class])->mountAction(TestAction::make('create')->table());
    $assignmentDays = collect($assignmentForm->get('mountedActions.0.data.days'))->map(fn (array $day): array => [...$day, 'shift_ids' => (int) $day['day_of_week'] === 3 ? [] : [$shift->id]])->all();
    $assignmentForm->set('mountedActions.0.data.days', $assignmentDays)->fillForm(['effective_from' => $employee->hire_date->toDateString(), 'reason' => 'Agreed work schedule'])->callMountedAction()
        ->assertHasNoActionErrors();
    expect($employee->workAssignments()->firstOrFail()->workSchedule->days->pluck('shift_id')->all())->toBe($schedule->days->pluck('shift_id')->all())
        ->and($schedule->days()->count())->toBe(7);
});
