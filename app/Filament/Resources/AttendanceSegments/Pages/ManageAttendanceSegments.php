<?php

namespace App\Filament\Resources\AttendanceSegments\Pages;

use App\Filament\Exports\AttendanceSegmentExporter;
use App\Filament\Imports\AttendanceSegmentImporter;
use App\Filament\Resources\AttendanceSegments\AttendanceSegmentResource;
use App\Jobs\SyncDeviceAttendance;
use App\Models\AttendanceDevicePunch;
use App\Models\AttendanceDeviceSync;
use App\Models\AttendanceSegment;
use App\Services\Hr\NotifyUnmatchedAttendanceNumbers;
use App\Services\Hr\RebuildAttendanceDailySummaries;
use App\Support\AttendanceDeviceSettings;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\ExportAction;
use Filament\Actions\ImportAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\File;

class ManageAttendanceSegments extends ManageRecords
{
    protected static string $resource = AttendanceSegmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unmatchedNumbers')->label('Unmatched FP')->icon('heroicon-o-user-plus')->color('warning')
                ->badge(fn (): int => NotifyUnmatchedAttendanceNumbers::unmatchedIds(AttendanceDeviceSettings::get('id'))->count())
                ->authorize(fn (): bool => Gate::allows('create', AttendanceSegment::class))
                ->modalHeading('Unmatched FP numbers')->modalWidth('lg')->modalSubmitAction(false)->modalCancelActionLabel('Close')
                ->modalContent(fn (): View => view('filament.resources.attendance-segments.unmatched-numbers', [
                    'rows' => NotifyUnmatchedAttendanceNumbers::unmatchedRows(AttendanceDeviceSettings::get('id')),
                ])),
            Action::make('syncNow')->label('Sync')->icon('heroicon-o-arrow-path')
                ->authorize(fn (): bool => Gate::allows('create', AttendanceSegment::class))
                ->modalHeading('Attendance sync')->modalWidth('md')->modalSubmitActionLabel('Sync now')
                ->modalSubmitAction(fn (Action $action): Action => $action->disabled(! AttendanceDeviceSettings::get('enabled')))
                ->schema(function (): array {
                    $state = AttendanceDeviceSync::where('device_id', AttendanceDeviceSettings::get('id'))->first();

                    return [
                        TextEntry::make('status')->inlineLabel()->state(str($state?->status ?? 'never_synced')->headline()->toString())->badge(),
                        TextEntry::make('connection')->inlineLabel()->label('Device')->state(match (true) {
                            $state?->status === 'failed' => 'Unavailable',
                            ! $state?->last_connected_at => 'Unknown',
                            $state->last_connected_at->lt(now()->subMinutes(10)) => 'Stale',
                            default => 'Last connection OK',
                        }),
                        TextEntry::make('last_sync')->inlineLabel()->label('Last sync')->state($state?->last_successful_sync_at?->diffForHumans() ?? 'Never'),
                        TextEntry::make('processing')->inlineLabel()->state(AttendanceDeviceSettings::get('processing_enabled') ? 'Enabled' : 'Paused'),
                        TextEntry::make('pending')->inlineLabel()->label('Pending punches')->state(AttendanceDevicePunch::where('device_id', AttendanceDeviceSettings::get('id'))->whereNull('processed_at')->count()),
                        TextEntry::make('unmatched')->inlineLabel()->label('Unmatched FP numbers')
                            ->state(fn (): string => NotifyUnmatchedAttendanceNumbers::unmatchedIds(AttendanceDeviceSettings::get('id'))->join(', ') ?: 'None'),
                        TextEntry::make('error')->inlineLabel()->state($state?->last_error)->visible(filled($state?->last_error))->columnSpanFull(),
                    ];
                })->action(function (): void {
                    Gate::authorize('create', AttendanceSegment::class);
                    if (! AttendanceDeviceSettings::get('enabled')) {
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
