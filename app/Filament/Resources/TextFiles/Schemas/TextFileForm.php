<?php

namespace App\Filament\Resources\TextFiles\Schemas;

use App\Support\PrivateStorage;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TextFileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->schema([
                        Select::make('job_order_task_id')
                            ->label('Task / Job Order')
                            ->relationship('jobOrderTask', 'name', fn ($query) => $query->with('jobOrder'))
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->jobOrder->job_order_number})")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
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
                            ->maxSize(51200) // 50 MB
                            ->previewable(false)
                            ->downloadable()
                            ->required()
                            ->afterStateUpdated(function ($state, callable $set) {
                                if ($state) {
                                    $set('original_name', is_string($state) ? basename($state) : $state->getClientOriginalName());
                                }
                            })
                            ->live()
                            ->columnSpanFull(),
                    ])->columnSpanFull(),
                Hidden::make('original_name'),
                Hidden::make('uploaded_by')
                    ->default(fn () => Auth::id()),
            ]);
    }
}
