<?php

use App\Filament\Resources\Employees\Pages\ListEmployees;
use App\Models\Employee;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Hr\BulkAssignEmployeeShift;
use App\Services\Hr\ResolveEmployeeWork;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 13)->setTime(12, 0));
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    Filament::setCurrentPanel(Filament::getPanel('hr'));
    $this->policy = PayPolicy::factory()->create(['effective_from' => null]);
    $this->shift = Shift::factory()->create(['name' => 'Day', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'break_minutes' => 0, 'break_start' => null, 'break_end' => null]);
    $this->data = ['shift_id' => $this->shift->id, 'workdays' => [1, 2, 3, 4, 5, 6], 'effective_from' => '2026-11-13', 'reason' => 'Team shift change'];
});

it('assigns a recurring shift to selected employees from the bulk menu', function (): void {
    $employees = Employee::factory()->count(2)->create();
    $unselected = Employee::factory()->create();
    Livewire::test(ListEmployees::class)->selectTableRecords($employees->modelKeys())
        ->callAction(TestAction::make('assignShift')->table()->bulk(), $this->data)
        ->assertHasNoActionErrors()->assertNotified('Shift assigned to 2 employees');
    expect(EmployeeWorkAssignment::count())->toBe(2)->and($unselected->workAssignments()->count())->toBe(0);
    foreach ($employees as $employee) {
        $assignment = $employee->workAssignments()->sole();
        expect($assignment->effective_to)->toBeNull()->and($assignment->pay_policy_id)->toBe($this->policy->id);
        $resolver = app(ResolveEmployeeWork::class);
        expect($resolver->forDate($employee, '2026-11-14')['shifts']->modelKeys())->toBe([$this->shift->id])
            ->and($resolver->forDate($employee, '2026-11-15')['shifts'])->toHaveCount(0)
            ->and($resolver->forDate($employee, '2027-01-04')['shifts']->modelKeys())->toBe([$this->shift->id]);
    }
});

it('preserves earlier history and supports reassignment twice on the same start date', function (): void {
    $old = EmployeeWorkAssignment::factory()->create(['pay_policy_id' => $this->policy->id]);
    $employees = Employee::whereKey($old->employee_id)->get();
    app(BulkAssignEmployeeShift::class)->handle($employees, $this->data);
    expect($old->fresh()->effective_to->toDateString())->toBe('2026-11-12');
    $newShift = Shift::factory()->create(['start_time' => '08:30:00', 'end_time' => '17:30:00', 'break_start' => null, 'break_end' => null, 'break_minutes' => 0]);
    app(BulkAssignEmployeeShift::class)->handle($employees, [...$this->data, 'shift_id' => $newShift->id]);
    expect(EmployeeWorkAssignment::count())->toBe(2)
        ->and($old->fresh()->work_schedule_id)->toBe($old->work_schedule_id)
        ->and(app(ResolveEmployeeWork::class)->forDate($employees->first(), '2026-11-13')['shifts']->modelKeys())->toBe([$newShift->id]);
});

it('retains future pay policy boundaries when changing shifts', function (): void {
    $old = EmployeeWorkAssignment::factory()->create(['pay_policy_id' => $this->policy->id, 'effective_to' => '2026-11-30']);
    $futurePolicy = PayPolicy::factory()->create(['effective_from' => '2026-12-01']);
    $future = EmployeeWorkAssignment::factory()->create(['employee_id' => $old->employee_id, 'pay_policy_id' => $futurePolicy->id, 'work_schedule_id' => $old->work_schedule_id, 'effective_from' => '2026-12-01']);
    app(BulkAssignEmployeeShift::class)->handle(Employee::whereKey($old->employee_id)->get(), $this->data);
    expect($future->fresh()->pay_policy_id)->toBe($futurePolicy->id)
        ->and($future->fresh()->effective_from->toDateString())->toBe('2026-12-01')
        ->and(app(ResolveEmployeeWork::class)->forDate($old->employee, '2026-12-02')['shifts']->modelKeys())->toBe([$this->shift->id]);
    expect($old->employee->workAssignments()->whereDate('effective_from', '2026-11-13')->sole()->effective_to->toDateString())->toBe('2026-11-30');
});

