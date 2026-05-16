<?php

use Filament\Actions\Exports\Exporter;
use Illuminate\Support\Facades\File;

test('filament exporters complete synchronously so download notifications are sent immediately', function (): void {
    $exporterClasses = collect(File::files(app_path('Filament/Exports')))
        ->map(fn (SplFileInfo $file): string => 'App\\Filament\\Exports\\'.$file->getBasename('.php'))
        ->filter(fn (string $class): bool => is_subclass_of($class, Exporter::class));

    expect($exporterClasses)->not->toBeEmpty();

    $exporterClasses->each(function (string $class): void {
        expect(file_get_contents((new ReflectionClass($class))->getFileName()))
            ->toContain('use RunsExportsSynchronously;');
    });
});
