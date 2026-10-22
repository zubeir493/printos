<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

class RequiredStorage
{
    /** @param array<string, mixed> $options */
    public static function put(string $disk, string $path, mixed $contents, array $options = []): bool
    {
        if (! Storage::disk($disk)->put($path, $contents, $options)) {
            throw new RuntimeException('Could not store the generated file. Please try again.');
        }

        return true;
    }

    public static function putFile(string $disk, string $path, string $localPath): void
    {
        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Could not open the generated file.');
        }
        try {
            self::put($disk, $path, $stream, ['visibility' => 'private']);
            if (! Storage::disk($disk)->exists($path) || Storage::disk($disk)->size($path) !== filesize($localPath)) {
                throw new RuntimeException('The uploaded file could not be verified.');
            }
        } finally {
            fclose($stream);
        }
    }
}
