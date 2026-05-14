<?php

namespace App\Filament\Resources\ProductionPlans\Pages;

use App\Filament\Resources\ProductionPlans\ProductionPlanResource;
use App\Filament\Resources\ProductionReports\ProductionReportResource;
use App\Models\ProductionReport;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionPlan extends ViewRecord
{
    protected static string $resource = ProductionPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve Plan')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn ($record) => $record->status === 'draft')
                ->action(function ($record) {
                    $record->update(['status' => 'approved']);
                    $this->record->refresh();

                    Notification::make()
                        ->title('Production plan approved')
                        ->success()
                        ->send();
                }),
            Action::make('report_week')
                ->label('Report Week')
                ->icon('heroicon-o-clipboard-document-check')
                ->color('success')
                ->visible(fn ($record) => $record->status === 'approved' && ! ProductionReport::where('production_plan_id', $record->id)->exists())
                ->action(function ($record) {
                    $report = ProductionReport::create([
                        'production_plan_id' => $record->id,
                        'status' => 'draft',
                    ]);

                    foreach ($record->machines as $planMachine) {
                        $reportMachine = $report->machines()->create([
                            'production_plan_machine_id' => $planMachine->id,
                        ]);

                        foreach ($planMachine->items as $item) {
                            $reportMachine->items()->create([
                                'production_plan_item_id' => $item->id,
                                'date' => now(),
                                'actual_quantity' => $item->planned_quantity,
                                'plates_used' => $item->planned_plates,
                                'rounds' => $item->planned_rounds,
                            ]);
                        }
                    }

                    Notification::make()
                        ->title('Production report generated')
                        ->success()
                        ->send();

                    $this->redirect(ProductionReportResource::getUrl('edit', ['record' => $report]));
                }),
            EditAction::make()
                ->visible(fn ($record) => $record->status === 'draft'),
        ];
    }
}
