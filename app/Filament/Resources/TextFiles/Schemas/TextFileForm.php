<?php

namespace App\Filament\Resources\TextFiles\Schemas;

use App\Models\JobOrderTask;
use App\Support\PrivateStorage;
use Filament\Forms\Components\BaseFileUpload;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get as UtilitiesGet;
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
                            ->live()
                            ->columnSpan(1),
                        Select::make('deliverable')
                            ->label('Deliverable')
                            ->options(fn (UtilitiesGet $get): array => self::textFileDeliverableOptions((int) $get('job_order_task_id')))
                            ->visible(fn (UtilitiesGet $get): bool => filled(self::textFileDeliverableOptions((int) $get('job_order_task_id'))))
                            ->required(fn (UtilitiesGet $get): bool => filled(self::textFileDeliverableOptions((int) $get('job_order_task_id'))))
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
                Toggle::make('is_approved')
                    ->label('Approved for Production')
                    ->default(false)
                    ->onColor('success')
                    ->offColor('danger')
                    ->live()
                    ->afterStateUpdated(function ($state, $record) {
                        if ($record && $record->jobOrderTask) {
                            $record->jobOrderTask->updateStatus();
                        }
                    }),
                Hidden::make('original_name'),
                Hidden::make('uploaded_by')
                    ->default(fn () => Auth::id()),
            ]);
    }

    private static function textFileDeliverableOptions(?int $taskId): array
    {
        if (! $taskId) {
            return [];
        }

        $task = JobOrderTask::find($taskId);

        if (! $task) {
            return [];
        }

        return $task->deliverableOptionsForType('text_file');
    }
}
