<?php

use App\Filament\Resources\Employees\Pages\CreateEmployee;
use App\Models\Employee;
use App\Models\User;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('creates employees without an overtime multiplier field', function () {
    Filament::setCurrentPanel(Filament::getPanel('hr'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::HR,
    ]));

    Livewire::test(CreateEmployee::class)
        ->fillForm([
            'employee_id' => 'EMP-NO-OT',
            'attendance_device_id' => 'EMP-NO-OT',
            'first_name' => 'No',
            'last_name' => 'Multiplier',
            'phone' => '0911111111',
            'hire_date' => '2026-05-01',
            'status' => 'active',
            'employment_type' => 'permanent',
            'pension_enabled' => true,
            'basic_salary' => 12000,
            'transport_allowance' => 500,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $employee = Employee::query()->where('employee_id', 'EMP-NO-OT')->firstOrFail();

    expect((float) $employee->basic_salary)->toBe(12000.0)
        ->and((float) $employee->transport_allowance)->toBe(500.0)
        ->and($employee->salaryHistories()->count())->toBe(1)
        ->and((float) $employee->salaryHistories()->firstOrFail()->basic_salary)->toBe(12000.0);
});
