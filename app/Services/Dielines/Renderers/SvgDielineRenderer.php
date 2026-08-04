<?php

namespace App\Services\Dielines\Renderers;

use Illuminate\Support\HtmlString;
use Illuminate\Support\Number;

class SvgDielineRenderer
{
    private const PDF_COORDINATE_SCALE = 3.7795275591;

    private const PDF_STROKE_WIDTH = '0.333333';

    /**
     * @param  array<string, mixed>  $geometry
     */
    public function render(array $geometry): string
    {
        return $this->renderSvg($geometry);
    }

    /**
     * Render a print-ready SVG for PDF export.
     *
     * @param  array<string, mixed>  $geometry
     */
    public function renderForPdf(array $geometry): string
    {
        return $this->renderSvg($geometry, forPdf: true, forExport: true);
    }

    /**
     * Render a print-ready SVG download.
     *
     * @param  array<string, mixed>  $geometry
     */
    public function renderForSvg(array $geometry): string
    {
        return $this->renderSvg($geometry, forExport: true);
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    private function renderSvg(array $geometry, bool $forPdf = false, bool $forExport = false): string
    {
        $bounds = $geometry['bounds'] ?? ['width' => 100, 'height' => 100];
        $width = max(1, (float) $bounds['width']);
        $height = max(1, (float) $bounds['height']);
        $originX = (float) ($bounds['x'] ?? 0);
        $originY = (float) ($bounds['y'] ?? 0);
        $padding = $forExport ? 0 : max(12, min($width, $height) * 0.05);
        $coordinateScale = $forPdf ? self::PDF_COORDINATE_SCALE : 1;
        $viewBox = implode(' ', [
            $this->number(($originX - $padding) * $coordinateScale),
            $this->number(($originY - $padding) * $coordinateScale),
            $this->number(($width + ($padding * 2)) * $coordinateScale),
            $this->number(($height + ($padding * 2)) * $coordinateScale),
        ]);
        $viewBoxWidth = $width + ($padding * 2);
        $viewBoxHeight = $height + ($padding * 2);
        $sizeAttributes = $forExport
            ? ' width="'.$this->number($viewBoxWidth).'mm" height="'.$this->number($viewBoxHeight).'mm"'
            : '';
        $strokeWidth = $forExport ? ($forPdf ? self::PDF_STROKE_WIDTH : '0.25pt') : '1.5';
        $bleedColor = $forExport ? '#ff00ff' : '#d97706';
        $creaseColor = $forExport ? '#000000' : '#2563eb';
        $cutColor = $forExport ? '#ff0000' : '#111827';
        $bleedDash = $forExport ? null : '6 5';
        $creaseDash = $forExport ? null : '8 6';

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="'.$viewBox.'"'.$sizeAttributes.' role="img" aria-label="'.$this->escape($geometry['name'] ?? 'Dieline').'">'.
            '<g stroke-linecap="round" stroke-linejoin="round" fill="none">'.
            $this->layer($geometry, 'bleed', $bleedColor, $strokeWidth, $bleedDash, $forExport, $coordinateScale).
            ($forExport ? '' : $this->areas($geometry['layers']['glue'] ?? [])).
            $this->layer($geometry, 'crease', $creaseColor, $strokeWidth, $creaseDash, $forExport, $coordinateScale).
            $this->layer($geometry, 'cut', $cutColor, $strokeWidth, null, $forExport, $coordinateScale).
            '</g>'.
            ($forExport ? '' : $this->labels($geometry['labels'] ?? [], '#374151')).
            '</svg>';
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    public function preview(array $geometry): HtmlString
    {
        return new HtmlString(
            '<div class="dieline-preview-frame">'.
                '<div class="dieline-preview-toolbar">'.
                '<span>'.e($geometry['name'] ?? 'Dieline').'</span>'.
                '<span>'.e($this->sizeLabel($geometry)).'</span>'.
                '</div>'.
                '<div class="dieline-preview-artboard">'.$this->render($geometry).'</div>'.
                '<div class="dieline-preview-legend">'.
                '<span><i class="dieline-legend-line dieline-legend-cut"></i><strong>Cut</strong></span>'.
                '<span><i class="dieline-legend-line dieline-legend-crease"></i><strong>Crease</strong></span>'.
                '<span><i class="dieline-legend-area dieline-legend-glue"></i><strong>Glue</strong></span>'.
                '<span><i class="dieline-legend-line dieline-legend-bleed"></i><strong>Bleed</strong></span>'.
                '</div>'.
                '</div>'
        );
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    private function layer(array $geometry, string $layer, string $color, string $width, ?string $dash = null, bool $forExport = false, float $coordinateScale = 1): string
    {
        $dashAttribute = $dash ? ' stroke-dasharray="'.$dash.'"' : '';
        $vectorEffect = $forExport ? '' : ' vector-effect="non-scaling-stroke"';

        return collect($geometry['layers'][$layer] ?? [])
            ->map(fn (array $line): string => '<line x1="'.$this->coordinate((float) $line['x1'], $coordinateScale).'" y1="'.$this->coordinate((float) $line['y1'], $coordinateScale).'" x2="'.$this->coordinate((float) $line['x2'], $coordinateScale).'" y2="'.$this->coordinate((float) $line['y2'], $coordinateScale).'" stroke="'.$color.'" stroke-width="'.$width.'"'.$dashAttribute.$vectorEffect.'/>')
            ->implode('');
    }

    private function coordinate(float $value, float $scale): string
    {
        return $scale === 1 ? (string) $value : $this->number($value * $scale);
    }

    /**
     * @param  array<int, array{points: array<int, array{x: float, y: float}>}>  $areas
     */
    private function areas(array $areas): string
    {
        return collect($areas)
            ->map(function (array $area): string {
                $points = collect($area['points'])
                    ->map(fn (array $point): string => $point['x'].','.$point['y'])
                    ->implode(' ');

                return '<polygon points="'.$points.'" fill="#dcfce7" stroke="#16a34a" stroke-width="1.2" vector-effect="non-scaling-stroke"/>';
            })
            ->implode('');
    }

    /**
     * @param  array<int, array{x: float, y: float, text: string}>  $labels
     */
    private function labels(array $labels, string $color): string
    {
        return collect($labels)
            ->map(fn (array $label): string => '<text x="'.$label['x'].'" y="'.$label['y'].'" text-anchor="middle" dominant-baseline="middle" font-family="Plus Jakarta Sans, Arial, sans-serif" font-size="5" fill="'.$color.'">'.$this->escape($label['text']).'</text>')
            ->implode('');
    }

    private function number(float $value): string
    {
        return Number::format($value, maxPrecision: 3);
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    private function sizeLabel(array $geometry): string
    {
        $bounds = $geometry['bounds'] ?? ['width' => 0, 'height' => 0];

        return Number::format((float) $bounds['width'], maxPrecision: 1).' x '.Number::format((float) $bounds['height'], maxPrecision: 1).' mm';
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
