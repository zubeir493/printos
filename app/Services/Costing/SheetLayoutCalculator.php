<?php

namespace App\Services\Costing;

class SheetLayoutCalculator
{
    /** @var array<string, array<int, array<string, mixed>>> */
    private array $candidateCache = [];

    /**
     * @param  array<string, mixed>  $stock
     * @param  array<string, mixed>  $machine
     * @param  array<string, mixed>  $spec
     * @return array<int, array<string, mixed>>
     */
    public function candidates(array $stock, array $machine, array $spec): array
    {
        $key = hash('sha256', serialize([
            array_intersect_key($stock, array_flip(['width', 'height', 'unit', 'gsm', 'grain_direction', 'average_cost'])),
            array_intersect_key($machine, array_flip(['min_sheet_width_mm', 'min_sheet_height_mm', 'max_sheet_width_mm', 'max_sheet_height_mm', 'max_print_width_mm', 'max_print_height_mm', 'side_margin_mm', 'gripper_margin_mm', 'trailing_margin_mm', 'hourly_cost', 'production_speed'])),
            array_intersect_key($spec, array_flip(['width_mm', 'height_mm', 'bleed_mm', 'gap_mm', 'grain_axis', 'unit_cost_override', 'hourly_rate_override', 'speed_override'])),
        ]));

        return $this->candidateCache[$key] ??= $this->buildCandidates($stock, $machine, $spec);
    }

    private function buildCandidates(array $stock, array $machine, array $spec): array
    {
        $parentWidth = (float) ($stock['width'] ?? 0) * 10;
        $parentHeight = (float) ($stock['height'] ?? 0) * 10;
        $width = (float) ($spec['width_mm'] ?? 0);
        $height = (float) ($spec['height_mm'] ?? 0);
        $bleed = max(0, (float) ($spec['bleed_mm'] ?? 3));
        $gap = max(0, (float) ($spec['gap_mm'] ?? 2));
        if (min($parentWidth, $parentHeight, $width, $height) <= 0) {
            return [];
        }

        $candidates = [];
        $grain = $stock['grain_direction'] ?? null;
        $grainAlongX = $grain === 'long' ? $parentWidth >= $parentHeight : $parentWidth < $parentHeight;
        foreach (range(1, 4) as $columns) {
            foreach (range(1, 4) as $rows) {
                foreach ([false, true] as $rotateSheet) {
                    $sheetWidth = floor(($rotateSheet ? $parentHeight / $rows : $parentWidth / $columns) * 10) / 10;
                    $sheetHeight = floor(($rotateSheet ? $parentWidth / $columns : $parentHeight / $rows) * 10) / 10;
                    if (! $this->fitsMachine($sheetWidth, $sheetHeight, $machine)) {
                        continue;
                    }

                    $left = (float) ($machine['side_margin_mm'] ?? 0);
                    $top = (float) ($machine['gripper_margin_mm'] ?? 0);
                    $usableWidth = min($sheetWidth - 2 * $left, (float) ($machine['max_print_width_mm'] ?? $sheetWidth));
                    $usableHeight = min($sheetHeight - $top - (float) ($machine['trailing_margin_mm'] ?? 0), (float) ($machine['max_print_height_mm'] ?? $sheetHeight));
                    foreach ([false, true] as $rotateArtwork) {
                        $axis = $spec['grain_axis'] ?? 'any';
                        $sheetGrainX = $rotateSheet ? ! $grainAlongX : $grainAlongX;
                        $requiredAxisX = ($axis === 'width') !== $rotateArtwork;
                        if ($axis !== 'any' && in_array($grain, ['long', 'short'], true) && $requiredAxisX !== $sheetGrainX) {
                            continue;
                        }
                        $cellWidth = ($rotateArtwork ? $height : $width) + 2 * $bleed;
                        $cellHeight = ($rotateArtwork ? $width : $height) + 2 * $bleed;
                        $across = max(0, (int) floor(($usableWidth + $gap + 0.000001) / ($cellWidth + $gap)));
                        $down = max(0, (int) floor(($usableHeight + $gap + 0.000001) / ($cellHeight + $gap)));
                        $ups = $across * $down;
                        if ($ups < 1) {
                            continue;
                        }
                        $cuts = $columns * $rows;
                        $parentCost = (float) ($spec['unit_cost_override'] ?? $stock['average_cost'] ?? 0);
                        if (in_array(strtolower($stock['unit'] ?? ''), ['kg', 'kilogram', 'kilograms'], true)) {
                            $parentCost *= $parentWidth * $parentHeight / 1000000 * (float) ($stock['gsm'] ?? 0) / 1000;
                        }
                        $cost = $parentCost / ($ups * $cuts)
                            + (float) ($spec['hourly_rate_override'] ?? $machine['hourly_cost'] ?? 0) / (max(1, (float) ($spec['speed_override'] ?? $machine['production_speed'] ?? 0)) * $ups);
                        $candidates[] = [
                            'key' => implode('-', [$columns, $rows, (int) $rotateSheet, (int) $rotateArtwork]),
                            'ups' => $ups, 'across' => $across, 'down' => $down, 'cuts_per_parent' => $cuts,
                            'sheet_width_mm' => $sheetWidth, 'sheet_height_mm' => $sheetHeight,
                            'parent_width_mm' => $parentWidth, 'parent_height_mm' => $parentHeight,
                            'usable_width_mm' => $usableWidth, 'usable_height_mm' => $usableHeight,
                            'left_mm' => $left, 'top_mm' => $top, 'cell_width_mm' => $cellWidth, 'cell_height_mm' => $cellHeight,
                            'gap_mm' => $gap, 'rotated' => $rotateArtwork,
                            'utilization_percent' => round($ups * $width * $height / ($sheetWidth * $sheetHeight) * 100, 2),
                            'score' => $cost,
                        ];
                    }
                }
            }
        }
        usort($candidates, fn (array $a, array $b): int => ($a['score'] <=> $b['score']) ?: ($b['ups'] <=> $a['ups']) ?: ($a['cuts_per_parent'] <=> $b['cuts_per_parent']));

        return array_values(collect($candidates)->unique(fn (array $layout): string => implode('|', [$layout['sheet_width_mm'], $layout['sheet_height_mm'], $layout['ups'], $layout['rotated']]))->all());
    }

    /** @param array<string, mixed> $machine */
    public function fitsMachine(float $width, float $height, array $machine): bool
    {
        return $width >= (float) ($machine['min_sheet_width_mm'] ?? 0)
            && $height >= (float) ($machine['min_sheet_height_mm'] ?? 0)
            && $width <= (float) ($machine['max_sheet_width_mm'] ?? 0)
            && $height <= (float) ($machine['max_sheet_height_mm'] ?? 0);
    }
}
