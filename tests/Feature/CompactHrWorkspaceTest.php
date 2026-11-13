<?php

use App\Filament\Pages\AttendanceDevice;
use App\Filament\Pages\Settings;
use App\Filament\Resources\AttendanceSegments\Pages\ManageAttendanceSegments;
use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\RelationManagers\WorkAssignmentsRelationManager;
use App\Filament\Resources\PayPolicies\PayPolicyResource;
use App\Filament\Resources\PayrollOvertimeEntries\PayrollOvertimeEntryResource;
use App\Filament\Resources\Shifts\Pages\ManageShifts;
use App\Filament\Resources\Shifts\ShiftResource;
use App\Filament\Resources\WorkSchedules\WorkScheduleResource;
use App\Jobs\SyncDeviceAttendance;
use App\Models\AttendanceDeviceSync;
use App\Models\Employee;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\Setting;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Hr\AssignEmployeeWork;
use App\Services\Hr\SavePayrollPolicy;
use App\UserRole;
use Database\Seeders\HrWorkSetupSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Tabs;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 12)->setTime(12, 0));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->seed(HrWorkSetupSeeder::class);
});

it('consolidates the HR navigation under attendance and payroll', function (): void {
    expect(WorkScheduleResource::shouldRegisterNavigation())->toBeFalse()
        ->and(PayPolicyResource::shouldRegisterNavigation())->toBeFalse()
        ->and(AttendanceDevice::shouldRegisterNavigation())->toBeFalse()
        ->and(ShiftResource::getNavigationParentItem())->toBe('Attendance')
        ->and(PayrollOvertimeEntryResource::getNavigationParentItem())->toBe('Payroll');
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    expect(PayrollOvertimeEntryResource::getNavigationParentItem())->toBe('Attendance');
});

it('uses native time inputs and saves afternoon shift times correctly', function (): void {
    $component = Livewire::test(ManageShifts::class)->mountAction('create');
    expect($component->instance()->getSchema('mountedActionSchema0')->getComponent('start_time')->isNative())->toBeTrue();
    $component->fillForm(['name' => 'Afternoon', 'start_time' => '13:15', 'end_time' => '17:45', '_has_break' => false], 'mountedActionSchema0')->callMountedAction()->assertHasNoActionErrors();
    expect(Shift::where('name', 'Afternoon')->firstOrFail()->start_time)->toBe('13:15:00');
});

it('assigns full weekdays a half saturday and a sunday rest without a schedule selector', function (): void {
    $employee = Employee::factory()->create();
    $component = Livewire::test(WorkAssignmentsRelationManager::class, ['ownerRecord' => $employee, 'pageClass' => EditEmployee::class])
        ->mountAction(TestAction::make('create')->table());
    $days = collect($component->get('mountedActions.0.data.days'))->keyBy('day_of_week');
    expect($days[0]['shift_ids'])->toBeEmpty()
        ->and(Shift::findOrFail($days[6]['shift_ids'][0])->expected_minutes)->toBe(240)
        ->and(Shift::findOrFail($days[1]['shift_ids'][0])->expected_minutes)->toBe(480);
    $component->fillForm(['effective_from' => '2026-11-12', 'reason' => 'Regular assignment'], 'mountedActionSchema0')->callMountedAction()->assertHasNoActionErrors();
    expect($employee->workAssignments()->count())->toBe(1)
        ->and($employee->workAssignments()->firstOrFail()->effective_to)->toBeNull();
});

it('shows compact sync status on attendance and queues the read only sync', function (): void {
    Bus::fake();
    config(['attendance_device.enabled' => true]);
    AttendanceDeviceSync::factory()->create(['device_id' => config('attendance_device.id'), 'status' => 'failed', 'last_error' => 'Connection timed out']);
    $component = Livewire::test(ManageAttendanceSegments::class)->mountAction('syncNow');
    expect($component->instance()->getMountedAction()->getModalHeading())->toBe('Attendance sync');
    expect($component->instance()->getSchema('mountedActionSchema0')->getComponent('error')->getState())->toBe('Connection timed out');
    $component->callMountedAction()->assertNotified();
    Bus::assertDispatched(SyncDeviceAttendance::class);
});

it('does not queue sync when collection is disabled', function (): void {
    Bus::fake();
    config(['attendance_device.enabled' => false]);
    Livewire::test(ManageAttendanceSegments::class)->callAction('syncNow');
    Bus::assertNotDispatched(SyncDeviceAttendance::class);
});

