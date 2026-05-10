<?php

namespace App\Observers;

use App\Models\Artwork;
use App\Models\User;
use App\Notifications\ArtworkApprovedNotification;
use App\Notifications\ArtworkUploadedNotification;
use App\UserRole;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;

class ArtworkObserver
{
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
            $artwork->jobOrderTask?->updateStatus();
        }
    }

    public function updated(Artwork $artwork): void
    {
        if ($artwork->wasChanged('is_approved') && $artwork->is_approved) {
            $artwork->jobOrderTask?->updateStatus();

            // Notify the designer who uploaded the artwork
            $uploader = $artwork->uploader;

            if ($uploader) {
                $uploader->notify(new ArtworkApprovedNotification($artwork));
            }
        }
    }
}
