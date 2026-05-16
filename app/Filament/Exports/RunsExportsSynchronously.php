<?php

namespace App\Filament\Exports;

trait RunsExportsSynchronously
{
    public function getJobConnection(): ?string
    {
        return 'sync';
    }
}
