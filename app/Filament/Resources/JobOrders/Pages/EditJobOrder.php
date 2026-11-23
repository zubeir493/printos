<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\JobOrders\Actions\JobOrderActions;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Support\PanelAccess;
use App\Services\JobOrders\JobProductionCosting;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EditJobOrder extends EditRecord
{
    protected static string $resource = JobOrderResource::class;

    public static function canAccess($record = null): bool
    {
        return PanelAccess::canManageJobOrders();
    }

    public function getTitle(): string|Htmlable
    {
        return 'Edit '.$this->getRecord()->job_order_number;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return DB::transaction(function () use ($record, $data): Model {
            $locked = $record->newQuery()->lockForUpdate()->findOrFail($record->getKey());
            Gate::authorize('update', $locked);
            $changeEstimate = array_key_exists('cost_estimate_id', $data);
            $estimateId = Arr::pull($data, 'cost_estimate_id');
            $locked->update($data);
            if ($changeEstimate) {
                app(JobProductionCosting::class)->useEstimate($locked, filled($estimateId) ? (int) $estimateId : null);
            }

            return $locked;
        }, attempts: 3);
    }

    protected function getHeaderActions(): array
    {
        return [
            JobOrderActions::make(includeDelete: true),
        ];
    }
}
