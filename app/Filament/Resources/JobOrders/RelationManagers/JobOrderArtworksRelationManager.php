<?php

namespace App\Filament\Resources\JobOrders\RelationManagers;

use App\Models\EmailLog;
use App\Support\PrivateStorage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class JobOrderArtworksRelationManager extends RelationManager
{
    protected static string $relationship = 'artworks';

    protected static ?string $title = 'Artworks Overview';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Artwork Details')
                    ->schema([
                        Select::make('job_order_task_id')
                            ->label('Task')
                            ->options(fn ($record) => $this->getOwnerRecord()->jobOrderTasks->pluck('name', 'id'))
                            ->required(),
                        FileUpload::make('filename')
                            ->label('Artwork File')
                            ->disk('s3')
                            ->visibility('private')
                            ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => PrivateStorage::uploadedFileInfo($component, $file, $storedFileNames))
                            ->getOpenableFileUrlUsing(fn (string $file): ?string => PrivateStorage::url($file))
                            ->getDownloadableFileUrlUsing(fn (string $file): ?string => PrivateStorage::downloadUrl($file))
                            ->directory('artworks')
                            ->preserveFilenames()
                            ->image()
                            ->imageEditor()
                            ->required(),
                        Hidden::make('uploaded_by')
                            ->default(fn () => Auth::id()),
                        Toggle::make('is_approved')
                            ->label('Approved'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('filename')
            ->columns([
                TextColumn::make('jobOrderTask.name')
                    ->label('Task'),
                IconColumn::make('type')
                    ->label('Type')
                    ->getStateUsing(fn ($record) => strtolower(pathinfo($record->filename, PATHINFO_EXTENSION)))
                    ->icon(fn ($state) => match ($state) {
                        'pdf' => 'heroicon-s-document-text',
                        'ai', 'eps', 'psd' => 'heroicon-s-paint-brush',
                        'png', 'jpg', 'jpeg', 'webp' => 'heroicon-s-photo',
                        'zip', 'rar' => 'heroicon-s-archive-box',
                        default => 'heroicon-s-document',
                    })
                    ->color(fn ($state) => match ($state) {
                        'pdf' => 'danger',
                        'ai', 'eps', 'psd' => 'warning',
                        'png', 'jpg', 'jpeg', 'webp' => 'success',
                        'zip', 'rar' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('filename')
                    ->label('File Name')
                    ->formatStateUsing(fn ($state) => basename($state))
                    ->searchable(),
                IconColumn::make('is_approved')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
            ])
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('Approval Status'),
            ])
            ->headerActions([
                //
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-m-check-badge')
                        ->color('gray')
                        ->hidden(fn ($record) => $record->is_approved)
                        ->action(fn ($record) => $record->update(['is_approved' => true])),
                    Action::make('sendEmail')
                        ->label('Email Link')
                        ->icon('heroicon-m-envelope')
                        ->color('gray')
                        ->form([
                            TextInput::make('recipient_email')
                                ->label('Recipient Email')
                                ->email()
                                ->required(),
                            TextInput::make('subject')
                                ->label('Subject')
                                ->default(fn ($record) => 'Artwork Download Link: '.basename($record->filename)),
                            Textarea::make('message')
                                ->label('Message')
                                ->rows(3),
                        ])
                        ->action(function ($record, array $data) {
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
                        }),
                    DeleteAction::make(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
