<?php

namespace App\Filament\Resources\CostEstimates\Pages;

use App\Filament\Resources\CostEstimates\CostEstimateResource;
use App\Filament\Resources\Proformas\ProformaResource;
use App\Models\Partner;
use App\Services\CostEstimates\CostEstimateCalculator;
use App\Services\Proformas\ProformaWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditCostEstimate extends EditRecord
{
    protected static string $resource = CostEstimateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('create_proforma')
                ->label('Create Proforma')
                ->icon('heroicon-o-document-plus')
                ->color('primary')
                ->visible(fn (): bool => $this->record->status === 'draft')
                ->schema([
                    Select::make('partner_id')
                        ->label('Customer')
                        ->options(fn (): array => Partner::query()
                            ->where('is_customer', true)
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn () => $this->record->partner_id)
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
                    TextInput::make('email_recipient')
                        ->email()
                        ->default(fn () => $this->record->partner?->email),
                    Textarea::make('remarks')
                        ->default(fn () => $this->record->remarks),
                ])
                ->action(function (array $data): void {
                    $proforma = app(ProformaWorkflowService::class)->createFromEstimate($this->record, $data);

                    Notification::make()
                        ->title('Proforma Created')
                        ->body($proforma->proforma_number.' is ready.')
                        ->success()
                        ->send();

                    $this->redirect(ProformaResource::getUrl('edit', ['record' => $proforma]));
                }),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $calculation = app(CostEstimateCalculator::class)->calculate(
            $data['job_type'],
            $data['tasks'] ?? [],
        );

        $data['tasks'] = $calculation['tasks'];
        $data['subtotal'] = $calculation['subtotal'];
        $data['tax_amount'] = $calculation['tax_amount'];
        $data['total'] = $calculation['total'];

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotals();
    }
}
