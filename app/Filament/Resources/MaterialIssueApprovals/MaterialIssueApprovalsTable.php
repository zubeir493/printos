<?php

namespace App\Filament\Resources\MaterialIssueApprovals;

use App\Filament\Tables\Filters\DateRangeFilter;
use App\Services\MaterialIssueService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MaterialIssueApprovalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('materialRequest.jobOrderTask.name')
                    ->label('Task')
                    ->weight('bold')
                    ->description(fn ($record) => $record->materialRequest->jobOrderTask->jobOrder->job_order_number)
                    ->searchable(),
                TextColumn::make('requester.name')
                    ->label('Requested')
                    ->weight('medium')
                    ->prefix('by ')
                    ->description(fn ($record) => $record->created_at->since())
                    ->placeholder('System'),
                TextColumn::make('materialRequest.inventoryItem.name')
                    ->label('Material')
                    ->searchable(),
                TextColumn::make('quantity')
                    ->numeric(decimalPlaces: 2)
                    ->suffix(fn ($record) => ' '.$record->materialRequest->inventoryItem->unit),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        default => 'warning',
                    }),
            ])
            ->filters([
                DateRangeFilter::make('request_date_range', 'created_at', 'Request date'),

                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                    ]),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('approve')
                        ->color('gray')
                        ->icon('heroicon-o-check-circle')
                        ->visible(fn ($record) => $record->status === 'pending')
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('decision_notes')
                                ->label('Approval Note')
                                ->rows(3),
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                app(MaterialIssueService::class)->approve($record, auth()->user(), $data['decision_notes'] ?? null);

                                Notification::make()
                                    ->title('Over-issue approved')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Unable to approve over-issue')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                    Action::make('reject')
                        ->color('gray')
                        ->icon('heroicon-o-x-circle')
                        ->visible(fn ($record) => $record->status === 'pending')
                        ->requiresConfirmation()
                        ->form([
                            Textarea::make('decision_notes')
                                ->label('Reason')
                                ->rows(3)
                                ->required(),
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                app(MaterialIssueService::class)->reject($record, auth()->user(), $data['decision_notes'] ?? null);

                                Notification::make()
                                    ->title('Over-issue rejected')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Unable to reject over-issue')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->persistent()
                                    ->send();
                            }
                        }),
                ]),
            ]);
    }
}