it('rolls back the whole selection and shows the employee responsible for an invalid date', function (): void {
    $first = EmployeeWorkAssignment::factory()->create(['pay_policy_id' => $this->policy->id]);
    $futureHire = Employee::factory()->create(['hire_date' => '2026-12-01']);
    $count = WorkSchedule::count();
    Livewire::test(ListEmployees::class)->selectTableRecords([$first->employee_id, $futureHire->id])
        ->callAction(TestAction::make('assignShift')->table()->bulk(), $this->data)
        ->assertHasActionErrors(['effective_from'])->assertNotNotified();
    expect(EmployeeWorkAssignment::count())->toBe(1)->and($first->fresh()->effective_to)->toBeNull()->and(WorkSchedule::count())->toBe($count);
});

it('protects approved payroll and clears draft attendance review after a shift change', function (): void {
    $employee = Employee::factory()->create();
    $run = PayrollRun::factory()->create(['period_start' => '2026-11-01', 'period_end' => '2026-11-30', 'status' => 'draft', 'attendance_reviewed_at' => now(), 'attendance_reviewed_by' => auth()->id()]);
    PayrollRunEmployee::withoutEvents(fn () => $run->employees()->create(['employee_id' => $employee->id]));
    app(BulkAssignEmployeeShift::class)->handle(Employee::whereKey($employee->id)->get(), $this->data);
    expect($run->fresh()->attendance_reviewed_at)->toBeNull();
    $run->update(['status' => 'approved']);
    expect(fn () => app(BulkAssignEmployeeShift::class)->handle(Employee::whereKey($employee->id)->get(), $this->data))->toThrow(ValidationException::class);
    expect(EmployeeWorkAssignment::count())->toBe(1);
});

it('restricts bulk shift assignment to employees the user may edit', function (): void {
    $employee = Employee::factory()->create();
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ListEmployees::class)->assertActionHidden(TestAction::make('assignShift')->table()->bulk());
    expect(fn () => app(BulkAssignEmployeeShift::class)->handle(Employee::whereKey($employee->id)->get(), $this->data))->toThrow(AuthorizationException::class);
    expect(EmployeeWorkAssignment::count())->toBe(0);
});

it('rejects empty working days and nonexistent shifts', function (array $invalid): void {
    $employee = Employee::factory()->create();
    expect(fn () => app(BulkAssignEmployeeShift::class)->handle(Employee::whereKey($employee->id)->get(), [...$this->data, ...$invalid]))->toThrow(ValidationException::class);
    expect(EmployeeWorkAssignment::count())->toBe(0);
})->with([
    [['workdays' => []]], [['workdays' => [8]]], [['shift_id' => 99999]],
]);

it('supports Sunday shifts and selected rest days', function (): void {
    $employee = Employee::factory()->create();
    app(BulkAssignEmployeeShift::class)->handle(Employee::whereKey($employee->id)->get(), [...$this->data, 'workdays' => [0, 2, 4]]);
    $resolver = app(ResolveEmployeeWork::class);
    expect($resolver->forDate($employee, '2026-11-15')['shifts']->modelKeys())->toBe([$this->shift->id])
        ->and($resolver->forDate($employee, '2026-11-16')['shifts'])->toHaveCount(0);
});

it('rejects an empty selection without creating a weekly pattern', function (): void {
    expect(fn () => app(BulkAssignEmployeeShift::class)->handle(collect(), $this->data))->toThrow(ValidationException::class);
    expect(WorkSchedule::count())->toBe(0);
});

it('allows bulk assignment with no reason', function (): void {
    $employee = Employee::factory()->create();
    Livewire::test(ListEmployees::class)->selectTableRecords([$employee->id])
        ->callAction(TestAction::make('assignShift')->table()->bulk(), [...$this->data, 'reason' => null])
        ->assertHasNoActionErrors()->assertNotified();
    expect($employee->workAssignments()->sole()->reason)->toBeNull();
});

it('accepts an omitted reason in the bulk assignment service', function (): void {
    $employee = Employee::factory()->create();
    $data = $this->data;
    unset($data['reason']);
    app(BulkAssignEmployeeShift::class)->handle(Employee::whereKey($employee->id)->get(), $data);
    expect($employee->workAssignments()->sole()->reason)->toBeNull();
});
