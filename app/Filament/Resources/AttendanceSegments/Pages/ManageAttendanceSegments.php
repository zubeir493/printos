<?php

namespace App\Filament\Resources\AttendanceSegments\Pages;

use App\Filament\Exports\AttendanceSegmentExporter;
use App\Filament\Imports\AttendanceSegmentImporter;
use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Models\Employee;
use App\Services\Hr\RebuildAttendanceDailySummaries;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\Rules\File;
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
            ?->when($this->selectedEmployeeId, fn(Builder $query): Builder => $query->where('employee_id', $this->selectedEmployeeId));
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->after(fn($record) => app(RebuildAttendanceDailySummaries::class)->forSegments(collect([$record]))),
            ActionGroup::make([
                ExportAction::make()
                    ->exporter(AttendanceSegmentExporter::class),
                ImportAction::make('importAttendanceCsv')
                    ->label('Import attendance CSV')
                    ->importer(AttendanceSegmentImporter::class)
                    ->fileRules([
                        File::types(['csv', 'txt'])->max(10240),
                    ]),
            ]),
        ];
    }
}
