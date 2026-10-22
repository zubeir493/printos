<?php

namespace App\Filament\Resources\Employees\Pages;

use App\Filament\Hr\Widgets\EmployeeSalaryTrendChart;
use App\Filament\Resources\Employees\EmployeeResource;
use App\Services\Hr\ReviseEmployeeSalary;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\EditRecord;

class EditEmployee extends EditRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    protected function getHeaderWidgets(): array
    {
        return [
            EmployeeSalaryTrendChart::class,
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reviseSalary')->label('Revise salary')->schema([TextInput::make('basic_salary')->numeric()->minValue(0)->required(), DatePicker::make('effective_date')->required(), Textarea::make('reason')->required()])->action(function (array $data): void {
                app(ReviseEmployeeSalary::class)->handle($this->record, (float) $data['basic_salary'], $data['effective_date'], $data['reason']);
                $this->record->refresh();
                $this->fillForm();
            }),
            DeleteAction::make(),
        ];
    }
}
