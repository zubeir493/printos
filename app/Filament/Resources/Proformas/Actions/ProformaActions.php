<?php

namespace App\Filament\Resources\Proformas\Actions;

use App\Models\Proforma;
use App\Services\Proformas\ProformaPdfService;
use App\Services\Proformas\ProformaWorkflowService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

class ProformaActions
{
    public static function make(bool $includeDelete = false): ActionGroup
    {
        return ActionGroup::make(array_filter([
            self::download(),
            self::email(),
            self::approve(),
            self::createJobOrder(),
            self::edit(),
            $includeDelete ? self::delete() : null,
        ]));
    }

    public static function download(): Action
    {
        return Action::make('download')
            ->label('Download')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->url(fn (Proforma $record): ?string => app(ProformaPdfService::class)->downloadUrl($record))
            ->openUrlInNewTab();
    }

    public static function email(): Action
    {
        return Action::make('email')
            ->label('Email')
            ->icon('heroicon-o-envelope')
            ->color('gray')
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
            });
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Approve')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->visible(fn (Proforma $record): bool => in_array($record->status, ['draft', 'sent'], true))
            ->action(function (Proforma $record): void {
                $record->update([
                    'status' => 'approved',
                    'approved_at' => now(),
                    'approved_by' => auth()->id(),
                ]);

                Notification::make()->title('Proforma approved')->success()->send();
            });
    }

    public static function createJobOrder(): Action
    {
        return Action::make('create_job_order')
            ->label('Create Job Order')
            ->icon('heroicon-o-briefcase')
            ->color('gray')
            ->visible(fn (Proforma $record): bool => $record->canCreateJobOrder())
            ->action(function (Proforma $record): void {
                $jobOrder = app(ProformaWorkflowService::class)->createJobOrder($record);

                Notification::make()
                    ->title('Job order created')
                    ->body($jobOrder->job_order_number.' was created from '.$record->proforma_number.'.')
                    ->success()
                    ->send();
            });
    }

    public static function edit(): EditAction
    {
        return EditAction::make()
            ->color('gray')
            ->visible(fn (Proforma $record): bool => $record->status === 'draft');
    }

    public static function delete(): DeleteAction
    {
        return DeleteAction::make()
            ->visible(fn (Proforma $record): bool => $record->status === 'draft');
    }
}
