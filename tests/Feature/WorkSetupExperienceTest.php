<?php

use App\Filament\Resources\Employees\Pages\EditEmployee;
use App\Filament\Resources\Employees\RelationManagers\WorkAssignmentsRelationManager;
use App\Filament\Resources\Shifts\Pages\ManageShifts;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use App\Models\PayrollRun;
use App\Models\PayrollRunEmployee;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Hr\EndEmployeeWorkAssignment;
use App\Services\Hr\ResolveAssignmentPattern;
use App\Services\Hr\ResolveEmployeeWork;
use App\Services\Hr\UpdateEmployeeWorkAssignment;
use App\Support\ShiftDisplay;
use App\UserRole;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 11, 11)->setTime(12, 0));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    Filament::setCurrentPanel(Filament::getPanel('hr'));
});

it('deletes an unused shift from its actions menu after confirmation', function (): void {
    $shift = Shift::factory()->create();
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    $component = Livewire::test(ManageShifts::class)->mountAction(TestAction::make('delete')->table($shift));
    expect($shift->fresh())->not->toBeNull();
    expect($component->instance()->getMountedAction()->isConfirmationRequired())->toBeTrue();
    $component->callMountedAction()->assertNotified();
    expect($shift->fresh())->toBeNull();
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    expect(auth()->user()->can('delete', Shift::factory()->create()))->toBeFalse();
});

it('shows a clear failure when deleting a shift still referenced by assignments', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $day = $assignment->workSchedule->days()->whereNotNull('shift_id')->firstOrFail();
    Livewire::test(ManageShifts::class)->callAction(TestAction::make('delete')->table($day->shift))
        ->assertNotified('Shift is in use');
    expect($day->shift->fresh())->not->toBeNull()
        ->and($day->fresh()->shift_id)->toBe($day->shift_id);
});

it('lets HR edit an assigned day shift and marks its draft payroll for review', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $shift = $assignment->workSchedule->days()->whereNotNull('shift_id')->firstOrFail()->shift;
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    $run = PayrollRun::factory()->create(['attendance_reviewed_at' => now(), 'attendance_reviewed_by' => auth()->id(), 'attendance_review_note' => 'Reviewed']);
    PayrollRunEmployee::withoutEvents(fn () => $run->employees()->create(['employee_id' => $assignment->employee_id]));
    $shiftCount = Shift::count();

    Livewire::test(ManageShifts::class)
        ->callAction(TestAction::make('edit')->table($shift), ['name' => 'Updated day shift', 'end_time' => '18:00:00'])
        ->assertHasNoActionErrors()->assertNotified();

    expect($shift->fresh()->end_time)->toBe('18:00:00')
        ->and($shift->fresh()->expected_minutes)->toBe(540)
        ->and(Shift::count())->toBe($shiftCount)
        ->and($run->fresh()->attendance_reviewed_at)->toBeNull()
        ->and(app(ResolveEmployeeWork::class)->forDate($assignment->employee, '2026-11-02')['shift']->id)->toBe($shift->id);
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    expect(auth()->user()->can('update', $shift))->toBeFalse();
});

it('rejects an assigned shift edit that places its break outside working hours', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $shift = $assignment->workSchedule->days()->whereNotNull('shift_id')->firstOrFail()->shift;
    Livewire::test(ManageShifts::class)
        ->callAction(TestAction::make('edit')->table($shift), ['end_time' => '11:00:00'])
        ->assertHasActionErrors(['break_start']);
    expect($shift->fresh()->end_time)->toBe('17:00:00');
});

it('rolls back a shared shift edit that overlaps another assigned period', function (): void {
    $morning = Shift::factory()->create(['start_time' => '08:00:00', 'end_time' => '12:00:00', 'break_start' => null, 'break_end' => null]);
    $afternoon = Shift::factory()->create(['start_time' => '13:00:00', 'end_time' => '17:00:00', 'break_start' => null, 'break_end' => null]);
    $schedule = WorkSchedule::factory()->create();
    $schedule->days()->where('day_of_week', 1)->firstOrFail()->update(['shift_ids' => [$morning->id, $afternoon->id]]);
    EmployeeWorkAssignment::factory()->create(['work_schedule_id' => $schedule->id]);
    Livewire::test(ManageShifts::class)
        ->callAction(TestAction::make('edit')->table($morning), ['end_time' => '14:00:00'])
        ->assertHasActionErrors(['start_time']);
    expect($morning->fresh()->end_time)->toBe('12:00:00')
        ->and($morning->fresh()->expected_minutes)->toBe(240);
});

