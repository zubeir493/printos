<?php

use App\Filament\Resources\Dielines\Pages\EditDieline;
use App\Filament\Resources\Dielines\Pages\ListDielines;
use App\Models\Dieline;
use App\Models\JobOrder;
use App\Models\Setting;
use App\Models\User;
use App\Services\Dielines\DielineExportService;
use App\Services\Dielines\DielineGeometryService;
use App\Services\Dielines\DielineTemplateRegistry;
use App\Services\Dielines\Renderers\SvgDielineRenderer;
use App\Services\Dielines\Templates\VialInsertTemplate;
use App\UserRole;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->actingAs(User::factory()->create([
        'role' => UserRole::Admin,
    ]));
});

it('opens the dieline list without colliding with a public asset directory', function (UserRole $role, string $path): void {
    $this->actingAs(User::factory()->create(['role' => $role]));
    $this->get($path)->assertSuccessful()->assertSee('Dielines');
    expect(is_dir(public_path('dielines')))->toBeFalse()
        ->and(is_file(public_path('dieline-assets/thumbnails/reverse-tuck-flap-box.svg')))->toBeTrue();
})->with([
    'admin' => [UserRole::Admin, '/dielines/'],
    'design' => [UserRole::Design, '/design/dielines/'],
]);

it('renders the dieline editor as a fullscreen canvas with a left settings sidebar', function (): void {
    $dieline = Dieline::factory()->create();

    Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->assertSuccessful()
        ->assertSee('Dieline type')
        ->assertSeeHtml('dieline-canvas-shell')
        ->assertDontSeeHtml('x-on:pointerdown')
        ->assertSeeHtml('dieline-editor-sidebar')
        ->assertSeeHtml('dieline-editor-actions')
        ->assertSeeHtml('dieline-editor-canvas')
        ->assertSeeHtml('dieline-preview-artboard')
        ->assertSeeHtml('dieline-save-action')
        ->assertSeeHtml('dieline-save-menu')
        ->assertSeeHtml('dieline-template-picker')
        ->assertSeeHtml('fi-input-wrp')
        ->assertSeeHtml('fi-fo-text-input')
        ->assertSeeHtml('fi-input')
        ->assertDontSee('Job task')
        ->assertSee('Cut')
        ->assertSee('Crease')
        ->assertDontSeeHtml('<strong>Bleed</strong>');
});

it('links admins back to the dashboard from the dieline editor', function (): void {
    $dieline = Dieline::factory()->create();

    Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->assertSee('Back to dashboard')
        ->assertSeeHtml('href="'.url('/').'"');
});

it('links designers back to their dieline list from the editor', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('design'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Design]));
    $dieline = Dieline::factory()->create();

    Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->assertSee('Back to dielines')
        ->assertSeeHtml('href="'.route('filament.design.resources.dielines.index').'"');
});

