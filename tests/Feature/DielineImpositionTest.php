<?php

use App\Filament\Resources\Dielines\Pages\EditDieline;
use App\Jobs\GenerateDielineImposition;
use App\Models\Dieline;
use App\Models\DielineImposition;
use App\Models\Setting;
use App\Models\User;
use App\Services\Dielines\DielineExportService;
use App\Services\Dielines\DielineGeometryService;
use App\Services\Dielines\DielineImpositionService;
use App\UserRole;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Support\Facades\FilamentView;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Setting::createDefault();
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $this->actingAs(User::factory()->create(['role' => UserRole::Admin]));
    config(['filesystems.private_disk' => 's3', 'dielines.imposition.disk' => 'local', 'dielines.imposition.search_ms' => 300]);
    Storage::fake('local');
});

it('previews custom sheets without generating files and queues an unsaved snapshot only on generate', function (): void {
    Queue::fake();
    $dieline = Dieline::factory()->create();
    $original = $dieline->dimensions;
    $page = Livewire::test(EditDieline::class, ['record' => $dieline->id])
        ->assertSee('Impose on sheet')->assertSee('Sheet width')->assertSee('Sheet height')->assertSee('Edge margin')->assertSee('Spacing')->assertSee('Generate files')
        ->assertDontSee('Choose a paper size')->assertDontSee('Download imposed PDF')
        ->set('data.dimensions.h', 123)
        ->set('impositionSettings', ['width' => 620, 'height' => 900, 'margin' => 7.5, 'spacing' => 3.25])
        ->assertHasNoErrors()->assertSee('Estimated')->assertSee('620 × 900 mm');
    Queue::assertNothingPushed();
    expect(DielineImposition::query()->count())->toBe(0);
    $page->call('startImposition')->assertHasNoErrors()
        ->assertDispatched('close-modal', id: 'dieline-imposition-picker');
    $imposition = DielineImposition::query()->sole();
    expect($imposition->paper_width)->toBe(620)->and($imposition->paper_height)->toBe(900)
        ->and($imposition->paper_size)->toBe('custom')->and($imposition->sheetLabel())->toBe('620 × 900 mm')
        ->and($imposition->margin)->toBe(7.5)->and($imposition->gap)->toBe(3.25)
        ->and($imposition->source_geometry)->toEqual(app(DielineGeometryService::class)->generate($dieline->template_key, [...$original, 'h' => 123]))
        ->and($imposition->user_id)->toBe(auth()->id())
        ->and($dieline->fresh()->dimensions)->toBe($original);
    Queue::assertPushed(GenerateDielineImposition::class, fn ($job) => $job->impositionId === $imposition->id && $job->afterCommit);
    $snapshot = $imposition->source_geometry;
    $page->set('data.dimensions.h', 145)->call('startImposition');
    expect(DielineImposition::query()->count())->toBe(1)->and($imposition->fresh()->source_geometry)->toBe($snapshot);
    $imposition->update(['status' => 'completed', 'copies' => 4]);
    $page->call('$refresh')->assertSeeHtml('wire:poll.3s.keep-alive="checkImposition"')
        ->call('checkImposition')->assertNotified('Imposition ready')->assertSet('impositionNotified', true)
        ->assertDontSeeHtml('wire:poll.3s.keep-alive="checkImposition"');
});

it('rejects invalid custom sheet settings before queuing any work', function (string $field, mixed $value): void {
    Queue::fake();
    Livewire::test(EditDieline::class, ['record' => Dieline::factory()->create()->id])
        ->set('impositionSettings.'.$field, $value)
        ->call('startImposition')->assertHasErrors('impositionSettings.'.$field);
    Queue::assertNothingPushed();
    expect(DielineImposition::query()->count())->toBe(0);
})->with([
    ['width', 0], ['height', ''], ['width', 5001], ['width', 594.5],
    ['margin', -1], ['margin', 101], ['spacing', -1], ['spacing', 101], ['spacing', 0.001],
]);

it('rejects margins that consume the sheet and reports when no copies fit', function (): void {
    Queue::fake();
    $page = Livewire::test(EditDieline::class, ['record' => Dieline::factory()->create()->id])
        ->set('impositionSettings', ['width' => 100, 'height' => 100, 'margin' => 50, 'spacing' => 0])
        ->call('startImposition')->assertHasErrors('impositionSettings.margin');
    $page->set('impositionSettings', ['width' => 10, 'height' => 10, 'margin' => 0, 'spacing' => 0])
        ->assertSet('impositionPreview', null)->assertSee('No copies fit at 100% scale');
    Queue::assertNothingPushed();
});