it('ends the assignment from a confirmation without asking users to edit its fields', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $component = Livewire::test(WorkAssignmentsRelationManager::class, ['ownerRecord' => $assignment->employee, 'pageClass' => EditEmployee::class])
        ->mountAction(TestAction::make('endAssignment')->table($assignment));
    expect($component->instance()->getMountedAction()->getModalHeading())->toBe('End this assignment today?');
    expect($component->get('mountedActions.0.data'))->toBeNull();
    $component->callMountedAction()->assertNotified();
    expect($assignment->fresh()->effective_to->toDateString())->toBe('2026-11-11')
        ->and($assignment->fresh()->reason)->toContain('Ended on 2026-11-11');
    $reason = $assignment->fresh()->reason;
    app(EndEmployeeWorkAssignment::class)->handle($assignment);
    expect($assignment->fresh()->reason)->toBe($reason);
    expect(fn () => app(ResolveEmployeeWork::class)->forDate($assignment->employee, '2026-11-12'))->toThrow(ValidationException::class);
});

it('edits the repeating shifts through a separate edit action', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $newSchedule = WorkSchedule::factory()->create();
    $policy = PayPolicy::factory()->create();
    Livewire::test(WorkAssignmentsRelationManager::class, ['ownerRecord' => $assignment->employee, 'pageClass' => EditEmployee::class])
        ->callAction(TestAction::make('edit')->table($assignment), ['days' => $newSchedule->load('days.shift', 'days.additionalShifts')->days->map(fn ($day): array => ['day_of_week' => $day->day_of_week, 'shift_ids' => $day->shift_ids])->all(), 'reason' => 'Corrected regular pattern'])
        ->assertHasNoActionErrors();
    expect($assignment->fresh()->workSchedule->days->pluck('shift_id')->all())->toBe($newSchedule->days->pluck('shift_id')->all())
        ->and($assignment->fresh()->pay_policy_id)->toBe($assignment->pay_policy_id)
        ->and($assignment->fresh()->effective_to)->toBeNull();
});

it('keeps assignment changes away from posted payroll but permits ending after the posted period', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $run = PayrollRun::factory()->create(['period_start' => '2026-10-01', 'period_end' => '2026-10-31']);
    PayrollRunEmployee::withoutEvents(fn () => $run->employees()->create(['employee_id' => $assignment->employee_id]));
    $run->update(['status' => 'approved']);
    $schedule = WorkSchedule::factory()->create();
    expect(fn () => app(UpdateEmployeeWorkAssignment::class)->handle($assignment, ['work_schedule_id' => $schedule->id, 'reason' => 'Edit past work']))->toThrow(ValidationException::class);
    app(EndEmployeeWorkAssignment::class)->handle($assignment->fresh());
    expect($assignment->fresh()->effective_to->toDateString())->toBe('2026-11-11');
});

it('blocks ending when a posted payroll includes dates that would lose coverage', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $run = PayrollRun::factory()->create(['period_start' => '2026-11-01', 'period_end' => '2026-11-30']);
    PayrollRunEmployee::withoutEvents(fn () => $run->employees()->create(['employee_id' => $assignment->employee_id]));
    $run->update(['status' => 'approved']);
    expect(fn () => app(EndEmployeeWorkAssignment::class)->handle($assignment))->toThrow(ValidationException::class);
    expect($assignment->fresh()->effective_to)->toBeNull();
    Livewire::test(WorkAssignmentsRelationManager::class, ['ownerRecord' => $assignment->employee, 'pageClass' => EditEmployee::class])
        ->callAction(TestAction::make('endAssignment')->table($assignment))
        ->assertNotified('Assignment could not be ended');
    expect($assignment->fresh()->effective_to)->toBeNull();
});

