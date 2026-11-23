<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Resources\Pages\CreateRecord;
use App\Filament\Support\PanelAccess;
use App\Services\JobOrders\JobProductionCosting;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class CreateJobOrder extends CreateRecord
{
    protected static string $resource = JobOrderResource::class;

    protected static bool $canCreateAnother = false;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManageJobOrders();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'status' => 'draft', 'uses_production_ticket' => true, 'cost_calc_file' => null];
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $estimateId = Arr::pull($data, 'cost_estimate_id');
            $job = parent::handleRecordCreation($data);
            if (filled($estimateId)) {
                app(JobProductionCosting::class)->useEstimate($job, (int) $estimateId);
            }

            return $job;
        }, attempts: 3);
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Save draft');
    }
}
