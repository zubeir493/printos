<?php

namespace App\Services\Dielines\Renderers;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class SvgDielineRenderer
{
    /**
     * @param  array<string, mixed>  $geometry
     */
    public function render(array $geometry): string
    {
        $bounds = $geometry['bounds'] ?? ['width' => 100, 'height' => 100];
        $width = max(1, (float) $bounds['width']);
        $height = max(1, (float) $bounds['height']);
        $originX = (float) ($bounds['x'] ?? 0);
        $originY = (float) ($bounds['y'] ?? 0);
        $padding = max(12, min($width, $height) * 0.05);
        $viewBox = implode(' ', [
            Number::format($originX - $padding, maxPrecision: 3),
            Number::format($originY - $padding, maxPrecision: 3),
            Number::format($width + ($padding * 2), maxPrecision: 3),
            Number::format($height + ($padding * 2), maxPrecision: 3),
        ]);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="' . $viewBox . '" role="img" aria-label="' . $this->escape($geometry['name'] ?? 'Dieline') . '">' .
            '<g stroke-linecap="round" stroke-linejoin="round" fill="none">' .
            $this->layer($geometry, 'bleed', '#d97706', '1.2', '6 5') .
            $this->areas($geometry['layers']['glue'] ?? []) .
            $this->layer($geometry, 'crease', '#2563eb', '1.5', '8 6') .
            $this->layer($geometry, 'cut', '#111827', '1.5') .
            '</g>' .
            $this->labels($geometry['labels'] ?? []) .
            '</svg>';
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    public function preview(array $geometry): HtmlString
    {
        return new HtmlString(
            '<div class="dieline-preview-frame">' .
                '<div class="dieline-preview-toolbar">' .
                '<span>' . e($geometry['name'] ?? 'Dieline') . '</span>' .
                '<span>' . e($this->sizeLabel($geometry)) . '</span>' .
                '</div>' .
                '<div class="dieline-preview-artboard">' . $this->render($geometry) . '</div>' .
                '<div class="dieline-preview-legend">' .
                '<span><i class="dieline-legend-line dieline-legend-cut"></i><strong>Cut</strong></span>' .
                '<span><i class="dieline-legend-line dieline-legend-crease"></i><strong>Crease</strong></span>' .
                '<span><i class="dieline-legend-area dieline-legend-glue"></i><strong>Glue</strong></span>' .
                '<span><i class="dieline-legend-line dieline-legend-bleed"></i><strong>Bleed</strong></span>' .
                '</div>' .
                '</div>'
        );
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    private function layer(array $geometry, string $layer, string $color, string $width, ?string $dash = null): string
    {
        $dashAttribute = $dash ? ' stroke-dasharray="' . $dash . '"' : '';

        return collect($geometry['layers'][$layer] ?? [])
            ->map(fn(array $line): string => '<line x1="' . $line['x1'] . '" y1="' . $line['y1'] . '" x2="' . $line['x2'] . '" y2="' . $line['y2'] . '" stroke="' . $color . '" stroke-width="' . $width . '"' . $dashAttribute . ' vector-effect="non-scaling-stroke"/>')
            ->implode('');
    }

    /**
     * @param  array<int, array{points: array<int, array{x: float, y: float}>}>  $areas
     */
    private function areas(array $areas): string
    {
        return collect($areas)
            ->map(function (array $area): string {
                $points = collect($area['points'])
                    ->map(fn(array $point): string => $point['x'] . ',' . $point['y'])
                    ->implode(' ');

                return '<polygon points="' . $points . '" fill="#dcfce7" stroke="#16a34a" stroke-width="1.2" vector-effect="non-scaling-stroke"/>';
            })
            ->implode('');
    }

    /**
     * @param  array<int, array{x: float, y: float, text: string}>  $labels
     */
    private function labels(array $labels): string
    {
        return collect($labels)
            ->map(fn(array $label): string => '<text x="' . $label['x'] . '" y="' . $label['y'] . '" text-anchor="middle" dominant-baseline="middle" font-family="Plus Jakarta Sans, Arial, sans-serif" font-size="5" fill="#374151">' . $this->escape($label['text']) . '</text>')
            ->implode('');
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    private function sizeLabel(array $geometry): string
    {
        $bounds = $geometry['bounds'] ?? ['width' => 0, 'height' => 0];

        return Number::format((float) $bounds['width'], maxPrecision: 1) . ' x ' . Number::format((float) $bounds['height'], maxPrecision: 1) . ' mm';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
