<?php

namespace App\Filament\Resources\JobOrders\RelationManagers;

use App\Filament\Support\PanelAccess;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\TextFile;
use App\Support\PrivateStorage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class TextFilesRelationManager extends RelationManager
{
    protected static string $relationship = 'textFiles';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function form(Schema $schema): Schema
    {
        $deliverableOptions = fn (): array => $this->getOwnerRecord()?->deliverableOptionsForType('text_file') ?? [];

        return $schema
            ->components([
                Select::make('deliverable')
                    ->label('Deliverable')
                    ->options($deliverableOptions)
                    ->visible(fn (): bool => filled($deliverableOptions()))
                    ->required(fn (): bool => filled($deliverableOptions()))
                    ->searchable()
                    ->preload()
                    ->placeholder('Select a text file deliverable'),
                FileUpload::make('filename')
                    ->label('Text File')
                    ->disk(config('filesystems.private_disk', 's3'))
                    ->visibility('private')
                    ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => PrivateStorage::uploadedFileInfo($component, $file, $storedFileNames))
                    ->getOpenableFileUrlUsing(fn (string $file): ?string => PrivateStorage::url($file))
                    ->getDownloadableFileUrlUsing(fn (string $file): ?string => PrivateStorage::downloadUrl($file))
                    ->directory('text-files')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    ])
                    ->maxSize(51200)
                    ->previewable(false)
                    ->downloadable()
                    ->required()
                    ->afterStateUpdated(function ($state, callable $set) {
                        if ($state) {
                            $set('original_name', is_string($state) ? basename($state) : $state->getClientOriginalName());
                        }
                    })
                    ->live(),
                Toggle::make('is_approved')->disabled(fn (): bool => ! PanelAccess::canManageJobOrders())->dehydrated(fn (): bool => PanelAccess::canManageJobOrders())
                    ->label('Approved for Production')
                    ->default(false)
                    ->onColor('success')
                    ->offColor('danger'),
                Hidden::make('job_order_id')
                    ->default(fn () => $this->getOwnerRecord()?->id),
                Hidden::make('uploaded_by')
                    ->default(fn () => Auth::id()),
                Hidden::make('original_name'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['jobOrder', 'uploader']))
            ->recordTitleAttribute('original_name')
            ->columns([
                TextColumn::make('original_name')
                    ->label('File Name')
                    ->searchable(),
                IconColumn::make('is_approved')
                    ->label('Approved')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('uploader.name')
                    ->label('Uploaded By')
                    ->placeholder('—'),
                TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime()
                    ->since(),
            ])
            ->filters([
                DateRangeFilter::make('uploaded_date_range', 'created_at', 'Uploaded date'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->authorize(fn (): bool => Gate::allows('uploadToJob', [TextFile::class, $this->getOwnerRecord()]))
                    ->label('Add Text File'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('approve')
                        ->label('Approve')->icon('heroicon-m-check-badge')
                        ->authorize(fn ($record): bool => Gate::allows('approve', $record))
                        ->hidden(fn ($record): bool => $record->is_approved)
                        ->action(fn ($record) => $record->update(['is_approved' => true])),
                    Action::make('download')
                        ->label('Download')
                        ->icon('heroicon-m-arrow-down-tray')
                        ->url(fn ($record): ?string => PrivateStorage::downloadUrl($record->filename, now()->addMinutes(60)))
                        ->openUrlInNewTab(),
                    DeleteAction::make(),
                ]),
            ]);
    }
}
