<?php

namespace App\Filament\Resources\JobOrderTasks\RelationManagers;

use App\Support\PrivateStorage;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\IconSize;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ArtworksRelationManager extends RelationManager
{
    protected static string $relationship = 'artworks';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Artwork Details')
                    ->description('Upload and manage creative assets for this job.')
                    ->schema([
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
                            ->previewable(false)
                            ->columnSpanFull()
                            ->required(),
                        Grid::make(2)
                            ->schema([
                                Toggle::make('is_approved')
                                    ->label('Approved for Production')
                                    ->default(false)
                                    ->onColor('success')
                                    ->offColor('danger'),
                                Hidden::make('uploaded_by')
                                    ->default(fn () => Auth::id()),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('filename')
            ->columns([
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
                    })
                    ->size(IconSize::Large),
                TextColumn::make('filename')
                    ->label('File Name')
                    ->formatStateUsing(fn ($state) => basename($state))
                    ->description(fn ($record) => $record->uploader?->name ? "Uploaded by {$record->uploader->name}" : 'Unknown Uploader')
                    ->searchable(),
                IconColumn::make('is_approved')
                    ->label('Status')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-clock')
                    ->trueColor('success')
                    ->falseColor('warning'),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime()
                    ->since()
                    ->color('gray'),
            ])
            ->filters([
                TernaryFilter::make('is_approved')
                    ->label('Approval Status'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Add Artwork')
                    ->icon('heroicon-m-plus'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make(),
                    Action::make('approve')
                        ->label('Approve')
                        ->icon('heroicon-m-check-badge')
                        ->color('success')
                        ->hidden(fn ($record) => $record->is_approved)
                        ->action(fn ($record) => $record->update(['is_approved' => true])),
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