it('saves payroll rules from settings and preserves the earlier assignment', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create(['effective_from' => '2026-10-01']);
    $oldPolicy = $assignment->payPolicy;
    Livewire::test(Settings::class)->fillForm(['pay_rules.day_overtime_multiplier' => 2, 'pay_rules_from' => '2026-11-12'])->call('save')->assertHasNoFormErrors()->assertNotified();
    $current = PayPolicy::latest('id')->firstOrFail();
    expect((float) $current->day_overtime_multiplier)->toBe(2.0)
        ->and($current->standard_daily_minutes)->toBe(480)
        ->and($assignment->fresh()->pay_policy_id)->toBe($oldPolicy->id)
        ->and($assignment->fresh()->effective_to->toDateString())->toBe('2026-11-11')
        ->and($assignment->employee->workAssignments()->whereDate('effective_from', '2026-11-12')->firstOrFail()->pay_policy_id)->toBe($current->id);
});

it('does not create policy revisions when saving unrelated settings', function (): void {
    $count = PayPolicy::count();
    Livewire::test(Settings::class)->fillForm(['company_name' => 'Updated company'])->call('save')->assertHasNoFormErrors();
    expect(PayPolicy::count())->toBe($count)->and(Setting::getSettings()->company_name)->toBe('Updated company');
});

it('keeps a future rule change out of earlier assignments', function (): void {
    $old = PayPolicy::forDate('2026-11-12');
    $new = app(SavePayrollPolicy::class)->handle(['day_overtime_multiplier' => 2], '2026-12-01');
    expect(PayPolicy::forDate('2026-11-30')->id)->toBe($old->id)
        ->and(PayPolicy::forDate('2026-12-01')->id)->toBe($new->id);
    Livewire::test(Settings::class)->call('save')->assertHasNoFormErrors();
    expect(PayPolicy::count())->toBe(2);
});

it('automatically includes pending company rule changes in new repeating assignments', function (): void {
    $old = PayPolicy::forDate('2026-11-12');
    $new = app(SavePayrollPolicy::class)->handle(['day_overtime_multiplier' => 2], '2026-12-01');
    $employee = Employee::factory()->create();
    $schedule = WorkSchedule::firstOrFail();
    app(AssignEmployeeWork::class)->handleCompanyRules($employee, $schedule->id, '2026-11-12', 'Regular work');
    $assignments = $employee->workAssignments()->orderBy('effective_from')->get();
    expect($assignments)->toHaveCount(2)
        ->and($assignments[0]->pay_policy_id)->toBe($old->id)
        ->and($assignments[0]->effective_to->toDateString())->toBe('2026-11-30')
        ->and($assignments[1]->pay_policy_id)->toBe($new->id)
        ->and($assignments[1]->effective_to)->toBeNull();
});

it('preserves an existing repeating week when only its assignment note changes', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    Livewire::test(WorkAssignmentsRelationManager::class, ['ownerRecord' => $assignment->employee, 'pageClass' => EditEmployee::class])
        ->callAction(TestAction::make('edit')->table($assignment), ['reason' => 'Updated note'])->assertHasNoActionErrors();
    expect($assignment->fresh()->work_schedule_id)->toBe($assignment->work_schedule_id);
});

it('rolls back company settings and policy revisions when payroll is finalized', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create(['effective_from' => '2026-10-01']);
    $run = PayrollRun::factory()->create(['period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    PayrollRunEmployee::withoutEvents(fn () => $run->employees()->create(['employee_id' => $assignment->employee_id]));
    $run->update(['status' => 'approved']);
    $count = PayPolicy::count();
    expect(fn () => app(SavePayrollPolicy::class)->handle(['day_overtime_multiplier' => 2], '2026-11-12'))->toThrow(ValidationException::class);
    expect(PayPolicy::count())->toBe($count)->and($assignment->fresh()->effective_to)->toBeNull();
});

it('lets finance manage payroll settings without changing company settings or employee shifts', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create(['effective_from' => '2026-10-01']);
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    Filament::setCurrentPanel(Filament::getPanel('finance'));
    $settings = Setting::getSettings();
    $component = Livewire::test(Settings::class);
    expect($component->instance()->form->getComponent(fn ($component): bool => $component instanceof Tabs)->getActiveTab())->toBe(1);
    $component->fillForm(['company_name' => 'Tampered', 'pay_rules.day_overtime_multiplier' => 2, 'employee_pension_rate' => 8])->call('save')->assertHasNoFormErrors()->assertNotified();
    expect($settings->fresh()->company_name)->toBe($settings->company_name)
        ->and((float) $settings->fresh()->employee_pension_rate)->toBe(8.0)
        ->and($assignment->employee->workAssignments()->count())->toBe(2);
});

it('prevents HR from editing company payroll rules', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    expect(Settings::canAccess())->toBeFalse();
    expect(fn () => app(SavePayrollPolicy::class)->handle(['day_overtime_multiplier' => 3], '2026-11-12'))->toThrow(AuthorizationException::class);
});
