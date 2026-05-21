<?php

namespace App\Filament\Resources\AttendanceImports;

use App\Filament\Resources\AttendanceImports\Pages\ListAttendanceImports;
use App\Models\AttendanceImport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AttendanceImportResource extends Resource
{
    protected static ?string $model = AttendanceImport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUpTray;

    protected static string|UnitEnum|null $navigationGroup = 'Attendance';

    protected static ?int $navigationSort = 310;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('file_name')->searchable(),
                TextColumn::make('report_type')->badge(),
                TextColumn::make('period_start')->date(),
                TextColumn::make('period_end')->date(),
                TextColumn::make('status')->badge(),
                TextColumn::make('successful_rows')->numeric(),
                TextColumn::make('failed_rows')->numeric(),
                TextColumn::make('processed_at')->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAttendanceImports::route('/'),
        ];
    }
}
