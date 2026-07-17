<?php

namespace App\Filament\Resources\CostEstimates\Actions;

use App\Models\CostEstimate;
use App\Models\Partner;
use App\Services\Costing\CostEstimateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;

class CostEstimateActions
{
    public static function make(bool $includeDelete = false): ActionGroup
    {
        return ActionGroup::make(array_filter([
            self::edit(),
            self::finalize(),
            self::createProforma(),
            $includeDelete ? self::delete() : null,
        ]));
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->color('gray')
            ->visible(fn (CostEstimate $record): bool => $record->isEditable());
    }

    public static function finalize(): Action
    {
        return Action::make('finalize')
            ->label('Finalize')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (CostEstimate $record): bool => $record->status === 'draft')
            ->action(function (CostEstimate $record): void {
                app(CostEstimateService::class)->finalize($record);
                Notification::make()->title('Estimate finalized')->success()->send();
            });
    }

    public static function createProforma(): Action
    {
        return Action::make('create_proforma')
            ->label('Create Proforma')
            ->icon('heroicon-o-document-text')
            ->color('gray')
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
            });
    }

    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (CostEstimate $record): bool => $record->status === 'draft');
    }
}
