<?php

namespace App\Filament\Resources\ProductionPlans\Tables;

use App\Filament\Resources\ProductionReports\ProductionReportResource;
use App\Models\ProductionReport;
use Filament\Actions\Action as ActionsAction;
use Filament\Actions\EditAction as ActionsEditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Http\RedirectResponse;

class ProductionPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('week_start')
                    ->date()
                    ->sortable(),
                TextColumn::make('week_end')
                    ->date()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'approved' => 'success',
                        default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'approved' => 'Approved',
                    ]),
            ])
            ->actions([
                ActionsEditAction::make()
                    ->visible(fn ($record) => $record->status === 'draft'),
                ActionsAction::make('report_week')
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

                        return new RedirectResponse(
                            ProductionReportResource::getUrl('edit', ['record' => $report])
                        );
                    }),
            ])
            ->recordActions([
                ActionsEditAction::make()
                    ->visible(fn ($record) => $record->status === 'draft'),
            ])
            ->bulkActions([]);
    }
}
