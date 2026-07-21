<?php

namespace App\Filament\Resources\Dielines\Pages;

use App\Filament\Resources\Dielines\DielineResource;
use App\Filament\Resources\Dielines\Pages\Concerns\PreparesDielineData;
use App\Services\Dielines\DielineGeometryService;
use App\Services\Dielines\DielineTemplateRegistry;
use App\Services\Dielines\Renderers\SvgDielineRenderer;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\Response;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditDieline extends EditRecord
{
    use PreparesDielineData;

    protected static string $resource = DielineResource::class;

    protected string $view = 'filament.resources.dielines.pages.edit-dieline';

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @return array<string, string>
     */
    public function templateOptions(): array
    {
        return app(DielineTemplateRegistry::class)->options();
    }

    /**
     * @return array<int, array{key: string, label: string, suffix: string}>
     */
    public function dimensionFields(): array
    {
        $templateKey = (string) data_get($this->data, 'template_key', 'fefco-0210');
        $fields = [
            ['key' => 'l', 'label' => 'Length', 'suffix' => 'mm'],
            ['key' => 'w', 'label' => 'Width', 'suffix' => 'mm'],
            ['key' => 'h', 'label' => 'Height', 'suffix' => 'mm'],
        ];

        foreach (app(DielineTemplateRegistry::class)->advancedFields() as $field) {
            if (in_array($templateKey, $field['templates'], true)) {
                $fields[] = [
                    'key' => $field['key'],
                    'label' => $field['label'],
                    'suffix' => $field['suffix'] ?? 'mm',
                ];
            }
        }

        return $fields;
    }

    public function preview(): HtmlString
    {
        try {
            $geometry = app(DielineGeometryService::class)->generate(
                (string) data_get($this->data, 'template_key', 'fefco-0210'),
                (array) data_get($this->data, 'dimensions', []),
            );

            return app(SvgDielineRenderer::class)->preview($geometry);
        } catch (\Throwable) {
            return new HtmlString('<div class="dieline-preview-frame dieline-preview-empty">Enter valid measurements to preview the dieline.</div>');
        }
    }

    public function updatedDataTemplateKey(?string $templateKey): void
    {
        $template = app(DielineTemplateRegistry::class)->template($templateKey ?: 'fefco-0210');

        foreach ($template->defaults() as $key => $value) {
            data_set($this->data, "dimensions.{$key}", $value);
        }
    }

    public function downloadInstantSvg(): Response|StreamedResponse
    {
        return $this->downloadInstant('svg');
    }

    public function downloadInstantPdf(): Response|StreamedResponse
    {
        return $this->downloadInstant('pdf');
    }

    public function downloadInstantDxf(): Response|StreamedResponse
    {
        return $this->downloadInstant('dxf');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Dieline saved';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->prepareDielineData($data);
    }

    public function getTitle(): string|Htmlable
    {
        return $this->record->name;
    }

    protected function hasFullWidthFormActions(): bool
    {
        return false;
    }
}
