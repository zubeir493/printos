<?php

use App\Filament\Resources\LeaveRequests\Pages\ManageLeaveRequests;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\UserRole;
use Database\Seeders\LeaveTypeSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::HR]));
    Filament::setCurrentPanel(Filament::getPanel('hr'));
});

it('seeds common leave types without duplicates or overwriting configured allowances', function (): void {
    $this->seed(LeaveTypeSeeder::class);
    $annual = LeaveType::where('name', 'Annual leave')->firstOrFail();
    expect($annual->annual_allowance_days)->toBeNull();
    $annual->update(['annual_allowance_days' => 20]);
    $this->seed(LeaveTypeSeeder::class);
    expect(LeaveType::count())->toBe(7)
        ->and(LeaveType::where('is_paid', true)->count())->toBe(6)
        ->and(LeaveType::where('name', 'Unpaid leave')->firstOrFail()->is_paid)->toBeFalse()
        ->and((float) $annual->fresh()->annual_allowance_days)->toBe(20.0);
});

it('removes the standalone leave type resource from both panels', function (): void {
    foreach (['admin', 'hr'] as $panel) {
        expect(Filament::getPanel($panel)->getResources())->not->toContain('App\Filament\Resources\LeaveTypes\LeaveTypeResource');
    }
    $this->get('/hr/leave-types')->assertNotFound();
    $this->get('/leave-types')->assertNotFound();
});

it('creates and selects a custom leave type without losing the parent request', function (): void {
    $employee = Employee::factory()->create();
    $component = Livewire::test(ManageLeaveRequests::class)->mountAction('create')
        ->fillForm(['employee_id' => $employee->id, 'start_date' => '2026-11-16', 'end_date' => '2026-11-16', 'reason' => 'Professional examination'])
        ->callAction(TestAction::make('createOption')->schemaComponent('leave_type_id', 'mountedActionSchema0'), [
            'name' => 'Exam leave', 'is_paid' => true, 'annual_allowance_days' => 3,
        ])->assertHasNoActionErrors();
    $type = LeaveType::where('name', 'Exam leave')->firstOrFail();
    expect((int) $component->get('mountedActions.0.data.leave_type_id'))->toBe($type->id)
        ->and($component->get('mountedActions.0.data.reason'))->toBe('Professional examination')
        ->and(LeaveRequest::count())->toBe(0);
    $component->callMountedAction()->assertHasNoActionErrors();
    expect(LeaveRequest::firstOrFail()->leave_type_id)->toBe($type->id)
        ->and(LeaveRequest::firstOrFail()->status)->toBe('pending');
});

it('validates custom leave type names and annual limits', function (): void {
    $this->seed(LeaveTypeSeeder::class);
    Livewire::test(ManageLeaveRequests::class)->callAction([
        TestAction::make('create'),
        TestAction::make('createOption')->schemaComponent('leave_type_id', 'mountedActionSchema0'),
    ], ['name' => 'Annual leave', 'is_paid' => true])->assertHasActionErrors(['name']);
    Livewire::test(ManageLeaveRequests::class)->callAction([
        TestAction::make('create'),
        TestAction::make('createOption')->schemaComponent('leave_type_id', 'mountedActionSchema0'),
    ], ['name' => 'Exam leave', 'is_paid' => false, 'annual_allowance_days' => -1])->assertHasActionErrors(['annual_allowance_days']);
    expect(LeaveType::count())->toBe(7);
});

it('authorizes inline type creation separately from leave requests', function (): void {
    Gate::before(fn ($user, string $ability, array $arguments): ?bool => $ability === 'create' && ($arguments[0] ?? null) === LeaveType::class ? false : null);
    Livewire::test(ManageLeaveRequests::class)->mountAction('create')
        ->assertActionHidden(TestAction::make('createOption')->schemaComponent('leave_type_id', 'mountedActionSchema0'));
    expect(LeaveType::count())->toBe(0);
});
