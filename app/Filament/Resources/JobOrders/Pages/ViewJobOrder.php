<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\JobOrders\Actions\JobOrderActions;
use App\Filament\Resources\JobOrders\JobOrderResource;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewJobOrder extends ViewRecord
{
    protected static string $resource = JobOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->job_order_number;
    }

    protected function getHeaderActions(): array
    {
        return [
            JobOrderActions::make(),
        ];
    }
}
