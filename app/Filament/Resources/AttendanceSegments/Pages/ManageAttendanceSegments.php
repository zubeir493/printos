<?php

namespace App\Filament\Resources\AttendanceSegments\Pages;

use App\Filament\Exports\AttendanceSegmentExporter;
use App\Filament\Imports\AttendanceSegmentImporter;
use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Jobs\SyncDeviceAttendance;
use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceDeviceSync;
use App\Models\AttendanceSegment;
use App\Models\Employee;
use App\Services\Hr\RebuildAttendanceDailySummaries;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\Gate;
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
            ?->when($this->selectedEmployeeId, fn (Builder $query): Builder => $query->where('employee_id', $this->selectedEmployeeId));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncNow')->label('Sync')->icon('heroicon-o-arrow-path')
                ->authorize(fn (): bool => Gate::allows('create', AttendanceSegment::class))
                ->modalHeading('Attendance sync')->modalWidth('md')->modalSubmitActionLabel('Sync now')
                ->modalSubmitAction(fn (Action $action): Action => $action->disabled(! config('attendance_device.enabled')))
                ->schema(function (): array {
                    $state = AttendanceDeviceSync::where('device_id', config('attendance_device.id'))->first();

                    return [
                        TextEntry::make('status')->inlineLabel()->state(str($state?->status ?? 'never_synced')->headline()->toString())->badge(),
                        TextEntry::make('connection')->inlineLabel()->label('Device')->state(match (true) {
                            $state?->status === 'failed' => 'Unavailable',
                            ! $state?->last_connected_at => 'Unknown',
                            $state->last_connected_at->lt(now()->subMinutes(10)) => 'Stale',
                            default => 'Last connection OK',
                        }),
                        TextEntry::make('last_sync')->inlineLabel()->label('Last sync')->state($state?->last_successful_sync_at?->diffForHumans() ?? 'Never'),
                        TextEntry::make('processing')->inlineLabel()->state(config('attendance_device.processing_enabled') ? 'Enabled' : 'Paused'),
                        TextEntry::make('pending')->inlineLabel()->label('Pending punches')->state(AttendanceDevicePunch::where('device_id', config('attendance_device.id'))->whereNull('processed_at')->count()),
                        TextEntry::make('error')->inlineLabel()->state($state?->last_error)->visible(filled($state?->last_error))->columnSpanFull(),
                    ];
                })->action(function (): void {
                    Gate::authorize('create', AttendanceSegment::class);
                    if (! config('attendance_device.enabled')) {
                        return;
                    }
                    SyncDeviceAttendance::dispatch();
                    Notification::make()->title('Sync queued')->success()->send();
                }),
            CreateAction::make()
                ->after(fn ($record) => app(RebuildAttendanceDailySummaries::class)->forSegments(collect([$record]))),
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
