<?php

use App\Models\EmployeeShiftOverride;
use App\Models\EmployeeWorkAssignment;
use App\Models\PayPolicy;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkSchedule;
use App\Services\Hr\AssignEmployeeWork;
use App\Services\Hr\ResolveEmployeeWork;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('repeats an ongoing weekly assignment across weeks and years without reassignment', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $resolver = app(ResolveEmployeeWork::class);
    $first = $resolver->forDate($assignment->employee, '2026-11-02');
    foreach (['2026-11-09', '2027-11-01'] as $date) {
        $work = $resolver->forDate($assignment->employee, $date);
        expect($work['assignment']->id)->toBe($assignment->id)->and($work['shift']->id)->toBe($first['shift']->id);
    }
    expect($assignment->effective_to)->toBeNull()->and($assignment->employee->workAssignments()->count())->toBe(1);
});

it('resolves weekly work, explicit rest days and dated overrides', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    $employee = $assignment->employee;
    $resolver = app(ResolveEmployeeWork::class);
    expect($resolver->forDate($employee, '2026-11-02')['shift'])->not->toBeNull()
        ->and($resolver->forDate($employee, '2026-11-01')['shift'])->toBeNull();
    EmployeeShiftOverride::create(['employee_id' => $employee->id, 'date' => '2026-11-02', 'shift_id' => null, 'reason' => 'Approved substituted rest day']);
    expect($resolver->forDate($employee, '2026-11-02')['shift'])->toBeNull();
});

it('rejects overlapping and reversed work assignments', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    expect(fn () => EmployeeWorkAssignment::factory()->create(['employee_id' => $assignment->employee_id]))->toThrow(ValidationException::class);
    expect(fn () => EmployeeWorkAssignment::factory()->create(['effective_from' => '2026-11-02', 'effective_to' => '2026-11-01']))->toThrow(ValidationException::class);
});

it('locks used policies and patterns while allowing assigned shift edits', function (): void {
    $assignment = EmployeeWorkAssignment::factory()->create();
    expect(fn () => $assignment->payPolicy->update(['day_overtime_multiplier' => 3]))->toThrow(ValidationException::class);
    expect(fn () => $assignment->workSchedule->update(['name' => 'Changed']))->toThrow(ValidationException::class);
    $day = $assignment->workSchedule->days()->whereNotNull('shift_id')->firstOrFail();
    $day->shift->update(['end_time' => '18:00:00']);
    expect($day->shift->fresh()->end_time)->toBe('18:00:00');
    expect(fn () => $day->update(['shift_id' => null]))->toThrow(ValidationException::class);
});

it('rejects incomplete weekly schedules and invalid policy divisors', function (): void {
    $schedule = WorkSchedule::create(['name' => 'Incomplete']);
    expect(fn () => EmployeeWorkAssignment::factory()->create(['work_schedule_id' => $schedule->id]))->toThrow(ValidationException::class);
    expect(fn () => PayPolicy::factory()->create(['monthly_day_divisor' => 0]))->toThrow(ValidationException::class);
});

it('derives shift paid minutes from its hours and unpaid break', function (): void {
    $shift = Shift::factory()->create();
    expect($shift->expected_minutes)->toBe(480);
    $night = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => '02:00:00', 'break_end' => '02:30:00']);
    expect($night->expected_minutes)->toBe(450)->and($night->is_night_shift)->toBeTrue();
});

it('associates a checkout after midnight with the previous work date', function (): void {
    $schedule = WorkSchedule::factory()->create();
    $shift = Shift::factory()->create(['start_time' => '22:00:00', 'end_time' => '06:00:00', 'break_start' => null, 'break_end' => null, 'break_minutes' => 0]);
    $schedule->days()->where('day_of_week', 6)->firstOrFail()->update(['shift_id' => $shift->id]);
    $assignment = EmployeeWorkAssignment::factory()->create(['work_schedule_id' => $schedule->id]);
    $work = app(ResolveEmployeeWork::class)->forPunch($assignment->employee, CarbonImmutable::parse('2026-11-08 06:00:00'));
    expect($work['date'])->toBe('2026-11-07')->and($work['shift']->id)->toBe($shift->id);
});

it('closes the previous assignment when HR assigns a new schedule from a date', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    $previous = EmployeeWorkAssignment::factory()->create();
    $next = app(AssignEmployeeWork::class)->handle($previous->employee, $previous->work_schedule_id, $previous->pay_policy_id, '2026-11-15', 'Production team change');
    expect($previous->fresh()->effective_to->toDateString())->toBe('2026-11-14')
        ->and($next->effective_from->toDateString())->toBe('2026-11-15');
});

it('clears derived break minutes when an unused shift break is removed', function (): void {
    $shift = Shift::factory()->create();
    $shift->update(['break_start' => null, 'break_end' => null]);
    expect($shift->break_minutes)->toBe(0)->and($shift->expected_minutes)->toBe(540);
});

it('retires a policy for new assignments while retaining its dated history', function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    $assignment = EmployeeWorkAssignment::factory()->create();
    $assignment->payPolicy->update(['is_active' => false]);
    $assignment->update(['effective_to' => '2026-11-14']);
    expect(app(ResolveEmployeeWork::class)->forDate($assignment->employee, '2026-11-02')['policy']->id)->toBe($assignment->pay_policy_id);
    expect(fn () => app(AssignEmployeeWork::class)->handle($assignment->employee, $assignment->work_schedule_id, $assignment->pay_policy_id, '2026-11-15', 'New assignment'))->toThrow(ValidationException::class);
});
