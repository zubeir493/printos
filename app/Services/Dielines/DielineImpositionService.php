<?php

namespace App\Services\Dielines;

use App\Models\DielineImposition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class DielineImpositionService
{
    /** @return array{placements: array<int, array{x: float, y: float, rotation: int}>, copies: int, utilisation: float} */
    public function nest(DielineImposition $imposition, ?int $searchMilliseconds = null): array
    {
        $input = json_encode([
            'geometry' => $imposition->source_geometry,
            'sheet' => ['width' => $imposition->paper_width, 'height' => $imposition->paper_height],
            'margin' => $imposition->margin, 'gap' => $imposition->gap,
            'search_ms' => $searchMilliseconds ?? config('dielines.imposition.search_ms'), 'max_copies' => config('dielines.imposition.max_copies'),
        ], JSON_THROW_ON_ERROR);

        return Cache::remember('dieline-nesting:v1:'.hash('sha256', $input), now()->addHour(), function () use ($input): array {
            $result = Process::path(base_path())->timeout(40)->input($input)
                ->run([config('dielines.imposition.node_binary'), '--max-old-space-size=512', base_path('resources/js/dielines/nest.mjs')]);

            if ($result->failed()) {
                throw new RuntimeException(trim($result->errorOutput()) ?: 'The nesting engine could not generate this layout.');
            }
            $layout = json_decode($result->output(), true, flags: JSON_THROW_ON_ERROR);
            if (empty($layout['placements']) || count($layout['placements']) > config('dielines.imposition.max_copies')) {
                throw new RuntimeException('The nesting engine returned an invalid layout.');
            }

            return $layout;
        });
    }

    /**
     * @param  array<int, array{x: float, y: float, rotation: int}>  $placements
     * @return array<string, mixed>
     */
    public function sheetGeometry(DielineImposition $imposition, array $placements): array
    {
        $geometry = ['name' => $imposition->name.' — '.$imposition->sheetLabel(), 'bounds' => ['x' => 0, 'y' => 0, 'width' => $imposition->paper_width, 'height' => $imposition->paper_height], 'layers' => ['glue' => []], 'labels' => []];
        foreach ($placements as $placement) {
            $angle = deg2rad($placement['rotation']);
            $cos = round(cos($angle), 10);
            $sin = round(sin($angle), 10);
            foreach (['cut', 'crease', 'cut_crease', 'perforation', 'bleed'] as $layer) {
                $geometry['layers'][$layer] ??= [];
                foreach ($imposition->source_geometry['layers'][$layer] ?? [] as $line) {
                    $transformed = [];
                    foreach ([1, 2] as $end) {
                        $x = $line['x'.$end];
                        $y = $line['y'.$end];
                        $transformed['x'.$end] = $x * $cos - $y * $sin + $placement['x'];
                        $transformed['y'.$end] = $x * $sin + $y * $cos + $placement['y'];
                    }
                    $geometry['layers'][$layer][] = $transformed;
                }
            }
        }

        return $geometry;
    }
}
