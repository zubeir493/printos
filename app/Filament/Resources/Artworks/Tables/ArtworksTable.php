<?php

namespace App\Filament\Resources\Artworks\Tables;

use App\Filament\Tables\Filters\DateRangeFilter;
use App\Mail\ShareArtwork;
use App\Models\EmailLog;
use App\Models\JobOrderTask;
use App\Support\PrivateStorage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;

class ArtworksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('jobOrderTask.name')
                    ->label('Task')
                    ->description(fn ($record) => $record->jobOrder?->job_order_number)
                    ->searchable()
                    ->weight('bold'),
                TextColumn::make('filename')
                    ->label('File Name')
                    ->formatStateUsing(fn ($state) => basename($state))
                    ->limit(50)
                    ->description(fn ($record) => $record->uploader?->name ? "Uploaded by {$record->uploader->name}" : 'System')
                    ->searchable(),
                IconColumn::make('is_approved')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('created_at')
                    ->label('Date Uploaded')
                    ->dateTime()
                    ->since()
                    ->color('gray')
                    ->sortable(),
            ])
            ->filters([
                DateRangeFilter::make('uploaded_date_range', 'created_at', 'Uploaded date'),

                SelectFilter::make('job_order_task_id')
                    ->label('Task')
                    ->options(fn () => JobOrderTask::query()
                        ->with('jobOrder')
                        ->get()
                        ->mapWithKeys(fn ($task) => [
                            $task->id => "{$task->name} (#{$task->jobOrder->job_order_number})",
                        ])
                    )
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_approved')
                    ->label('Approval Status'),
            ])
            ->recordActions([
                ActionGroup::make([
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-m-check-badge')
                        ->color('gray')
                        ->hidden(fn ($record) => $record->is_approved)
                        ->visible(fn () => in_array(Filament::getCurrentPanel()?->getId(), ['admin', 'operations']))
                        ->requiresConfirmation()
                        ->modalHeading('Approve Artwork')
                        ->modalDescription('Mark this artwork as approved? The task status will update automatically.')
                        ->action(fn ($record) => $record->update(['is_approved' => true])),
                    Action::make('download')
                        ->label('Download')
                        ->icon('heroicon-m-arrow-down-tray')
                        ->url(fn ($record): ?string => PrivateStorage::downloadUrl($record->filename, now()->addMinutes(60)))
                        ->openUrlInNewTab(),
                    Action::make('sendEmail')
                        ->label('Send Artwork')
                        ->icon('heroicon-m-envelope')
                        ->color('gray')
                        ->form([
                            TextInput::make('recipient_email')
                                ->label('Recipient Email')
                                ->email()
                                ->required(),
                            TextInput::make('subject')
                                ->label('Subject')
                                ->required()
                                ->default(fn ($record) => basename($record->filename)),
                            Textarea::make('message'),
                        ])
                        ->action(function ($record, array $data) {
                            try {
                                // Send the actual email
                                Mail::to($data['recipient_email'])
                                    ->send(new ShareArtwork($record, $data['recipient_email'], $data['message'] ?? null));

                                EmailLog::create([
                                    'recipient_email' => $data['recipient_email'],
                                    'subject' => $data['subject'],
                                    'message' => $data['message'],
                                    'artwork_id' => $record->id,
                                    'sent_by' => Auth::id(),
                                    'sent_at' => now(),
                                ]);

                                Notification::make()
                                    ->title('Email sent successfully')
                                    ->success()
                                    ->send();
                            } catch (\Exception $e) {
                                Notification::make()
                                    ->title('Email Failed')
                                    ->body($e->getMessage())
                                    ->danger()
                                    ->send();
                            }
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
