<?php

namespace App\Filament\Resources\CostEstimates\Tables;

use App\Models\CostEstimate;
use App\Models\Partner;
use App\Services\Costing\CostEstimateService;
use App\Support\Money;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CostEstimatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('estimate_number')
                    ->label('Estimate #')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->color('primary'),
                TextColumn::make('description')
                    ->searchable()
                    ->wrap(),
                TextColumn::make('job_type')
                    ->label('Service')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->value()),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'gray',
                        'finalized' => 'success',
                        'converted' => 'info',
                        'cancelled' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => str($state)->headline()->value()),
                TextColumn::make('total')
                    ->formatStateUsing(fn ($state): string => Money::format($state))
                    ->sortable(),
                TextColumn::make('unit_price')
                    ->formatStateUsing(fn ($state): string => number_format((float) $state, 4).' Birr')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('job_type')
                    ->options([
                        'labels' => 'Labels',
                        'packages' => 'Packages',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'finalized' => 'Finalized',
                        'converted' => 'Converted',
                        'cancelled' => 'Cancelled',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->visible(fn (CostEstimate $record): bool => $record->isEditable()),
                    Action::make('finalize')
                        ->label('Finalize')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->visible(fn (CostEstimate $record): bool => $record->status === 'draft')
                        ->action(function (CostEstimate $record): void {
                            app(CostEstimateService::class)->finalize($record);
                            Notification::make()->title('Estimate finalized')->success()->send();
                        }),
                    Action::make('create_proforma')
                        ->label('Create Proforma')
                        ->icon('heroicon-o-document-text')
                        ->color('primary')
                        ->visible(fn (CostEstimate $record): bool => in_array($record->status, ['draft', 'finalized'], true))
                        ->schema([
                            Select::make('partner_id')
                                ->label('Customer')
                                ->options(fn (): array => Partner::query()->where('is_customer', true)->pluck('name', 'id')->all())
                                ->searchable()
                                ->preload()
                                ->required(),
                            DatePicker::make('issue_date')
                                ->default(now())
                                ->required(),
                            DatePicker::make('expiry_date')
                                ->default(now()->addDays(15))
                                ->afterOrEqual('issue_date')
                                ->required(),
                        ])
                        ->action(function (array $data, CostEstimate $record): void {
                            $proforma = app(CostEstimateService::class)->createProforma($record, $data);
                            Notification::make()
                                ->title('Proforma created')
                                ->body($proforma->proforma_number)
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
