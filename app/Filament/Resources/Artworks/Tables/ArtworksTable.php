<?php

namespace App\Filament\Resources\Artworks\Tables;

use App\Mail\ShareArtwork;
use App\Models\EmailLog;
use App\Models\JobOrder;
use App\Support\PrivateStorage;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\IconSize;
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
                IconColumn::make('type')
                    ->label('Type')
                    ->getStateUsing(fn ($record) => strtolower(pathinfo($record->filename, PATHINFO_EXTENSION)))
                    ->icon(fn ($state) => match ($state) {
                        'pdf' => 'heroicon-s-document-text',
                        'ai', 'eps', 'psd' => 'heroicon-s-paint-brush',
                        'png', 'jpg', 'jpeg', 'webp' => 'heroicon-s-photo',
                        default => 'heroicon-s-document',
                    })
                    ->color(fn ($state) => match ($state) {
                        'pdf' => 'danger',
                        'ai', 'eps', 'psd' => 'warning',
                        'png', 'jpg', 'jpeg', 'webp' => 'success',
                        default => 'gray',
                    })
                    ->size(IconSize::Large),
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
                SelectFilter::make('job_order_task_id')
                    ->label('Filter By Task')
                    ->relationship('jobOrderTask', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('job_order_id')
                    ->label('Filter By Job Order')
                    ->options(JobOrder::pluck('job_order_number', 'id'))
                    ->query(function ($query, array $data) {
                        if ($data['value']) {
                            $query->whereHas('jobOrderTask', fn ($q) => $q->where('job_order_id', $data['value']));
                        }
                    })
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_approved')
                    ->label('Approval Status'),
            ])
            ->recordActions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->url(fn ($record): ?string => PrivateStorage::downloadUrl($record->filename, now()->addMinutes(60)))
                    ->openUrlInNewTab(),
                Action::make('sendEmail')
                    ->label('Send Artwork')
                    ->icon('heroicon-m-envelope')
                    ->color('success')
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
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
