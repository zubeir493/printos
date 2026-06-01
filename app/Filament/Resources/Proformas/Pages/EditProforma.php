<?php

namespace App\Filament\Resources\Proformas\Pages;

use App\Filament\Resources\Proformas\ProformaResource;
use App\Services\Proformas\ProformaPdfService;
use App\Services\Proformas\ProformaWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditProforma extends EditRecord
{
    protected static string $resource = ProformaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('download')
                ->label('Download')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn (): ?string => app(ProformaPdfService::class)->downloadUrl($this->record))
                ->openUrlInNewTab(),
            Action::make('email')
                ->label('Email')
                ->icon('heroicon-o-envelope')
                ->schema([
                    TextInput::make('email')
                        ->email()
                        ->required()
                        ->default(fn () => $this->record->email_recipient ?? $this->record->partner?->email),
                    Textarea::make('message')->rows(3),
                ])
                ->action(function (array $data): void {
                    app(ProformaPdfService::class)->email($this->record, $data['email'], $data['message'] ?? null);
                    Notification::make()->title('Proforma emailed')->success()->send();
                }),
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (): bool => in_array($this->record->status, ['draft', 'sent'], true))
                ->action(function (): void {
                    $this->record->update([
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
                ->visible(fn (): bool => $this->record->canCreateJobOrder())
                ->action(function (): void {
                    $jobOrder = app(ProformaWorkflowService::class)->createJobOrder($this->record);

                    Notification::make()
                        ->title('Job order created')
                        ->body($jobOrder->job_order_number.' was created.')
                        ->success()
                        ->send();
                }),
            DeleteAction::make()
                ->visible(fn (): bool => $this->record->status === 'draft'),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['tasks']);

        return $data;
    }

    protected function afterSave(): void
    {
        $this->record->recalculateTotals();
    }
}
