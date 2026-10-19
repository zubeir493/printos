<?php

namespace App\Observers;

use App\Models\TextFile;

class TextFileObserver
{
    public function created(TextFile $textFile): void
    {
        if ($textFile->is_approved) {
            $textFile->jobOrder?->updateStatus();
        }
    }

    public function deleted(TextFile $textFile): void
    {
        $textFile->jobOrder?->updateStatus();
    }

    public function updated(TextFile $textFile): void
    {
        if ($textFile->wasChanged(['is_approved', 'deliverable'])) {
            $textFile->jobOrder?->updateStatus();
        }
    }
}
