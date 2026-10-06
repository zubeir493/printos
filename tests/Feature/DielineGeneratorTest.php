<?php

use App\Filament\Resources\Dielines\Pages\EditDieline;
use App\Filament\Resources\Dielines\Pages\ListDielines;
use App\Models\Dieline;
use App\Models\Setting;
use App\Models\User;
use App\Services\Dielines\DielineExportService;
use App\Services\Dielines\DielineGeometryService;
use App\Services\Dielines\DielineTemplateRegistry;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));
});

it('renders the dieline editor as a fullscreen canvas with a left settings sidebar', function (): void {
    $dieline = Dieline::factory()->create();

    Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->assertSuccessful()
        ->assertSee('Settings')
        ->assertSeeHtml('dieline-canvas-shell')
        ->assertDontSeeHtml('x-on:pointerdown')
        ->assertSeeHtml('dieline-editor-sidebar')
        ->assertSeeHtml('dieline-editor-actions')
        ->assertSeeHtml('dieline-editor-canvas')
        ->assertSeeHtml('dieline-preview-artboard')
        ->assertSeeHtml('dieline-save-action')
        ->assertSeeHtml('dieline-save-menu')
        ->assertSeeHtml('fi-select-input')
        ->assertSeeHtml('fi-input-wrp')
        ->assertSeeHtml('fi-fo-text-input')
        ->assertSeeHtml('fi-input')
        ->assertDontSee('Job task')
        ->assertSee('Solid black')
        ->assertSee('Blue dashed fold')
        ->assertSee('Amber dashed margin');
});

