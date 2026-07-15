<?php

namespace App\Filament\Resources\JobOrderTasks\Actions;

use App\Models\User;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;

class JobOrderTaskWorkflowActions
{
    public static function assignDesigner(): Action
    {
        return Action::make('assign_designer')
            ->label('Assign Designer')
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->visible(fn ($record): bool => blank($record->designer_id)
                && ! in_array($record->status, ['completed', 'cancelled'], true)
                && ! in_array($record->jobOrder->status, ['completed', 'draft'], true)
                && in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations'], true))
            ->form([
                Select::make('designer_id')
                    ->label('Designer')
                    ->options(fn (): array => User::query()
                        ->where('role', UserRole::Design->value)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required(),
                Textarea::make('instructions')
                    ->label('Brief / Instructions')
                    ->placeholder('Describe what needs to be designed, any specific requirements, references, or deadlines...')
                    ->rows(4)
                    ->helperText('This will be included in the notification sent to the designer and saved on the task.'),
            ])
            ->action(function (array $data, $record): void {
                $record->update([
                    'designer_id' => $data['designer_id'],
                    'instructions' => $data['instructions'] ?? null,
                ]);

                $record->updateStatus();

                Notification::make()
                    ->title('Designer assigned')
                    ->success()
                    ->send();
            });
    }

    public static function assignTypist(): Action
    {
        return Action::make('assign_typist')
            ->label('Assign Typist')
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(fn ($record): bool => blank($record->typist_id)
                && ! in_array($record->status, ['completed', 'cancelled'], true)
                && in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations'], true))
            ->form([
                Select::make('typist_id')
                    ->label('Typist')
                    ->options(fn (): array => User::query()
                        ->where('role', UserRole::Typist->value)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->required(),
            ])
            ->action(function (array $data, $record): void {
                $record->update(['typist_id' => $data['typist_id']]);
                $record->updateStatus();

                Notification::make()
                    ->title('Typist assigned')
                    ->success()
                    ->send();
            });
    }

    public static function sendToProduction(): Action
    {
        return Action::make('send_to_production')
            ->label('Send to Production')
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn ($record): bool => in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations'], true)
                && ! in_array($record->status, ['completed', 'cancelled', 'production'], true)
                && ! in_array($record->jobOrder->status, ['completed', 'draft'], true))
            ->requiresConfirmation()
            ->modalDescription('Move this task to production once the required files are ready?')
            ->action(function ($record): void {
                $record->update(['status' => 'production']);

                Notification::make()
                    ->title('Task sent to production')
                    ->success()
                    ->send();
            });
    }
}
