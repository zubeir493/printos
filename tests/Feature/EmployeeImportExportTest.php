<?php

use App\Filament\Exports\EmployeeExporter;
use App\Filament\Imports\EmployeeImporter;
use App\Models\Employee;
use App\Models\User;
use App\UserRole;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Imports\Exceptions\RowImportFailedException;
use Filament\Actions\Imports\Models\Import;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
});

function exportedEmployeeCsvRow(Employee $employee): array
{
    $columns = collect(EmployeeExporter::getColumns())->mapWithKeys(fn ($column): array => [$column->getName() => $column->getLabel()])->all();
    $values = (new EmployeeExporter(new Export, $columns, []))($employee);
    $stream = fopen('php://temp', 'r+');
    fputcsv($stream, $values, escape: '');
    rewind($stream);
    $values = fgetcsv($stream, escape: '');
    fclose($stream);

    return array_combine(array_values($columns), $values);
}

function importEmployeeCsvRow(array $row): void
{
    $map = collect(EmployeeImporter::getColumns())->filter(fn ($column): bool => array_key_exists($column->getLabel(), $row))
        ->mapWithKeys(fn ($column): array => [$column->getName() => $column->getLabel()])->all();
    $import = (new Import)->setRelation('user', auth()->user());
    (new EmployeeImporter($import, $map, []))($row);
}

it('keeps import and export fields and headers identical', function (): void {
    $imports = collect(EmployeeImporter::getColumns())->mapWithKeys(fn ($column): array => [$column->getName() => $column->getLabel()])->all();
    $exports = collect(EmployeeExporter::getColumns())->mapWithKeys(fn ($column): array => [$column->getName() => $column->getLabel()])->all();
    expect($exports)->toBe($imports)->not->toHaveKey('full_name');
});

it('round trips exported CSV without losing dates identifiers false or zero values', function (): void {
    $employee = Employee::factory()->create([
        'attendance_device_id' => '00042', 'account_number' => '0012345', 'phone' => '0912345678',
        'tax_id' => '00098', 'first_name' => 'Abas Ahmed', 'last_name' => 'Seman Mohammed',
        'hire_date' => '2026-01-05', 'termination_date' => null, 'pension_enabled' => false, 'basic_salary' => 0, 'transport_allowance' => 0,
    ]);
    $row = exportedEmployeeCsvRow($employee);
    expect($row['Hire Date'])->toBe('2026-01-05')->and($row['Termination Date'])->toBe('')
        ->and($row['Pension Enabled'])->toBe('0')->and($row['Punch Machine AC No'])->toBe('00042');
    importEmployeeCsvRow($row);
    expect(Employee::count())->toBe(1)->and($employee->fresh()->attendance_device_id)->toBe('00042')
        ->and($employee->fresh()->account_number)->toBe('0012345')->and($employee->fresh()->pension_enabled)->toBeFalse()
        ->and($employee->salaryHistories()->count())->toBe(1)->and($employee->fresh()->first_name)->toBe('Abas Ahmed');
});

it('uses the exported fields to create new employees with initial salary history', function (): void {
    $source = Employee::factory()->create(['termination_date' => null, 'basic_salary' => 12345.67, 'transport_allowance' => 500]);
    $row = [...exportedEmployeeCsvRow($source), 'Employee ID' => 'EMP-NEW', 'Punch Machine AC No' => '00007'];
    importEmployeeCsvRow($row);
    $created = Employee::where('employee_id', 'EMP-NEW')->firstOrFail();
    expect($created->attendance_device_id)->toBe('00007')->and($created->basic_salary)->toBe('12345.67')
        ->and($created->salaryHistories()->count())->toBe(1)->and($created->termination_date)->toBeNull();
});

it('updates employee details without creating a salary revision', function (): void {
    $employee = Employee::factory()->create();
    importEmployeeCsvRow([...exportedEmployeeCsvRow($employee), 'Department' => 'Printing', 'First Name' => 'Updated']);
    expect($employee->fresh()->department)->toBe('Printing')->and($employee->fresh()->first_name)->toBe('Updated')
        ->and($employee->salaryHistories()->count())->toBe(1);
});

it('rejects duplicate AC numbers and invalid employee values', function (array $changes): void {
    Employee::factory()->create(['attendance_device_id' => 'TAKEN']);
    $employee = Employee::factory()->create();
    expect(fn () => importEmployeeCsvRow([...exportedEmployeeCsvRow($employee), ...$changes]))->toThrow(ValidationException::class);
    expect($employee->fresh()->attendance_device_id)->toBe($employee->attendance_device_id);
})->with([
    [['Punch Machine AC No' => 'TAKEN']], [['Status' => 'unknown']], [['Employment Type' => 'unknown']],
    [['Monthly Rate' => '-1']], [['Transportation Allowance' => '-2']], [['Termination Date' => '2025-01-01']],
]);

it('keeps compensation changes in the salary revision workflow', function (): void {
    $employee = Employee::factory()->create();
    expect(fn () => importEmployeeCsvRow([...exportedEmployeeCsvRow($employee), 'Monthly Rate' => '99999']))->toThrow(RowImportFailedException::class);
    expect($employee->fresh()->basic_salary)->toBe($employee->basic_salary)->and($employee->salaryHistories()->count())->toBe(1);
});

it('authorizes imported employee changes using the importing user', function (): void {
    $employee = Employee::factory()->create();
    $this->actingAs(User::factory()->create(['role' => UserRole::Finance]));
    expect(fn () => importEmployeeCsvRow([...exportedEmployeeCsvRow($employee), 'First Name' => 'Unauthorized']))->toThrow(AuthorizationException::class);
    expect($employee->fresh()->first_name)->toBe($employee->first_name);
});
