<?php

namespace App\Filament\Resources\Dielines\Pages;

use App\Filament\Resources\Dielines\DielineResource;
use App\Filament\Resources\Dielines\Pages\Concerns\PreparesDielineData;
use App\Services\Dielines\DielineGeometryService;
use App\Services\Dielines\DielineTemplateRegistry;
use App\Services\Dielines\Renderers\SvgDielineRenderer;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
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

    /** @return array<int, array{key: string, name: string, description: string, thumbnail: string}> */
    public function templateCards(): array
    {
        $registry = app(DielineTemplateRegistry::class);
        $cards = [];

        foreach ($registry->options() as $key => $name) {
            $cards[] = ['key' => $key, 'name' => $name, 'description' => $registry->template($key)->description(), 'thumbnail' => asset('dielines/thumbnails/'.$key.'.svg')];
        }

        return $cards;
    }

    public function selectTemplate(string $key): void
    {
        if (! array_key_exists($key, $this->templateOptions())) {
            throw ValidationException::withMessages(['data.template_key' => 'Choose an available blueprint.']);
        }

        data_set($this->data, 'template_key', $key);
        $this->updatedDataTemplateKey($key);
        $this->resetValidation();
        $this->dispatch('close-modal', id: 'dieline-template-picker');
    }

    /**
     * @return array<int, array{key: string, label: string, suffix: string, min: float|int, max?: float|int, step: string|int}>
     */
    public function dimensionFields(): array
    {
        $templateKey = (string) data_get($this->data, 'template_key', 'reverse-tuck-flap-box');
        $labels = app(DielineTemplateRegistry::class)->template($templateKey)->baseDimensionLabels();
        $fields = [
            ['key' => 'l', 'label' => $labels['l'], 'suffix' => 'mm', 'min' => 1, 'step' => 'any'],
            ['key' => 'w', 'label' => $labels['w'], 'suffix' => 'mm', 'min' => 1, 'step' => 'any'],
            ['key' => 'h', 'label' => $labels['h'], 'suffix' => 'mm', 'min' => 1, 'step' => 'any'],
        ];

        foreach (app(DielineTemplateRegistry::class)->fieldsForTemplate($templateKey) as $field) {
            $fields[] = [...$field, 'suffix' => $field['suffix'] ?? 'mm', 'min' => $field['min'] ?? 0, 'step' => ($field['integer'] ?? false) ? 1 : 'any'];
        }

        return $fields;
    }

    public function preview(): HtmlString
    {
        try {
            $geometry = app(DielineGeometryService::class)->generate(
                (string) data_get($this->data, 'template_key', 'reverse-tuck-flap-box'),
                (array) data_get($this->data, 'dimensions', []),
            );

            return app(SvgDielineRenderer::class)->preview($geometry);
        } catch (\Throwable) {
            return new HtmlString('<div class="dieline-preview-frame dieline-preview-empty">Enter valid measurements to preview the dieline.</div>');
        }
    }

    public function updatedDataTemplateKey(?string $templateKey): void
    {
        $template = app(DielineTemplateRegistry::class)->template($templateKey ?: 'reverse-tuck-flap-box');

        foreach ($template->defaults() as $key => $value) {
            data_set($this->data, "dimensions.{$key}", $value);
        }
    }

    public function downloadInstantSvg(): StreamedResponse
    {
        return $this->downloadInstant('svg');
    }

    public function downloadInstantPdf(): StreamedResponse
    {
        return $this->downloadInstant('pdf');
    }

    public function downloadInstantDxf(): StreamedResponse
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