it('caches the quick estimate and allows zero margin and spacing', function (): void {
    Queue::fake();
    $nesting = Mockery::mock(DielineImpositionService::class);
    $nesting->shouldReceive('nest')->once()->withArgs(fn (DielineImposition $draft, int $searchMilliseconds): bool => $draft->margin === 0.0 && $draft->gap === 0.0 && $searchMilliseconds === 1200)
        ->andReturn(['copies' => 2, 'utilisation' => 54.5]);
    app()->instance(DielineImpositionService::class, $nesting);
    Livewire::test(EditDieline::class, ['record' => Dieline::factory()->create()->id])
        ->set('impositionSettings', ['width' => 594, 'height' => 841, 'margin' => 0, 'spacing' => 0])
        ->call('refreshImpositionPreview')->assertSee('Estimated 2 copies per sheet')
        ->assertSee('54.5% material use')->call('startImposition')->assertHasNoErrors();
    Queue::assertPushed(GenerateDielineImposition::class);
});

it('rejects invalid dieline measurements before queuing work', function (): void {
    Queue::fake();
    Livewire::test(EditDieline::class, ['record' => Dieline::factory()->create()->id])
        ->set('data.dimensions.l', 0)->call('startImposition')->assertHasFormErrors(['dimensions.l']);
    Queue::assertNothingPushed();
    expect(DielineImposition::query()->count())->toBe(0);
});

it('generates private full-size files and a persistent notification with three download actions', function (): void {
    Queue::fake();
    $imposition = DielineImposition::factory()->create(['user_id' => auth()->id(), 'paper_size' => 'custom', 'paper_width' => 620, 'paper_height' => 900, 'margin' => 7.5, 'gap' => 3.25]);
    $job = new GenerateDielineImposition($imposition->id);
    $job->handle(app(DielineImpositionService::class), app(DielineExportService::class));
    $imposition->refresh();
    expect($imposition->status)->toBe('completed')->and($imposition->copies)->toBeGreaterThan(1);
    foreach (['pdf', 'svg', 'dxf'] as $format) {
        Storage::disk('local')->assertExists($imposition->files[$format]);
        $response = $this->get($imposition->downloadUrl($format))
            ->assertSuccessful()
            ->assertHeader('Content-Type', ['pdf' => 'application/pdf', 'svg' => 'image/svg+xml', 'dxf' => 'application/dxf'][$format])
            ->assertDownload();

        $streamedContent = $response->streamedContent();

        expect($streamedContent)->toBe(Storage::disk('local')->get($imposition->files[$format]));

        if ($format === 'pdf') {
            expect($streamedContent)->toStartWith('%PDF-')->toMatch('/\/MediaBox\s*\[0\.000 0\.000 1757\.480 2551\.181\]/');
        }
    }
    $svg = Storage::disk('local')->get($imposition->files['svg']);
    expect($svg)->toContain('width="620mm"', 'height="900mm"', 'viewBox="0 0 620 900"');
    $pdf = Storage::disk('local')->get($imposition->files['pdf']);
    expect($pdf)->toStartWith('%PDF-')->toMatch('/\/MediaBox\s*\[0\.000 0\.000 1757\.480 2551\.181\]/');
    $dxf = Storage::disk('local')->get($imposition->files['dxf']);
    expect($dxf)->toContain("\$INSUNITS\r\n70\r\n4");
    $notification = auth()->user()->notifications()->sole();
    expect($notification->data['title'])->toBe('Imposition ready')
        ->and($notification->data['body'])->toContain('620 × 900 mm')
        ->and(array_column($notification->data['actions'], 'name'))->toBe(['pdf', 'svg', 'dxf']);
    expect(array_column($notification->data['actions'], 'url'))->toBe(array_map(fn (string $format): string => '/dieline-impositions/'.$imposition->id.'/'.$format, ['pdf', 'svg', 'dxf']));
    foreach ($notification->data['actions'] as $action) {
        expect($action['extraAttributes'])->toBe(['download' => true]);
    }
    $job->handle(app(DielineImpositionService::class), app(DielineExportService::class));
    expect(auth()->user()->notifications()->count())->toBe(1);
    Queue::assertNothingPushed();
    expect($imposition->completedNotification()->getDuration())->toBe('persistent');
    Livewire::test(EditDieline::class, ['record' => $imposition->dieline_id])->assertDontSee('Download imposed PDF')->assertDontSee('copies on A1');
});

it('renders existing notification downloads without SPA navigation in either dieline panel', function (string $panel): void {
    Filament::setCurrentPanel(Filament::getPanel($panel));
    Filament::bootCurrentPanel();
    $imposition = DielineImposition::factory()->create(['user_id' => auth()->id()]);

    expect(FilamentView::hasSpaMode(url('/dielines/1/edit')))->toBeTrue();

    foreach (['pdf', 'svg', 'dxf'] as $format) {
        foreach ([$imposition->downloadUrl($format), url($imposition->downloadUrl($format))] as $url) {
            $action = Action::fromArray(['name' => $format, 'label' => strtoupper($format), 'url' => $url]);

            expect($action->toHtml())->toContain('href="'.e($url).'"')
                ->not->toContain('wire:navigate')
                ->not->toContain('Alpine.navigate');
        }
    }
})->with(['admin', 'design']);

