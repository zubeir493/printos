<?php

namespace App\Observers;

use App\Models\JobOrder;
use App\Models\TextFile;
use Illuminate\Support\Facades\Gate;

class TextFileObserver
{
    public function saving(TextFile $textFile): void
    {
        if (auth()->check()) {
            Gate::authorize('uploadToJob', [TextFile::class, JobOrder::find($textFile->job_order_id)]);
            if ($textFile->isDirty('is_approved') && ($textFile->exists || $textFile->is_approved)) {
                Gate::authorize('approve', $textFile);
            }
        }
    }

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

    public function updating(TextFile $textFile): void
    {
        if ($textFile->isDirty(['filename', 'deliverable', 'job_order_id'])) {
            $textFile->is_approved = false;
        }
    }

    public function updated(TextFile $textFile): void
    {
        if ($textFile->wasChanged('job_order_id')) {
            JobOrder::find($textFile->getRawOriginal('job_order_id'))?->updateStatus();
            $textFile->unsetRelation('jobOrder');
        }
        if ($textFile->wasChanged(['is_approved', 'deliverable', 'filename', 'job_order_id'])) {
            $textFile->jobOrder?->updateStatus();
        }
    }
}
