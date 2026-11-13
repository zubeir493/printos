<?php

namespace App\Filament\Resources\PayrollOvertimeEntries\Pages;

use App\Filament\Resources\PayrollOvertimeEntries\PayrollOvertimeEntryResource;
use App\Models\Employee;
use App\Models\PayrollOvertimeEntry;
use App\Services\Hr\GeneratePayrollOvertimeEntries;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Facades\Gate;

class ManagePayrollOvertimeEntries extends ManageRecords
{
    protected static string $resource = PayrollOvertimeEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')->label('Generate from attendance')->schema([
                Select::make('employee_ids')->label('Employees')->options(fn (): array => Employee::orderBy('first_name')->get()->mapWithKeys(fn (Employee $employee): array => [$employee->id => $employee->full_name.' · '.$employee->employee_id])->all())->multiple()->searchable()->required(),
                DatePicker::make('from')->label('From')->default(now()->startOfMonth())->required(),
                DatePicker::make('to')->label('To')->default(today())->afterOrEqual('from')->maxDate(today())->required(),
            ])->action(function (array $data): void {
                Gate::authorize('viewAny', PayrollOvertimeEntry::class);
                foreach (Employee::whereIn('id', $data['employee_ids'])->get() as $employee) {
                    Gate::authorize('view', $employee);
                    app(GeneratePayrollOvertimeEntries::class)->generate($employee, $data['from'], $data['to']);
                }
                Notification::make()->title('Overtime generated')->success()->send();
            }),
        ];
    }
}
