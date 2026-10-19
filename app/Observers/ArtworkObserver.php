<?php

namespace App\Observers;

use App\Models\Artwork;
use App\Models\JobOrder;
use App\Models\User;
use App\Notifications\ArtworkApprovedNotification;
use App\Notifications\ArtworkUploadedNotification;
use App\UserRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;

class ArtworkObserver
{
    public function saving(Artwork $artwork): void
    {
        if (auth()->check()) {
            Gate::authorize('uploadToJob', [Artwork::class, JobOrder::find($artwork->job_order_id)]);
            if ($artwork->isDirty('is_approved') && ($artwork->exists || $artwork->is_approved)) {
                Gate::authorize('approve', $artwork);
            }
        }
    }

    public function creating(Artwork $artwork): void
    {
        if (! $artwork->uploaded_by && Auth::check()) {
            $artwork->uploaded_by = Auth::id();
        }
    }

    public function created(Artwork $artwork): void
    {
        $recipients = User::whereIn('role', [UserRole::Admin->value, UserRole::Operations->value])->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new ArtworkUploadedNotification($artwork));
        }

        // If artwork was created as approved, update the task status
        if ($artwork->is_approved) {
            $artwork->jobOrder?->updateStatus();
        }
    }

    public function deleted(Artwork $artwork): void
    {
        $artwork->jobOrder?->updateStatus();
    }

    public function updating(Artwork $artwork): void
    {
        if ($artwork->isDirty(['filename', 'deliverable', 'job_order_id'])) {
            $artwork->is_approved = false;
        }
    }

    public function updated(Artwork $artwork): void
    {
        if ($artwork->wasChanged('job_order_id')) {
            JobOrder::find($artwork->getRawOriginal('job_order_id'))?->updateStatus();
            $artwork->unsetRelation('jobOrder');
        }
        if ($artwork->wasChanged(['is_approved', 'deliverable', 'filename', 'job_order_id'])) {
            $artwork->jobOrder?->updateStatus();
        }
        if ($artwork->wasChanged('is_approved') && $artwork->is_approved) {
            $artwork->jobOrder?->updateStatus();

            // Notify the designer who uploaded the artwork
            $uploader = $artwork->uploader;

            if ($uploader) {
                $uploader->notify(new ArtworkApprovedNotification($artwork));
            }
        }
    }
}
