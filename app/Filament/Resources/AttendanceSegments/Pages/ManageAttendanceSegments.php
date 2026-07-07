<?php

namespace App\Filament\Resources\AttendanceSegments\Pages;

use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Models\Employee;
use App\Services\Hr\ImportAttendanceSegmentCsv;
use App\Services\Hr\RebuildAttendanceDailySummaries;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Url;

class ManageAttendanceSegments extends ManageRecords
{
    protected static string $resource = AttendanceSegmentResource::class;

    #[Url(as: 'employee')]
    public ?int $selectedEmployeeId = null;

    public function mount(): void
    {
        parent::mount();

        $this->selectedEmployeeId ??= Employee::query()
            ->orderBy('first_name')
            ->value('id');
    }

    public function updatedSelectedEmployeeId(): void
    {
        $this->resetPage();
    }

    protected function getTableQuery(): Builder|Relation|null
    {
        return parent::getTableQuery()
            ?->when($this->selectedEmployeeId, fn (Builder $query): Builder => $query->where('employee_id', $this->selectedEmployeeId));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importAttendanceCsv')
                ->label('Import attendance')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalHeading('Import attendance CSV')
                ->modalDescription('Upload the attendance segment CSV from the attendance device. Import results will be shown here when processing finishes.')
                ->schema([
                    FileUpload::make('attendance_file')
                        ->label('Attendance CSV')
                        ->disk('local')
                        ->directory('attendance-imports')
                        ->acceptedFileTypes(['text/csv', 'text/plain', 'application/vnd.ms-excel'])
                        ->maxSize(5120)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    $result = app(ImportAttendanceSegmentCsv::class)->handle(
                        storage_path('app/private/'.$data['attendance_file']),
                        auth()->id(),
                    );
                    $import = $result['import'];
                    $period = $result['period_start'] && $result['period_end']
                        ? " Period: {$result['period_start']} to {$result['period_end']}."
                        : '';

                    Notification::make()
                        ->title($import->failed_rows > 0 ? 'Attendance imported with issues' : 'Attendance imported')
                        ->body("{$import->successful_rows} rows imported, {$import->failed_rows} rows failed.{$period}")
                        ->color($import->failed_rows > 0 ? 'warning' : 'success')
                        ->icon($import->failed_rows > 0 ? 'heroicon-o-exclamation-triangle' : 'heroicon-o-check-circle')
                        ->send();

                    $this->resetTable();
                }),
            CreateAction::make()
                ->after(fn ($record) => app(RebuildAttendanceDailySummaries::class)->forSegments(collect([$record]))),
        ];
    }
}
