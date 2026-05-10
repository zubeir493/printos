<?php

namespace App\Filament\Resources\TextFiles\Schemas;

use App\Models\JobOrder;
use App\Support\PrivateStorage;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

class TextFileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('job_order_id')
                    ->label('Job Order')
                    ->options(
                        JobOrder::where('production_mode', 'make_to_stock')
                            ->orderByDesc('id')
                            ->get()
                            ->mapWithKeys(fn ($jo) => [$jo->id => "{$jo->job_order_number} — {$jo->partner?->name}"])
                    )
                    ->searchable()
                    ->required(),
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
                Hidden::make('original_name'),
                Hidden::make('uploaded_by')
                    ->default(fn () => Auth::id()),
            ]);
    }
}
