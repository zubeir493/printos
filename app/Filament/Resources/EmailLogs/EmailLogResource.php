<?php

namespace App\Filament\Resources\EmailLogs;

use App\Filament\Resources\EmailLogs\Pages\ManageEmailLogs;
use App\Filament\Tables\Filters\DateRangeFilter;
use App\Models\EmailLog;
use BackedEnum;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class EmailLogResource extends Resource
{
    protected static ?string $model = EmailLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Emails';

    protected static ?int $navigationSort = 910;

    protected static ?string $recordTitleAttribute = 'recipient_email';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('recipient_email'),
                TextEntry::make('subject')
                    ->placeholder('-'),
                TextEntry::make('message')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('artwork_id')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('sent_by')
                    ->numeric()
                    ->placeholder('-'),
                TextEntry::make('sent_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('recipient_email')
            ->columns([
                TextColumn::make('recipient_email')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('subject')
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('sender.name')
                    ->label('Sent By')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('sent_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('sent_by')
                    ->label('Sender')
                    ->relationship('sender', 'name')
                    ->searchable()
                    ->preload(),
                DateRangeFilter::make('sent_date_range', 'sent_at', 'Sent date'),
            ])
            ->recordActions([])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageEmailLogs::route('/'),
        ];
    }
}
