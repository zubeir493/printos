<?php

namespace App\Http\Controllers;

use App\Models\DielineImposition;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DielineImpositionDownloadController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(DielineImposition $dielineImposition, string $format): StreamedResponse
    {
        abort_unless($dielineImposition->user_id === auth()->id(), 403);
        abort_unless(in_array($format, ['pdf', 'svg', 'dxf'], true), 404);
        abort_unless($dielineImposition->status === 'completed' && filled($dielineImposition->files[$format] ?? null), 404);
        $disk = Storage::disk($dielineImposition->disk);
        $path = $dielineImposition->files[$format];
        abort_unless($disk->exists($path), 404);

        $filename = Str::slug($dielineImposition->name).'-'.Str::slug($dielineImposition->sheetLabel()).'-imposed.'.$format;

        return $disk->download($path, $filename, [
            'Content-Type' => match ($format) {
                'pdf' => 'application/pdf',
                'svg' => 'image/svg+xml',
                'dxf' => 'application/dxf',
            },
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
