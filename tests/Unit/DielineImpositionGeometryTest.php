<?php

use App\Models\DielineImposition;
use App\Services\Dielines\DielineImpositionService;
use App\Services\Dielines\DielineTemplateRegistry;
use App\Services\Dielines\Renderers\SvgDielineRenderer;
use App\Services\Dielines\Templates\ReverseTuckFlapBoxTemplate;
use Illuminate\Support\Facades\Process;
use Tests\TestCase;

uses(TestCase::class);

it('keeps the dense small-carton B1 layout within the search budget', function (int $budget): void {
    $source = (new ReverseTuckFlapBoxTemplate)->generate(['l' => 60, 'w' => 30, 'h' => 30, 'tuck_flap' => 12, 'glue_flap' => 10]);
    $imposition = new DielineImposition(['name' => 'Reverse Tuck Carton', 'paper_size' => 'b1', 'paper_width' => 707, 'paper_height' => 1000, 'margin' => 0, 'gap' => 0, 'source_geometry' => $source]);
    $layout = app(DielineImpositionService::class)->nest($imposition, $budget);
    expect($layout['copies'])->toBeGreaterThanOrEqual(39);
})->with([1000, 5000]);

it('reuses nesting results only for the same geometry, sheet, clearance and search quality', function (): void {
    $source = (new ReverseTuckFlapBoxTemplate)->generate(['l' => 60, 'w' => 30, 'h' => 30, 'tuck_flap' => 12, 'glue_flap' => 10]);
    $imposition = new DielineImposition(['paper_width' => 707, 'paper_height' => 1000, 'margin' => 0, 'gap' => 0, 'source_geometry' => $source]);
    $layout = ['placements' => [['x' => 20, 'y' => 80, 'rotation' => 0]], 'copies' => 1, 'utilisation' => 10];
    Process::fake(['*' => Process::result(output: json_encode($layout, JSON_THROW_ON_ERROR))]);
    $service = app(DielineImpositionService::class);

    expect($service->nest($imposition))->toBe($layout);
    $imposition->name = 'Renamed dieline';
    expect($service->nest($imposition))->toBe($layout);
    Process::assertRanTimes(fn (): bool => true, 1);

    foreach (['paper_width' => 708, 'paper_height' => 1001, 'margin' => 1, 'gap' => 1, 'source_geometry' => [...$source, 'layers' => []]] as $field => $value) {
        $changed = clone $imposition;
        $changed->{$field} = $value;
        $service->nest($changed);
    }
    $service->nest($imposition, 1200);
    Process::assertRanTimes(fn (): bool => true, 7);
});

it('adds more than four copies to the A1 reverse tuck layout after compaction', function (): void {
    $source = (new ReverseTuckFlapBoxTemplate)->generate(['l' => 100, 'w' => 80, 'h' => 80, 'tuck_flap' => 15, 'glue_flap' => 10]);
    $imposition = new DielineImposition(['name' => 'Reverse Tuck Carton', 'paper_size' => 'a1', 'paper_width' => 594, 'paper_height' => 841, 'margin' => 0, 'gap' => 0, 'source_geometry' => $source]);
    config(['dielines.imposition.search_ms' => 5000]);
    $layout = app(DielineImpositionService::class)->nest($imposition);
    expect($layout['copies'])->toBeGreaterThan(4);
});

it('compacts the short reverse tuck carton beyond its old rectangular grid spacing', function (): void {
    $source = (new ReverseTuckFlapBoxTemplate)->generate(['l' => 100, 'w' => 80, 'h' => 30, 'tuck_flap' => 15, 'glue_flap' => 10]);
    $imposition = new DielineImposition(['name' => 'Reverse Tuck Carton', 'paper_size' => 'a1', 'paper_width' => 594, 'paper_height' => 841, 'margin' => 0, 'gap' => 0, 'source_geometry' => $source]);
    config(['dielines.imposition.search_ms' => 300]);
    $service = app(DielineImpositionService::class);
    $layout = $service->nest($imposition);
    expect($layout['copies'])->toBeGreaterThanOrEqual(4);
    $sheet = $service->sheetGeometry($imposition, $layout['placements']);
    $xs = array_merge(array_column($sheet['layers']['cut'], 'x1'), array_column($sheet['layers']['cut'], 'x2'));
    $ys = array_merge(array_column($sheet['layers']['cut'], 'y1'), array_column($sheet['layers']['cut'], 'y2'));
    expect(min($xs))->toEqualWithDelta(0, 0.001)->and(min($ys))->toEqualWithDelta(0, 0.001);
    if ($layout['copies'] === 4) {
        expect((max($xs) - min($xs)) * (max($ys) - min($ys)))->toBeLessThan(2 * 218 * 2 * 369.5);
    }
});

it('nests every blueprint with its true outline and preserves full-size manufacturing lines', function (string $key, string $class): void {
    $template = new $class;
    $source = $template->generate($template->defaults());
    $imposition = new DielineImposition(['name' => $template->name(), 'paper_size' => 'b0', 'paper_width' => 1000, 'paper_height' => 1414, 'margin' => 0, 'gap' => 0, 'source_geometry' => $source]);
    config(['dielines.imposition.search_ms' => 300]);
    $service = app(DielineImpositionService::class);
    $layout = $service->nest($imposition);
    expect($layout['copies'])->toBeGreaterThan(0)
        ->and($layout['utilisation'])->toBeGreaterThan(0)->toBeLessThanOrEqual(100);
    $sheet = $service->sheetGeometry($imposition, $layout['placements']);
    foreach (['cut', 'crease', 'perforation', 'cut_crease', 'bleed'] as $layer) {
        $lines = $source['layers'][$layer] ?? [];
        expect($sheet['layers'][$layer])->toHaveCount(count($lines) * $layout['copies']);
        $maximumLengthError = 0;
        $outsideSheet = false;
        foreach ($sheet['layers'][$layer] as $index => $line) {
            $original = $lines[$index % count($lines)];
            $maximumLengthError = max($maximumLengthError, abs(hypot($line['x2'] - $line['x1'], $line['y2'] - $line['y1']) - hypot($original['x2'] - $original['x1'], $original['y2'] - $original['y1'])));
            foreach ([1, 2] as $end) {
                $outsideSheet = $outsideSheet || $line['x'.$end] < -0.001 || $line['x'.$end] > 1000.001 || $line['y'.$end] < -0.001 || $line['y'.$end] > 1414.001;
            }
        }
        expect($maximumLengthError)->toBeLessThanOrEqual(0.00001)->and($outsideSheet)->toBeFalse();
    }
    $svg = (new SvgDielineRenderer)->renderForSvg($sheet);
    expect($svg)->toContain('width="1000mm"', 'height="1414mm"', 'viewBox="0 0 1000 1414"');
})->with(fn (): array => collect((new DielineTemplateRegistry)->fallbackTypes())->map(fn ($class, $key) => [$key, $class])->all());
