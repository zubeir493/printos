<?php

namespace App\Observers;

use App\Models\TextFile;

class TextFileObserver
{
    public function created(TextFile $textFile): void
    {
        if ($textFile->is_approved) {
            $textFile->jobOrderTask?->updateStatus();
        }
    }

    public function updated(TextFile $textFile): void
    {
        if ($textFile->wasChanged('is_approved') && $textFile->is_approved) {
            $textFile->jobOrderTask?->updateStatus();
        }
    }
}