it('downloads completed artifacts without rerunning nesting or export rendering', function (): void {
    $imposition = DielineImposition::factory()->create([
        'user_id' => auth()->id(), 'status' => 'completed', 'disk' => 'local',
        'files' => ['pdf' => 'imposed.pdf', 'svg' => 'imposed.svg', 'dxf' => 'imposed.dxf'],
    ]);
    $this->mock(DielineImpositionService::class)->shouldNotReceive('sheetGeometry');
    $this->mock(DielineExportService::class)->shouldNotReceive('downloadGeometry');

    foreach (['pdf' => "%PDF-1.7\n saved binary \x00\xFF", 'svg' => '<svg/>', 'dxf' => "0\r\nEOF"] as $format => $contents) {
        Storage::disk('local')->put($imposition->files[$format], $contents);

        $response = $this->get($imposition->downloadUrl($format))->assertSuccessful()
            ->assertDownload()->assertHeader('X-Content-Type-Options', 'nosniff');

        expect($response->streamedContent())->toBe($contents);
    }
});

it('marks a sheet that cannot fit as failed without producing downloads', function (): void {
    $imposition = DielineImposition::factory()->create(['user_id' => auth()->id(), 'paper_size' => 'a4', 'paper_width' => 10, 'paper_height' => 10]);
    $job = new GenerateDielineImposition($imposition->id);
    expect(fn () => $job->handle(app(DielineImpositionService::class), app(DielineExportService::class)))->toThrow(RuntimeException::class);
    expect($imposition->fresh()->status)->toBe('failed')->and($imposition->fresh()->files)->toBeNull();
    $this->get($imposition->downloadUrl('svg'))->assertNotFound();
});

it('protects another users downloads and rejects unfinished or missing files', function (): void {
    $imposition = DielineImposition::factory()->create(['user_id' => auth()->id()]);
    $this->get($imposition->downloadUrl('pdf'))->assertNotFound();
    $imposition->update(['status' => 'completed', 'disk' => 'local', 'files' => ['pdf' => 'missing.pdf']]);
    $this->get($imposition->downloadUrl('pdf'))->assertNotFound();
    $this->get($imposition->downloadUrl('exe'))->assertNotFound();
    Storage::disk('local')->put('missing.pdf', '%PDF-');
    $this->actingAs(User::factory()->create(['role' => UserRole::Design]));
    $this->get($imposition->downloadUrl('pdf'))->assertRedirect();
});

it('cleans partial files on failure and ignores duplicate failure callbacks', function (): void {
    Queue::fake();
    $imposition = DielineImposition::factory()->create(['user_id' => auth()->id(), 'status' => 'processing', 'disk' => 'local']);
    Storage::disk('local')->put('dieline-impositions/'.$imposition->id.'/sheet.svg', '<svg/>');
    $job = new GenerateDielineImposition($imposition->id);
    $job->failed(new RuntimeException('Storage failed'));
    Storage::disk('local')->assertMissing('dieline-impositions/'.$imposition->id.'/sheet.svg');
    expect($imposition->fresh()->status)->toBe('failed');
    $job->failed(new RuntimeException('Duplicate failure callback'));
    expect(auth()->user()->notifications()->count())->toBe(1);
    Queue::assertNothingPushed();
});

it('does not report success if storing an export fails halfway through', function (): void {
    $imposition = DielineImposition::factory()->create(['user_id' => auth()->id()]);
    $disk = Mockery::mock(FilesystemAdapter::class);
    $disk->shouldReceive('put')->twice()->andReturn(true, false);
    $disk->shouldReceive('deleteDirectory')->once()->with('dieline-impositions/'.$imposition->id)->andReturn(true);
    Storage::shouldReceive('disk')->with('local')->andReturn($disk);
    $nesting = Mockery::mock(DielineImpositionService::class)->makePartial();
    $nesting->shouldReceive('nest')->once()->andReturn(['placements' => [['x' => 300, 'y' => 300, 'rotation' => 0]], 'copies' => 1, 'utilisation' => 10]);
    expect(fn () => (new GenerateDielineImposition($imposition->id))->handle($nesting, app(DielineExportService::class)))->toThrow(RuntimeException::class, 'Could not store');
    expect($imposition->fresh()->status)->toBe('failed')->and($imposition->fresh()->files)->toBeNull()
        ->and(auth()->user()->notifications()->sole()->data['title'])->toBe('Imposition failed');
});
