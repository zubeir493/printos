<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PrivateStorageController extends Controller
{
    public function show(Request $request, string $disk, string $path): StreamedResponse
    {
        $allowedDisks = array_unique(array_filter([
            config('filesystems.private_disk'),
            's3',
            'b2',
        ]));
        abort_unless(in_array($disk, $allowedDisks, true), 404);

        $storage = Storage::disk($disk);
        $path = ltrim($path, '/');

        try {
            $stream = $storage->readStream($path);
        } catch (Throwable $e) {
            throw new RuntimeException('Private storage file could not be read: '.$e->getMessage(), 0, $e);
        }

        abort_if($stream === false, 404);

        $name = basename((string) $request->query('name', basename($path)));
        $disposition = $request->query('disposition') === 'attachment' ? 'attachment' : 'inline';
        $mimeType = $this->mimeTypeFromPath($path);

        return response()->stream(function () use ($stream): void {
            fpassthru($stream);

            if (is_resource($stream)) {
                fclose($stream);
            }
        }, 200, array_filter([
            'Content-Type' => $mimeType,
            'Content-Disposition' => (new ResponseHeaderBag)->makeDisposition($disposition, $name),
            'Cache-Control' => 'private, max-age=0, no-store',
        ]));
    }

    private function mimeTypeFromPath(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'pdf' => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'tif', 'tiff' => 'image/tiff',
            'csv' => 'text/csv',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            default => 'application/octet-stream',
        };
    }
}
