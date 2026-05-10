<?php

namespace App\Observers;

use App\Models\MaterialRequest;
use App\Models\User;
use App\Notifications\MaterialRequestCreatedNotification;
use App\UserRole;
use Illuminate\Support\Facades\Notification;

class MaterialRequestObserver
{
    public function created(MaterialRequest $materialRequest): void
    {
        $recipients = User::whereIn('role', [UserRole::Warehouse->value, UserRole::Admin->value])->get();

        if ($recipients->isNotEmpty()) {
            Notification::send($recipients, new MaterialRequestCreatedNotification($materialRequest));
        }
    }
}
