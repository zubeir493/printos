<?php

namespace App\Filament\Resources\JobOrders\Pages;

use App\Filament\Resources\Dispatches\DispatchResource;
use App\Filament\Resources\JobOrders\Actions\JobOrderActions;
use App\Filament\Resources\JobOrders\Actions\JobOrderWorkflowActions;
use App\Filament\Resources\JobOrders\JobOrderResource;
use App\Filament\Support\PanelAccess;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Gate;

class ViewJobOrder extends ViewRecord
{
    protected static string $resource = JobOrderResource::class;

    public function getTitle(): string|Htmlable
    {
        return $this->getRecord()->job_order_number;
    }

    public function getRelationManagersContentComponent(): Component
    {
        return Group::make([parent::getRelationManagersContentComponent()])
            ->id('job-order-files')->columnSpanFull();
    }

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();
        $primary = match (true) {
            (string) $record->status === 'draft' && Gate::allows('manageWorkflow', $record) => JobOrderActions::activate(),
            (string) $record->status === 'design' && (empty($record->deliverables) || $record->isReadyForProduction()) && Gate::allows('manageWorkflow', $record) => JobOrderWorkflowActions::sendToProduction(),
            (string) $record->status === 'design' && filled($record->deliverables) && ! $record->isReadyForProduction() && in_array(auth()->user()?->role, [UserRole::Admin, UserRole::Operations, UserRole::Design, UserRole::Typist], true) => Action::make('review_files')->label('Review required files')->icon('heroicon-o-document-check')->url('#job-order-files'),
            (string) $record->status === 'production' && ! $record->production_completed_at && (empty($record->deliverables) || $record->isReadyForProduction()) && Gate::allows('logProduction', $record) => JobOrderWorkflowActions::logProduction(),
            PanelAccess::canAccessFinanceSection() && $record->balance > 0 && Gate::allows('recordPayment', $record) => JobOrderActions::pay(),
            PanelAccess::canAccessWarehouseSection() && in_array((string) $record->status, ['production', 'completed'], true) && $record->production_mode === 'make_to_order' && $record->remaining_quantity > 0 => Action::make('create_dispatch')
                ->label('Create dispatch')->icon('heroicon-o-truck')
                ->url(DispatchResource::getUrl('create', ['job_order_id' => $record->id])),
            default => null,
        };
        $secondary = JobOrderActions::make();
        if ($primary) {
            $secondary->actions(array_filter($secondary->getActions(), fn ($action): bool => $action->getName() !== $primary->getName()));
        }

        return array_values(array_filter([$primary, $secondary]));
    }
}