it('creates dieline drafts from a setup modal then saves dimensions and geometry', function (): void {
    $jobOrder = JobOrder::factory()->create();

    Livewire::test(ListDielines::class)
        ->mountAction('createDieline')
        ->assertMountedActionModalSee('Job order')
        ->assertMountedActionModalDontSee('Dieline type')
        ->assertMountedActionModalDontSee('Job task')
        ->setActionData([
            'name' => 'Pharma carton',
            'job_order_id' => $jobOrder->getKey(),
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

    expect($dieline->jobOrder->is($jobOrder))->toBeTrue();

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
            'label' => 'Sealing flap depth',
            'default' => 119.1,
            'min' => 1,
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
        ])
        ->callMountedAction()
        ->assertHasActionErrors([
            'name' => 'required',
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
        ->toContain('block-size: 100dvh')
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
        ->toContain('dieline-template-picker')
        ->toContain('selectTemplate(');
});

it('creates edits and exports every new packaging blueprint', function (string $class): void {
    $template = new $class;

    Livewire::test(ListDielines::class)
        ->mountAction('createDieline')
        ->assertMountedActionModalSee('Job order')
        ->assertMountedActionModalDontSee('Dieline type')
        ->assertMountedActionModalDontSee('Job task')
        ->setActionData(['name' => $template->name(), 'job_order_id' => null])
        ->callMountedAction()
        ->assertHasNoActionErrors();

    $dieline = Dieline::query()->sole();
    $editor = Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->call('selectTemplate', $template->key());

    foreach ($template->advancedFields() as $field) {
        $editor->assertSee($field['label'])->assertFormFieldVisible('dimensions.'.$field['key']);
    }

    $dimensions = [...$template->defaults(), 'l' => $template->defaults()['l'] + 10];

    $editor->fillForm(['template_key' => $template->key(), 'dimensions' => $dimensions])
        ->call('save')
        ->assertHasNoFormErrors();

    $dieline->refresh();

    expect($dieline->dimensions['l'])->toEqual($dimensions['l'])
        ->and($dieline->geometry['name'])->toBe($template->name())
        ->and(app(DielineTemplateRegistry::class)->options()[$template->key()])->toBe($template->name());

    foreach (['svg', 'dxf', 'pdf'] as $format) {
        $response = app(DielineExportService::class)->downloadDieline($dieline, $format);
        ob_start();
        $response->sendContent();
        $content = ob_get_clean();

        expect($content)->not->toBeEmpty();

        if ($format === 'pdf') {
            expect($content)->toStartWith('%PDF-');
        }
    }

    Livewire::test(ListDielines::class)->assertCanSeeTableRecords([$dieline])->assertSee($template->name());
})->with('packaging blueprints');

it('switches field labels defaults and validation limits with the selected template', function (): void {
    $dieline = Dieline::factory()->create();
    $editor = Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->set('data.template_key', 'pizza-box')
        ->assertSet('data.dimensions.l', 300)
        ->assertSet('data.dimensions.corner_cut', 60)
        ->assertSee('Octagon corner cut')
        ->assertDontSee('View source drawing');

    $editor->set('data.template_key', 'open-food-tray')
        ->assertSet('data.dimensions.corner_tab', 15)
        ->assertSee('Corner glue-tab width')
        ->assertDontSee('Octagon corner cut')
        ->assertDontSee('View source drawing')
        ->assertFormFieldVisible('dimensions.board_thickness')
        ->set('data.dimensions.corner_tab', 0)
        ->call('save')
        ->assertHasFormErrors(['dimensions.corner_tab' => 'min']);
});

it('requires every declared template measurement', function (string $class): void {
    $template = new $class;
    $dieline = Dieline::factory()->create(['template_key' => $template->key(), 'dimensions' => $template->defaults()]);
    $missingFields = array_fill_keys(array_keys($template->defaults()), null);
    $errors = array_fill_keys(array_map(fn (string $key): string => 'dimensions.'.$key, array_keys($missingFields)), 'required');

    $editor = Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->set('data.dimensions', [...$template->defaults(), ...$missingFields]);

    $editor->call('save');
    $editor->assertHasFormErrors($errors);
})->with('packaging blueprints');

it('validates insert aperture and wall taper before saving', function (string $field, float|int $value, string $rule): void {
    $template = new VialInsertTemplate;
    $dieline = Dieline::factory()->create(['template_key' => $template->key(), 'dimensions' => $template->defaults()]);

    Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->set('data.dimensions.'.$field, $value)
        ->call('save')
        ->assertHasFormErrors(['dimensions.'.$field => $rule]);
})->with([
    ['vial_diameter', 0, 'min'],
    ['wall_taper', 60, 'max'],
    ['vial_diameter', 160, 'max'],
]);

it('resolves the expanded catalog once per request when rendering fields', function (): void {
    app()->forgetInstance(DielineTemplateRegistry::class);
    DB::enableQueryLog();
    $registry = app(DielineTemplateRegistry::class);
    $options = $registry->options();

    foreach (array_keys($options) as $key) {
        $registry->fieldsForTemplate($key);
        $registry->defaultsByTemplate();
    }

    $queries = collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], 'dieline_templates'));
    DB::disableQueryLog();
    $sortedNames = array_values($options);
    sort($sortedNames);

    expect($queries)->toHaveCount(1)
        ->and(app(DielineTemplateRegistry::class))->toBe($registry)
        ->and(array_values($options))->toBe($sortedNames);
});

