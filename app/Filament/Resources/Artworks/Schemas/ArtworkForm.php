<?php

namespace App\Filament\Resources\Artworks\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;

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
                            ->relationship('jobOrderTask', 'name')
                            ->getOptionLabelFromRecordUsing(fn ($record) => "{$record->name} ({$record->jobOrder->job_order_number})")
                            ->required()
                            ->searchable()
                            ->preload()
                            ->columnSpan(1),
                        FileUpload::make('filename')
                            ->label('Artwork File')
                            ->disk('s3')
                            ->directory('artworks')
                            ->preserveFilenames()
                            ->maxSize(10240)
                            ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/tiff', 'image/webp'])
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
                                if (!$record || empty($record->filename)) return null;
                                $url = \Illuminate\Support\Facades\Storage::disk('s3')->temporaryUrl($record->filename, now()->addMinutes(60));
                                return new \Illuminate\Support\HtmlString(
                                    '<div class="p-4 bg-gray-50 rounded-xl border border-dashed border-gray-300 flex items-center justify-center">' .
                                    '<a href="' . $url . '" target="_blank" class="text-primary-600 hover:text-primary-800 font-medium flex items-center gap-2">' .
                                    'Click to Download' .
                                    '</a>' .
                                    '</div>'
                                );
                            })
                    ])->columnSpanFull(),
                Hidden::make('uploaded_by')
                    ->default(fn() => Auth::id()),
            ]);
    }
}
