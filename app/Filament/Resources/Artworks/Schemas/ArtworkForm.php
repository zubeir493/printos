<?php

namespace App\Filament\Resources\Artworks\Schemas;

use App\Models\JobOrderTask;
use App\Support\PrivateStorage;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get as UtilitiesGet;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;

class ArtworkForm
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
                            ->live()
                            ->columnSpan(1),
                        Select::make('deliverable')
                            ->label('Deliverable')
                            ->options(fn (UtilitiesGet $get): array => self::artworkDeliverableOptions((int) $get('job_order_task_id')))
                            ->visible(fn (UtilitiesGet $get): bool => filled(self::artworkDeliverableOptions((int) $get('job_order_task_id'))))
                            ->required(fn (UtilitiesGet $get): bool => filled(self::artworkDeliverableOptions((int) $get('job_order_task_id'))))
                            ->searchable()
                            ->preload()
                            ->placeholder('Select an artwork deliverable'),
                        FileUpload::make('filename')
                            ->label('Artwork File')
                            ->disk(config('filesystems.private_disk', 's3'))
                            ->visibility('private')
                            ->getUploadedFileUsing(fn (BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array => PrivateStorage::uploadedFileInfo($component, $file, $storedFileNames))
                            ->getOpenableFileUrlUsing(fn (string $file): ?string => PrivateStorage::url($file))
                            ->getDownloadableFileUrlUsing(fn (string $file): ?string => PrivateStorage::downloadUrl($file))
                            ->directory('artworks')
                            ->preserveFilenames()
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/tiff', 'image/webp'])
                            ->maxSize(102400)
                            ->previewable(false)
                            ->required()
                            ->columnSpanFull(),
                    ])->columnSpanFull(),
                Flex::make([
                    Toggle::make('is_approved')
                        ->label('Production Ready')
                        ->onColor('success')
                        ->offColor('danger')
                        ->required()
                        ->columnSpan(1)
                        ->live()
                        ->afterStateUpdated(function ($state, callable $get, callable $set, $record) {
                            if ($record && $record->jobOrderTask) {
                                $record->jobOrderTask->updateStatus();
                            }
                        }),
                    Placeholder::make('download_link')
                        ->label('')
                        ->hidden(fn ($record) => empty($record?->filename))
                        ->content(function ($record) {
                            if (! $record || empty($record->filename)) {
                                return null;
                            }
                            $url = PrivateStorage::downloadUrl($record->filename, now()->addMinutes(60));

                            return new HtmlString(
                                '<div class="p-4 bg-gray-50 rounded-xl border border-dashed border-gray-300 flex items-center justify-center">'.
                                '<a href="'.$url.'" target="_blank" class="text-primary-600 hover:text-primary-800 font-medium flex items-center gap-2">'.
                                'Click to Download'.
                                '</a>'.
                                '</div>'
                            );
                        }),
                ])->columnSpanFull(),
                Hidden::make('uploaded_by')
                    ->default(fn () => Auth::id()),
            ]);
    }

    private static function artworkDeliverableOptions(?int $taskId): array
    {
        if (! $taskId) {
            return [];
        }

        $task = JobOrderTask::find($taskId);

        if (! $task) {
            return [];
        }

        return $task->deliverableOptionsForType('artwork');
    }
}
