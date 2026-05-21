<?php

namespace App\Filament\Resources\EmployeeLoans\Pages;

use App\Filament\Resources\EmployeeLoans\EmployeeLoanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageEmployeeLoans extends ManageRecords
{
    protected static string $resource = EmployeeLoanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
