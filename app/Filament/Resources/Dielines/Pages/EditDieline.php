<?php

namespace App\Filament\Resources\Dielines\Pages;

use App\Filament\Resources\Dielines\DielineResource;
use App\Filament\Resources\Dielines\Pages\Concerns\PreparesDielineData;
use App\Jobs\GenerateDielineImposition;
use App\Models\DielineImposition;
use App\Services\Dielines\DielineGeometryService;
use App\Services\Dielines\DielineImpositionService;
use App\Services\Dielines\DielineTemplateRegistry;
use App\Services\Dielines\Renderers\SvgDielineRenderer;
use App\UserRole;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EditDieline extends EditRecord
{
    use PreparesDielineData;

    protected static string $resource = DielineResource::class;

    protected string $view = 'filament.resources.dielines.pages.edit-dieline';

    #[Locked]
    public ?int $impositionId = null;

    #[Locked]
    public bool $impositionNotified = true;

    public array $impositionSettings = ['width' => 594, 'height' => 841, 'margin' => 5, 'spacing' => 2];

    #[Locked]
    public ?array $impositionPreview = null;

    #[Locked]
    public ?string $impositionPreviewError = null;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->impositionId = DielineImposition::query()->where('user_id', auth()->id())->where('dieline_id', $this->record->getKey())->latest('id')->value('id');
        $this->impositionNotified = ! in_array($this->currentImposition()?->status, ['queued', 'processing'], true);
    }

    public function updatedImpositionSettings(): void
    {
        $this->refreshImpositionPreview();
    }

    public function refreshImpositionPreview(): void
    {
        $this->impositionPreview = null;
        $this->impositionPreviewError = null;

        try {
            $draft = $this->impositionDraft();
            $key = 'dieline-imposition-preview:'.hash('sha256', json_encode($draft->getAttributes(), JSON_THROW_ON_ERROR));
            $layout = Cache::remember($key, now()->addMinutes(5), fn (): array => app(DielineImpositionService::class)->nest($draft, (int) config('dielines.imposition.preview_search_ms')));
            $this->impositionPreview = ['copies' => $layout['copies'], 'utilisation' => $layout['utilisation']];
        } catch (ValidationException) {
            $this->impositionPreviewError = 'Enter valid sheet and dieline measurements to calculate copies.';
        } catch (\RuntimeException $exception) {
            if (str_contains($exception->getMessage(), 'does not fit')) {
                $this->impositionPreviewError = 'No copies fit at 100% scale. Increase the sheet size or reduce the margin or spacing.';
            } else {
                report($exception);
                $this->impositionPreviewError = 'The copy estimate could not be calculated. Please try again.';
            }
        }
    }

    private function impositionDraft(): DielineImposition
    {
        abort_unless(in_array(auth()->user()?->role, [UserRole::Admin, UserRole::Design], true), 403);
        $settings = $this->validate([
            'impositionSettings.width' => ['required', 'integer', 'min:1', 'max:5000'],
            'impositionSettings.height' => ['required', 'integer', 'min:1', 'max:5000'],
            'impositionSettings.margin' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'impositionSettings.spacing' => ['required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
        ], [], [
            'impositionSettings.width' => 'sheet width', 'impositionSettings.height' => 'sheet height',
            'impositionSettings.margin' => 'edge margin', 'impositionSettings.spacing' => 'spacing',
        ])['impositionSettings'];

        if (2 * (float) $settings['margin'] >= min((int) $settings['width'], (int) $settings['height'])) {
            throw ValidationException::withMessages(['impositionSettings.margin' => 'The margin must leave usable space on the sheet.']);
        }

        $data = $this->prepareDielineData($this->form->getState());

        return new DielineImposition([
            'user_id' => auth()->id(), 'dieline_id' => $this->record->getKey(), 'name' => $data['name'],
            'paper_size' => 'custom', 'paper_width' => (int) $settings['width'], 'paper_height' => (int) $settings['height'],
            'margin' => $settings['margin'], 'gap' => $settings['spacing'],
            'status' => 'queued', 'source_geometry' => $data['geometry'],
        ]);
    }

    public function currentImposition(): ?DielineImposition
    {
        return $this->impositionId ? DielineImposition::query()->where('user_id', auth()->id())->find($this->impositionId) : null;
    }

    public function startImposition(): void
    {
        abort_unless(in_array(auth()->user()?->role, [UserRole::Admin, UserRole::Design], true), 403);
        if (in_array($this->currentImposition()?->status, ['queued', 'processing'], true)) {
            return;
        }
        $imposition = $this->impositionDraft();
        $imposition->save();
        $this->impositionId = $imposition->getKey();
        $this->impositionNotified = false;
        GenerateDielineImposition::dispatch($imposition->getKey())->afterCommit();
        $this->dispatch('close-modal', id: 'dieline-imposition-picker');
        Notification::make()->info()->title('Generating imposition')->body('You can keep working. We will notify you when your '.$imposition->sheetLabel().' sheet is ready.')->send();
    }

    public function checkImposition(): void
    {
        $imposition = $this->currentImposition();
        if ($this->impositionNotified || ! $imposition || ! in_array($imposition->status, ['completed', 'failed'], true)) {
            return;
        }
        $this->impositionNotified = true;
        if ($imposition->status === 'completed') {
            $imposition->completedNotification()->send();
        } else {
            Notification::make()->danger()->title('Imposition failed')->body($imposition->error_message)->send();
        }
    }

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
            $cards[] = ['key' => $key, 'name' => $name, 'description' => $registry->template($key)->description(), 'thumbnail' => asset('dieline-assets/thumbnails/'.$key.'.svg')];
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
