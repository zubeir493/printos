<?php

namespace App\Filament\Resources\JobOrders\Actions;

use App\Filament\Support\MaterialRequestActionForms;
use App\Models\InventoryItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\JobOrders\JobOrderWorkflow;
use App\Services\JobOrders\LogJobOrderProduction;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Gate;

class JobOrderWorkflowActions
{
    public static function assignDesigner(): Action
    {
        return Action::make('assign_designer')
            ->label('Assign Designer')->authorize(fn ($record): bool => (string) $record->status === 'design' && Gate::allows('manageWorkflow', $record))
            ->icon('heroicon-o-user-plus')
            ->color('gray')
            ->visible(fn ($record): bool => blank($record->designer_id)
                && (string) $record->status === 'design'
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
            ->label('Assign Typist')->authorize(fn ($record): bool => (string) $record->status === 'design' && Gate::allows('manageWorkflow', $record))
            ->icon('heroicon-o-document-text')
            ->color('gray')
            ->visible(fn ($record): bool => blank($record->typist_id)
                && (string) $record->status === 'design'
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
            ->label('Send to Production')->authorize(fn ($record): bool => (string) $record->status === 'design' && Gate::allows('manageWorkflow', $record))
            ->icon('heroicon-o-arrow-up-tray')
            ->color('gray')
            ->visible(fn ($record): bool => in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations'], true)
                && (string) $record->status === 'design'
                && (empty($record->deliverables) || $record->isReadyForProduction())
            )
            ->requiresConfirmation()
            ->modalDescription('Move this job order to production once the required files are ready?')
            ->action(function ($record): void {
                app(JobOrderWorkflow::class)->transition($record, 'production');

                Notification::make()
                    ->title('Job order sent to production')
                    ->success()
                    ->send();
            });
    }

    public static function logProduction(): Action
    {
        return Action::make('log_production')->label('Log Production')->icon('heroicon-o-archive-box-arrow-down')->color('gray')
            ->authorize(fn ($record): bool => (string) $record->status === 'production' && ! $record->production_completed_at && Gate::allows('logProduction', $record))
            ->visible(fn ($record): bool => (string) $record->status === 'production' && ! $record->production_completed_at
                && (empty($record->deliverables) || $record->isReadyForProduction())
                && in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations', 'production'], true))
            ->schema([
                Select::make('warehouse_id')->options(fn () => Warehouse::pluck('name', 'id'))->required(),
                TextInput::make('quantity')->numeric()->minValue(0.0001)->required()
                    ->default(fn ($record) => $record->remaining_production_quantity),
                Select::make('existing_inventory_item_id')->label('Finished Good')
                    ->options(fn () => InventoryItem::where('type', 'finished_good')->pluck('name', 'id'))
                    ->searchable()->visible(fn ($record): bool => $record->production_mode === 'make_to_stock')
                    ->disabled(fn ($record): bool => filled($record->output_inventory_item_id))
                    ->default(fn ($record) => $record->output_inventory_item_id),
            ])
            ->action(function (array $data, $record): void {
                app(LogJobOrderProduction::class)->handle($record, (int) $data['warehouse_id'], (float) $data['quantity'], isset($data['existing_inventory_item_id']) ? (int) $data['existing_inventory_item_id'] : null);
                Notification::make()->title('Production logged')->success()->send();
            });
    }

    public static function requestMaterials(): Action
    {
        return Action::make('request_materials')->modalWidth('lg')->label('Request Materials')->icon('heroicon-o-document-plus')->color('gray')
            ->authorize(fn ($record): bool => in_array((string) $record->status, ['design', 'production'], true) && ! $record->production_completed_at && Gate::allows('logProduction', $record))
            ->visible(fn ($record): bool => in_array((string) $record->status, ['design', 'production'], true)
                && ! $record->production_completed_at
                && in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations', 'production'], true))
            ->schema(fn ($record): array => MaterialRequestActionForms::requestMaterialsForm($record))
            ->action(function (array $data, $record): void {
                MaterialRequestActionForms::createMaterialRequests($record, $data);
                Notification::make()->title('Materials requested')->success()->send();
            });
    }
}
