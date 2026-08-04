<?php

namespace App\Filament\Resources\Dielines\Pages\Concerns;

use App\Models\Dieline;
use App\Services\Dielines\DielineExportService;
use App\Services\Dielines\DielineGeometryService;
use Filament\Actions\Action;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

trait PreparesDielineData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function prepareDielineData(array $data): array
    {
        $templateKey = (string) ($data['template_key'] ?? 'reverse-tuck-flap-box');
        $dimensions = app(DielineGeometryService::class)->normalize($templateKey, (array) ($data['dimensions'] ?? []));
        $geometry = app(DielineGeometryService::class)->generate($templateKey, $dimensions);

        $data['name'] = filled($data['name'] ?? null) ? $data['name'] : $geometry['name'].' '.now()->format('Ymd His');
        $data['dimensions'] = $dimensions;
        $data['geometry'] = $geometry;
        $data['created_by'] ??= auth()->id();

        return $data;
    }

    protected function downloadInstant(string $format): Response|StreamedResponse
    {
        return app(DielineExportService::class)->downloadFromData(
            $this->prepareDielineData($this->form->getState()),
            $format,
        );
    }

    protected function saveAndDownload(string $format): Response|StreamedResponse
    {
        $data = $this->prepareDielineData($this->form->getState());
        $record = $this->record ?? null;

        if ($record instanceof Dieline && $record->exists) {
            $record->update($data);
        } else {
            $record = Dieline::query()->create($data);
            $this->record = $record;
        }

        return app(DielineExportService::class)->downloadDieline($record->refresh(), $format);
    }

    protected function instantDownloadAction(string $format, string $label): Action
    {
        return Action::make("instant_{$format}")
            ->label("Download {$label}")
            ->icon('heroicon-o-arrow-down-tray')
            ->color('gray')
            ->action(fn () => $this->downloadInstant($format));
    }

    protected function saveAndDownloadAction(string $format, string $label): Action
    {
        return Action::make("save_download_{$format}")
            ->label("Save & Download {$label}")
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->action(fn () => $this->saveAndDownload($format));
    }

    protected function duplicateAction(): Action
    {
        return Action::make('duplicate')
            ->label('Duplicate')
            ->icon('heroicon-o-document-duplicate')
            ->color('gray')
            ->action(function (): void {
                $copy = $this->record->replicate();
                $copy->name = Str::limit($this->record->name.' copy', 255, '');
                $copy->created_by = auth()->id();
                $copy->save();

                $this->redirect(static::$resource::getUrl('edit', ['record' => $copy]));
            });
    }
}
