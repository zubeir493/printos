<?php

namespace App\Services\Dielines;

use App\Models\Dieline;
use App\Services\Dielines\Renderers\DxfDielineRenderer;
use App\Services\Dielines\Renderers\SvgDielineRenderer;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DielineExportService
{
    public function __construct(
        private DielineGeometryService $geometry,
        private SvgDielineRenderer $svg,
        private DxfDielineRenderer $dxf,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function downloadFromData(array $data, string $format): StreamedResponse
    {
        $templateKey = (string) ($data['template_key'] ?? 'reverse-tuck-flap-box');
        $dimensions = (array) ($data['dimensions'] ?? []);
        $geometry = $this->geometry->generate($templateKey, $dimensions);
        $name = (string) ($data['name'] ?? $geometry['name'] ?? 'dieline');

        return $this->downloadGeometry($geometry, $name, $format);
    }

    public function downloadDieline(Dieline $dieline, string $format): StreamedResponse
    {
        return $this->downloadGeometry($dieline->geometry, $dieline->name, $format);
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    public function downloadGeometry(array $geometry, string $name, string $format): StreamedResponse
    {
        $format = strtolower($format);
        $filename = Str::slug($name ?: 'dieline').'.'.$format;

        return match ($format) {
            'pdf' => $this->pdf($geometry, $filename),
            'dxf' => response()->streamDownload(
                fn (): int => print $this->dxf->render($geometry),
                $filename,
                ['Content-Type' => 'application/dxf'],
            ),
            default => response()->streamDownload(
                fn (): int => print $this->svg->renderForSvg($geometry),
                $filename,
                ['Content-Type' => 'image/svg+xml'],
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $geometry
     */
    private function pdf(array $geometry, string $filename): StreamedResponse
    {
        $bounds = $geometry['bounds'] ?? ['width' => 210, 'height' => 297];
        $widthMm = (float) $bounds['width'];
        $heightMm = (float) $bounds['height'];
        $width = $widthMm * 72 / 25.4;
        $height = $heightMm * 72 / 25.4;

        $pdf = Pdf::loadView('dielines.pdf', [
            'svg' => $this->svg->renderForPdf($geometry),
            'widthMm' => $widthMm,
            'heightMm' => $heightMm,
        ])
            ->setPaper([0, 0, $width, $height]);

        return response()->streamDownload(
            fn (): int => print $pdf->output(),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }
}
