<?php

namespace App\Filament\Resources\AttendanceImports\Pages;

use App\Filament\Resources\AttendanceImports\AttendanceImportResource;
use App\Services\Hr\ImportAttendanceSegmentCsv;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListAttendanceImports extends ListRecords
{
    protected static string $resource = AttendanceImportResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importAttendanceCsv')
                ->label('Import Attendance CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->schema([
                    FileUpload::make('attendance_file')
                        ->label('Attendance CSV')
                        ->disk('local')
                        ->directory('attendance-imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $result = app(ImportAttendanceSegmentCsv::class)->handle(
                        storage_path('app/private/'.$data['attendance_file']),
                        auth()->id(),
                    );

                    Notification::make()
                        ->title('Attendance imported')
                        ->body("{$result['import']->successful_rows} rows imported, {$result['import']->failed_rows} rows failed.")
                        ->success()
                        ->send();
                }),
        ];
    }
}
