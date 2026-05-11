<?php

namespace App\Console\Commands;

use App\Models\JobOrder;
use App\Models\User;
use App\Notifications\JobOrderLateNotification;
use App\UserRole;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyLateJobOrders extends Command
{
    protected $signature = 'job-orders:notify-late';

    protected $description = 'Notify admin and operations users about job orders past their submission date';

    public function handle(): int
    {
        $this->info('Checking for late job orders...');

        // Only notify once per job order — skip those already notified
        $lateJobOrders = JobOrder::late()
            ->whereNull('notified_late_at')
            ->get();

        if ($lateJobOrders->isEmpty()) {
            $this->info('No new late job orders found.');

            return Command::SUCCESS;
        }

        $recipients = User::whereIn('role', [
            UserRole::Admin->value,
            UserRole::Operations->value,
        ])->get();

        foreach ($lateJobOrders as $jobOrder) {
            $this->line("Job order {$jobOrder->job_order_number} is late.");

            if ($recipients->isNotEmpty()) {
                Notification::send($recipients, new JobOrderLateNotification($jobOrder));
            }

            // Mark as notified so we don't spam every day
            $jobOrder->updateQuietly(['notified_late_at' => now()]);
        }

        $this->info("Notified about {$lateJobOrders->count()} late job order(s).");

        return Command::SUCCESS;
    }
}