it('does not allow finance users to end employee assignments', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    expect(fn () => app(EndEmployeeWorkAssignment::class)->handle($assignment))->toThrow(AuthorizationException::class);
});

it('fills all seven days and mixes shifts without creating overlapping employee assignments', function (): void {
    $dayShift = Shift::factory()->create();
    $nightShift = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null]);
    $days = collect([1, 2, 3, 4, 5, 6, 0])->map(fn (int $day): array => ['day_of_week' => $day, 'shift_ids' => [$dayShift->id]])->all();
    foreach ($days as &$day) {
        if ((int) $day['day_of_week'] === 6) {
            $day['shift_ids'] = [$nightShift->id];
        }
    }
    unset($day);
    $schedule = app(ResolveAssignmentPattern::class)->handle($days);
    $assignment = EmployeeWorkAssignment::factory()->create(['work_schedule_id' => $schedule->id]);
    $resolver = app(ResolveEmployeeWork::class);
    expect($resolver->forDate($assignment->employee, '2026-11-14')['shift']->id)->toBe($nightShift->id)
        ->and($resolver->forDate($assignment->employee, '2026-11-15')['shift']->id)->toBe($dayShift->id)
        ->and($assignment->employee->workAssignments()->count())->toBe(1);
});

it('previews paid hours while creating a shift with an unpaid break', function (): void {
    $component = Livewire::test(ManageShifts::class)->mountAction('create')
        ->set('mountedActions.0.data.name', 'Production day')
        ->set('mountedActions.0.data.start_time', '08:00:00')->set('mountedActions.0.data.end_time', '17:00:00')
        ->set('mountedActions.0.data._has_break', true)
        ->set('mountedActions.0.data.break_start', '12:00:00')->set('mountedActions.0.data.break_end', '13:00:00');
    expect(view('filament.resources.shifts.shift-preview', ['preview' => ShiftDisplay::preview($component->get('mountedActions.0.data'))])->render())->toContain('8h', '1h unpaid');
    $component->callMountedAction()->assertHasNoActionErrors();
    expect(Shift::where('name', 'Production day')->firstOrFail()->expected_minutes)->toBe(480);
});

it('clears an existing break from the edit form without leaving a hidden deduction', function (): void {
    $shift = Shift::factory()->create();
    $component = Livewire::test(ManageShifts::class)->mountAction(TestAction::make('edit')->table($shift))
        ->set('mountedActions.0.data._has_break', false);
    expect(view('filament.resources.shifts.shift-preview', ['preview' => ShiftDisplay::preview($component->get('mountedActions.0.data'))])->render())->toContain('No break');
    $component->callMountedAction()->assertHasNoActionErrors();
    expect($shift->fresh()->expected_minutes)->toBe(540)->and($shift->fresh()->break_start)->toBeNull();
});

it('creates an overnight variant without changing the original shift', function (): void {
    $shift = Shift::factory()->create();
    $component = Livewire::test(ManageShifts::class)->mountAction(TestAction::make('duplicate')->table($shift))
        ->set('mountedActions.0.data.name', 'Production night')
        ->set('mountedActions.0.data._has_break', false)
        ->set('mountedActions.0.data.start_time', '22:00:00')->set('mountedActions.0.data.end_time', '06:00:00');
    expect(view('filament.resources.shifts.shift-preview', ['preview' => ShiftDisplay::preview($component->get('mountedActions.0.data'))])->render())->toContain('Overnight', '8h');
    $component->callMountedAction()->assertHasNoActionErrors();
    expect(Shift::where('name', 'Production night')->firstOrFail()->expected_minutes)->toBe(480)
        ->and($shift->fresh()->start_time)->toBe('08:00:00');
});

it('explains invalid break times in the preview', function (): void {
    $preview = ShiftDisplay::preview(['start_time' => '08:00:00', 'end_time' => '17:00:00', '_has_break' => true, 'break_start' => '18:00:00', 'break_end' => '19:00:00']);
    expect($preview['valid'])->toBeFalse()->and($preview['message'])->toBe('The break must fall within the shift hours.');
});