it('creates dieline drafts from a setup modal then saves dimensions and geometry', function (): void {
    Livewire::test(ListDielines::class)
        ->mountAction('createDieline')
        ->setActionData([
            'name' => 'Pharma carton',
            'template_key' => 'reverse-tuck-flap-box',
            'job_order_task_id' => null,
        ])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $dieline = Dieline::query()->firstOrFail();

    Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->fillForm([
            'name' => 'Pharma carton',
            'template_key' => 'reverse-tuck-flap-box',
            'dimensions' => [
                'l' => 160,
                'w' => 50,
                'h' => 90,
                'tuck_flap' => 28,
                'glue_flap' => 18,
                'dust_flap' => 25,
                'bleed' => 3,
                'board_thickness' => 1.5,
            ],
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $dieline->refresh();

    expect($dieline)
        ->not->toBeNull()
        ->name->toBe('Pharma carton')
        ->template_key->toBe('reverse-tuck-flap-box')
        ->and($dieline->dimensions)->toHaveKeys(['l', 'w', 'h'])
        ->and($dieline->geometry['layers'])->toHaveKeys(['cut', 'crease', 'glue', 'bleed']);
});

it('lists the additional templates in the new dieline selector', function (): void {
    expect(app(DielineTemplateRegistry::class)->options())
        ->toHaveKeys([
            'auto-bottom-tuck-top',
            'four-corner-food-tray',
            'full-overlap-carton',
            'open-ended-sleeve',
            'snap-lock-bottom-tuck-top',
        ]);

    expect(app(DielineTemplateRegistry::class)->advancedFields())
        ->toContain([
            'key' => 'flap_height',
            'label' => 'Flap height',
            'default' => 50,
            'min' => 0,
            'suffix' => 'mm',
            'templates' => ['full-overlap-carton'],
        ]);
});

it('validates required base dimensions', function (): void {
    $dieline = Dieline::factory()->create([
        'template_key' => 'reverse-tuck-flap-box',
    ]);

    Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->fillForm([
            'name' => 'Invalid dieline',
            'template_key' => 'reverse-tuck-flap-box',
            'dimensions' => [
                'l' => null,
                'w' => 160,
                'h' => 45,
            ],
        ])
        ->call('save')
        ->assertHasFormErrors(['dimensions.l' => 'required']);
});

it('lists saved dielines and exposes compact edit page download actions', function (): void {
    $dieline = Dieline::factory()->create();

    Livewire::test(ListDielines::class)
        ->assertSuccessful()
        ->assertCanSeeTableRecords([$dieline])
        ->assertActionExists('createDieline');

    Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->assertSuccessful()
        ->assertSee('Download SVG')
        ->assertSee('Download PDF')
        ->assertSee('Download DXF')
        ->call('downloadInstantSvg')
        ->assertFileDownloaded('reverse-tuck-flap-box-test-dieline.svg')
        ->call('downloadInstantPdf')
        ->assertFileDownloaded('reverse-tuck-flap-box-test-dieline.pdf')
        ->assertSeeHtml('dieline-preview-artboard');
});

it('can build instant svg and dxf downloads without a saved record', function (): void {
    $data = [
        'name' => 'Instant mailer',
        'template_key' => 'reverse-tuck-flap-box',
        'dimensions' => [
            'l' => 220,
            'w' => 160,
            'h' => 45,
            'lid_tuck' => 35,
            'side_lock' => 28,
            'front_lock' => 24,
            'bleed' => 3,
            'board_thickness' => 1.5,
        ],
    ];

    $svg = app(DielineExportService::class)->downloadFromData($data, 'svg');
    $dxf = app(DielineExportService::class)->downloadFromData($data, 'dxf');
    $pdf = app(DielineExportService::class)->downloadFromData($data, 'pdf');

    ob_start();
    $pdf->sendContent();
    $pdfContent = ob_get_clean();

    preg_match_all('/\/Type\s*\/Page\b/', $pdfContent, $pages);
    preg_match('/\/MediaBox\s*\[\s*0\.000\s+0\.000\s+([0-9.]+)\s+([0-9.]+)\s*\]/', $pdfContent, $mediaBox);
    $geometry = app(DielineGeometryService::class)->generate($data['template_key'], $data['dimensions']);

    expect($svg->headers->get('content-disposition'))
        ->toContain('instant-mailer.svg')
        ->and($dxf->headers->get('content-disposition'))
        ->toContain('instant-mailer.dxf')
        ->and($pdf->headers->get('content-disposition'))
        ->toContain('instant-mailer.pdf')
        ->and($pages[0])
        ->toHaveCount(1)
        ->and($mediaBox)
        ->not->toBeEmpty()
        ->and(abs((float) $mediaBox[1] - ((float) $geometry['bounds']['width'] * 72 / 25.4)))
        ->toBeLessThan(0.01)
        ->and(abs((float) $mediaBox[2] - ((float) $geometry['bounds']['height'] * 72 / 25.4)))
        ->toBeLessThan(0.01)
        ->and(Dieline::query()->count())
        ->toBe(0);

    expect(str_contains($svg->getContent(), 'fill="#fafafa"'))->toBeFalse()
        ->and(str_contains($svg->getContent(), '<text'))->toBeFalse();
});

it('embeds the dieline SVG as an image for PDF rendering', function (): void {
    $html = view('dielines.pdf', [
        'svg' => '<svg><line x1="0" y1="0" x2="10" y2="10" /></svg>',
    ])->render();

    expect($html)
        ->toContain('data:image/svg+xml;base64,')
        ->toContain('alt="Dieline"')
        ->not->toContain('Test dieline')
        ->not->toContain('<div class="drawing"><svg');
});

it('validates the setup modal before creating a dieline draft', function (): void {
    Livewire::test(ListDielines::class)
        ->mountAction('createDieline')
        ->setActionData([
            'name' => null,
            'template_key' => null,
        ])
        ->callMountedAction()
        ->assertHasActionErrors([
            'name' => 'required',
            'template_key' => 'required',
        ]);

    expect(Dieline::query()->count())->toBe(0);
});

it('scopes fullscreen chrome hiding and split save styling to the dieline editor', function (): void {
    $css = file_get_contents(resource_path('css/filament/admin/theme.css'));
    $blade = file_get_contents(resource_path('views/filament/resources/dielines/pages/edit-dieline.blade.php'));

    expect($css)
        ->toContain('body:has(.dieline-canvas-shell) .fi-topbar-ctn')
        ->toContain('body:has(.dieline-canvas-shell) .fi-main-sidebar')
        ->toContain('body:has(.dieline-canvas-shell) .fi-page-header-main-ctn')
        ->toContain('display: flex')
        ->toContain('inline-size: 100vw')
        ->toContain('.dieline-editor-sidebar')
        ->toContain('.dieline-editor-canvas')
        ->toContain('max-block-size: calc(100dvh - 1.5rem)')
        ->toContain('position: sticky')
        ->toContain('overflow: hidden')
        ->toContain('dieline-save-action')
        ->toContain('dieline-save-menu')
        ->not->toContain('grid-template-columns: 15.25rem minmax(0, 1fr)')
        ->not->toContain('.dieline-editor-select')
        ->not->toContain('.dieline-editor-input-wrap')
        ->not->toContain('.dieline-editor-input')
        ->not->toContain('--dieline-pan-x')
        ->not->toContain('cursor: grab');

    expect($blade)
        ->toContain('<x-filament::input.wrapper x-on:focus-input.stop="$el.querySelector(\'select\')?.focus()">')
        ->toContain('<x-filament::input.select wire:model.live="data.template_key">');
});
