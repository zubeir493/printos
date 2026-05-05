<?php

namespace App\Support;

use DateTimeInterface;
use Filament\Forms\Components\BaseFileUpload;
use Illuminate\Support\Facades\URL;

class PrivateStorage
{
    public static function diskName(?string $disk = null): string
    {
        return $disk ?: config('filesystems.private_disk', 's3');
    }

    public static function url(
        ?string $path,
        ?DateTimeInterface $expiresAt = null,
        ?string $disk = null,
        string $disposition = 'inline',
        ?string $name = null,
    ): ?string {
        if (blank($path)) {
            return null;
        }

        $disk = self::diskName($disk);

        return URL::temporarySignedRoute(
            'private-storage.show',
            $expiresAt ?? now()->addMinutes(60),
            array_filter([
                'disk' => $disk,
                'path' => ltrim($path, '/'),
                'disposition' => $disposition,
                'name' => $name ?: basename($path),
            ], fn ($value) => filled($value)),
        );
    }

    public static function downloadUrl(?string $path, ?DateTimeInterface $expiresAt = null, ?string $disk = null): ?string
    {
        return self::url($path, $expiresAt, self::diskName($disk), 'attachment');
    }

    public static function uploadedFileInfo(BaseFileUpload $component, string $file, string|array|null $storedFileNames): ?array
    {
        return [
            'name' => ($component->isMultiple() ? ($storedFileNames[$file] ?? null) : $storedFileNames) ?? basename($file),
            'size' => 0,
            'type' => self::mimeTypeFromPath($file),
            'url' => self::url(
                $file,
                now()->addMinutes(config('filament.temporary_file_url_expiry_minutes', 30))->endOfHour(),
                $component->getDiskName(),
            ),
        ];
    }

    private static function mimeTypeFromPath(string $path): string
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