it('rejects incompatible measurements instead of saving a silently shortened feature', function (string $key, string $field, mixed $value, string $rule): void {
    $template = app(DielineTemplateRegistry::class)->template($key);
    $dieline = Dieline::factory()->create(['template_key' => $key, 'dimensions' => $template->defaults()]);

    Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->set('data.dimensions.'.$field, $value)
        ->assertSee('Enter valid measurements')
        ->call('save')
        ->assertHasFormErrors(['dimensions.'.$field => $rule]);

    expect($dieline->fresh()->dimensions[$field])->toEqual($template->defaults()[$field]);
})->with([
    ['pizza-box', 'corner_cut', 150, 'max'],
    ['burger-box', 'lid_depth', 120, 'max'],
    ['fries-scoop', 'base_length', 110, 'max'],
    ['fries-scoop', 'base_length', 'invalid', 'numeric'],
    ['sandwich-wedge', 'h', 60, 'max'],
    ['carry-bag', 'handle_drop', 60, 'max'],
    ['gable-box', 'hole_diameter', 60, 'max'],
    ['open-food-tray', 'corner_tab', 50, 'max'],
    ['seal-end-carton', 'seal_flap', 60, 'max'],
    ['hang-tab-carton', 'hanger_slot_width', 160, 'max'],
    ['vial-insert', 'wall_taper', 60, 'max'],
]);

it('keeps source metadata internally without adding links beneath the picker', function (string $key): void {
    $template = app(DielineTemplateRegistry::class)->template($key);
    $dieline = Dieline::factory()->create(['template_key' => $key, 'dimensions' => $template->defaults()]);

    Livewire::test(EditDieline::class, ['record' => $dieline->getKey()])
        ->assertDontSee('View source drawing')
        ->assertDontSeeHtml('href="'.asset('dieline-assets/references/'.$key.'.svg').'"')
        ->assertSeeHtml('step="any"');
})->with(['fries-scoop', 'burger-box', 'open-food-tray', 'hang-tab-carton', 'pizza-box', 'lock-tab-pizza-box', 'clamshell-burger-box', 'rollover-pizza-box', 'flip-top-burger-box', 'carry-bag', 'gable-box', 'seal-end-carton', 'sandwich-wedge', 'vial-insert']);

it('browses actual thumbnails and applies every registered blueprint without saving the draft', function (): void {
    $dieline = Dieline::factory()->create();
    $original = $dieline->dimensions;
    $editor = Livewire::test(EditDieline::class, ['record' => $dieline->getKey()]);
    $editor->assertSee('Choose a dieline')->assertSee('Search blueprints')->assertDontSee('View source drawing');
    foreach (app(DielineTemplateRegistry::class)->options() as $key => $name) {
        $template = app(DielineTemplateRegistry::class)->template($key);
        $editor->call('selectTemplate', $key)->assertSet('data.template_key', $key)->assertDispatched('close-modal', id: 'dieline-template-picker');
        foreach ($template->defaults() as $field => $value) {
            $editor->assertSet('data.dimensions.'.$field, $value);
        }
        $editor->assertSee($name)->assertSeeHtml('dieline-assets/thumbnails/'.$key.'.svg');
        $geometry = app(DielineGeometryService::class)->generate($key, $template->defaults());
        expect($geometry['layers']['cut'])->not->toBeEmpty();
        $renderer = new SvgDielineRenderer;
        foreach ([(string) $renderer->preview($geometry), $renderer->renderForSvg($geometry), $renderer->renderForPdf($geometry)] as $drawing) {
            expect($drawing)->not->toContain('data-dieline-layer="glue"', '<polygon', '#dcfce7', '#16a34a');
        }
        $geometry['labels'] = [];
        expect(file_get_contents(public_path('dieline-assets/thumbnails/'.$key.'.svg')))->toBe($renderer->render($geometry));
        $thumbnail = new DOMDocument;
        expect($thumbnail->load(public_path('dieline-assets/thumbnails/'.$key.'.svg')))->toBeTrue();
        expect($thumbnail->getElementsByTagName('line')->length)->toBe(count($geometry['layers']['cut']) + count($geometry['layers']['crease']) + count($geometry['layers']['perforation'] ?? []) + count($geometry['layers']['cut_crease'] ?? []) + count($geometry['layers']['bleed'] ?? []));
    }
    expect($dieline->fresh()->dimensions)->toBe($original);
    $editor->call('selectTemplate', 'unknown-blueprint')->assertHasErrors('data.template_key');
});
