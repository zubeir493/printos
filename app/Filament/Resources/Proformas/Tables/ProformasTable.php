<?php

namespace App\Filament\Resources\Proformas\Tables;

use App\Filament\Resources\Proformas\ProformaResource;
use App\Filament\Support\PanelAccess;
use App\Models\Proforma;
use App\Services\Proformas\ProformaPdfService;
use App\Services\Proformas\ProformaWorkflowService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class ProformasTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('proforma_number')
                    ->label('Proforma #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary')
                    ->description(fn (Proforma $record): string => $record->partner?->name ?? 'Internal Job'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'sent' => 'info',
                        'approved' => 'success',
                        'job_order_created' => 'success',
                        'expired', 'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->value()),
                TextColumn::make('expiry_date')
                    ->date()
                    ->sortable()
                    ->color(fn (Proforma $record): ?string => $record->expiry_date->isPast() && ! in_array($record->status, ['job_order_created', 'cancelled'], true) ? 'danger' : null),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->visible(fn () => PanelAccess::canSeeMoneyValues())
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'sent' => 'Sent',
                        'approved' => 'Approved',
                        'job_order_created' => 'Job Order Created',
                        'expired' => 'Expired',
                        'cancelled' => 'Cancelled',
                    ]),
                SelectFilter::make('job_type')
                    ->options([
                        'books' => 'Books',
                        'packages' => 'Packages',
                        'labels' => 'Labels',
                        'vouchers' => 'Vouchers',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (Proforma $record): string => ProformaResource::getUrl('view', ['record' => $record]))
            ->recordActions([
                ActionGroup::make([
                    Action::make('download')
                        ->label('Download')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->url(fn (Proforma $record): ?string => app(ProformaPdfService::class)->downloadUrl($record))
                        ->openUrlInNewTab(),
                    Action::make('email')
                        ->label('Email')
                        ->icon('heroicon-o-envelope')
                        ->schema([
                            TextInput::make('email')
                                ->email()
                                ->required()
                                ->default(fn (Proforma $record): ?string => $record->email_recipient ?? $record->partner?->email),
                            Textarea::make('message')->rows(3),
                        ])
                        ->action(function (array $data, Proforma $record): void {
                            try {
                                app(ProformaPdfService::class)->email($record, $data['email'], $data['message'] ?? null);

                                Notification::make()->title('Proforma emailed')->success()->send();
                            } catch (\Throwable $e) {
                                Notification::make()
                                    ->title('Email failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
                        }),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (Proforma $record): bool => in_array($record->status, ['draft', 'sent'], true))
                        ->action(function (Proforma $record): void {
                            $record->update([
                                'status' => 'approved',
                                'approved_at' => now(),
                                'approved_by' => auth()->id(),
                            ]);

                            Notification::make()->title('Proforma approved')->success()->send();
                        }),
                    Action::make('create_job_order')
                        ->label('Create Job Order')
                        ->icon('heroicon-o-briefcase')
                        ->color('primary')
                        ->visible(fn (Proforma $record): bool => $record->canCreateJobOrder())
                        ->action(function (Proforma $record): void {
                            $jobOrder = app(ProformaWorkflowService::class)->createJobOrder($record);

                            Notification::make()
                                ->title('Job order created')
                                ->body($jobOrder->job_order_number.' was created from '.$record->proforma_number.'.')
                                ->success()
                                ->send();
                        }),
                    EditAction::make()
                        ->visible(fn (Proforma $record): bool => $record->status === 'draft'),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
