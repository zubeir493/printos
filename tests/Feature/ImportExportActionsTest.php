<?php

use App\Filament\Exports\AttendanceSegmentExporter;
use App\Filament\Exports\EmployeeExporter;
use App\Filament\Imports\EmployeeImporter;
use Filament\Actions\View\ActionsIconAlias;
use Filament\Support\Facades\FilamentIcon;
use Filament\Support\Icons\Heroicon;

test('resource import and export actions use the expected grouped icons', function (): void {
    expect(FilamentIcon::resolve(ActionsIconAlias::IMPORT_ACTION_GROUPED))->toBe(Heroicon::ArrowDownTray)
        ->and(FilamentIcon::resolve(ActionsIconAlias::EXPORT_ACTION_GROUPED))->toBe(Heroicon::ArrowUpTray);
});

test('employees and attendance segments have import and export actions wired', function (): void {
    expect(file_get_contents(app_path('Filament/Resources/Employees/Pages/ListEmployees.php')))
        ->toContain(EmployeeImporter::class)
        ->toContain(EmployeeExporter::class)
        ->and(file_get_contents(app_path('Filament/Resources/AttendanceSegments/Pages/ManageAttendanceSegments.php')))
        ->toContain(AttendanceSegmentExporter::class);
});

test('employee import and export columns are available', function (): void {
    $importColumns = collect(EmployeeImporter::getColumns())->map(fn ($column): string => $column->getName());
    $exportColumns = collect(EmployeeExporter::getColumns())->map(fn ($column): string => $column->getName());

    expect($importColumns)
        ->toContain('employee_id')
        ->toContain('first_name')
        ->toContain('hire_date')
        ->and($exportColumns)
        ->toContain('employee_id')
        ->toContain('full_name')
        ->toContain('basic_salary');
});
