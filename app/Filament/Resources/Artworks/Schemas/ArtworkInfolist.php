<?php

namespace App\Filament\Resources\Artworks\Schemas;

use App\Support\PrivateStorage;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ArtworkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Artwork Overview')
                    ->schema([
                        TextEntry::make('jobOrder.job_order_number')
                            ->label('Job Order')
                            ->weight('bold')
                            ->color('primary'),
                        IconEntry::make('is_approved')
                            ->label('Production Approved')
                            ->boolean()
                            ->trueColor('success')
                            ->falseColor('warning'),
                        TextEntry::make('uploader.name')
                            ->label('Uploaded By')
                            ->icon('heroicon-m-user'),
                        TextEntry::make('created_at')
                            ->label('Uploaded Date')
                            ->dateTime(),
                        TextEntry::make('filename')
                            ->label('Download Artwork')
                            ->formatStateUsing(fn ($state) => '📥 Download Artwork')
                            ->url(fn ($record) => PrivateStorage::downloadUrl($record->filename, now()->addMinutes(60)))
                            ->openUrlInNewTab()
                            ->color('primary')
                            ->icon('heroicon-m-arrow-down-tray')
                            ->extraAttributes(['class' => 'p-4 bg-gray-50 rounded-xl border border-dashed border-gray-300 flex items-center justify-center']),
                    ]),
            ]);
    }
}
