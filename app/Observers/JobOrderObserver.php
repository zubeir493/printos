<?php

namespace App\Observers;

use App\Models\JobOrder;
use App\Models\Setting;
use App\Notifications\DesignerAssignedToTask;
use App\Notifications\JobOrderCancelledNotification;
use App\Notifications\JobOrderCompletedNotification;
use App\Notifications\ProductionTaskCompletedNotification;
use App\Notifications\TaskSentToProductionNotification;
use App\Notifications\TypistAssignedToTask;
use App\Services\Accounting\CreateSalesJournalEntry;
use App\Support\NotificationRecipients;
use App\Support\SequentialNumber;
use App\UserRole;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;

class JobOrderObserver
{
    public function creating(JobOrder $jobOrder): void
    {
        if (blank($jobOrder->job_order_number)) {
            $jobOrder->job_order_number = SequentialNumber::next(
                lockName: 'job_orders', modelClass: JobOrder::class,
                column: 'job_order_number', prefix: 'JO-', padding: 6, likePattern: 'JO-%',
            );
        }
    }

    public function saving(JobOrder $jobOrder): void
    {
        if ($jobOrder->exists && (
            ($jobOrder->pricingIsLocked() && $jobOrder->isDirty(['subtotal', 'tax_amount']))
            || (((string) $jobOrder->getRawOriginal('status') !== 'draft' || $jobOrder->proforma_task_id)
                && $jobOrder->isDirty(['quantity', 'name', 'production_mode', 'job_type', 'partner_id']))
        )) {
            throw ValidationException::withMessages(['subtotal' => 'Started jobs and accepted quotation specifications cannot be repriced.']);
        }
        if ($jobOrder->uses_production_ticket && $jobOrder->isInternal()) {
            $jobOrder->forceFill([
                'partner_id' => null, 'due_date' => null, 'subtotal' => 0, 'tax_amount' => 0,
                'total' => 0, 'advance_amount' => 0, 'advance_paid' => false,
            ]);
        }
        if ($jobOrder->uses_production_ticket && $jobOrder->exists && ! $jobOrder->proforma_task_id
            && $jobOrder->isDirty(['quantity', 'job_type', 'size', 'services', 'paper', 'production_mode', 'partner_id'])) {
            $jobOrder->forceFill(['cost_reviewed_at' => null, 'cost_reviewed_by' => null]);
            if ($jobOrder->isDirty(['quantity', 'job_type', 'production_mode', 'partner_id']) && $jobOrder->cost_estimate_id) {
                $jobOrder->forceFill(['cost_estimate_id' => null, 'estimated_production_costs' => null]);
            }
        }
        if ($jobOrder->isDirty('subtotal') && ! $jobOrder->proforma_task_id && ! $jobOrder->isDirty('tax_amount')) {
            $settings = Setting::getSettings();
            $jobOrder->tax_amount = round((float) $jobOrder->subtotal * ($settings->vat_enabled ? (float) $settings->vat_rate / 100 : 0), 2);
        }
        if ($jobOrder->isDirty(['subtotal', 'tax_amount'])) {
            $jobOrder->total = round((float) $jobOrder->subtotal + (float) $jobOrder->tax_amount, 2);
        }
        if ($jobOrder->isDirty('status')) {
            $status = (string) $jobOrder->status;
            if ($status === 'cancelled' && $jobOrder->exists && $jobOrder->requiresCostedStockReceipt()
                && ($jobOrder->recorded_output_quantity > 0 || $jobOrder->issuedBalanceByWarehouse()->flatten(1)->contains(fn ($line): bool => (float) $line->net_quantity !== 0.0))) {
                throw ValidationException::withMessages(['status' => 'Return outstanding materials before cancelling. A run with logged good output must be reconciled before cancellation.']);
            }
            if ($status === 'production') {
                $issues = $jobOrder->productionReleaseIssues();
                if ((string) $jobOrder->getRawOriginal('status') !== 'completed' && $issues !== []) {
                    throw ValidationException::withMessages(['status' => $issues]);
                }
                $jobOrder->production_started_at ??= now();
                if ((string) $jobOrder->getRawOriginal('status') === 'completed') {
                    $jobOrder->completed_at = null;
                }
                if ($jobOrder->isReadyForProduction()) {
                    $jobOrder->design_completed_at ??= now();
                }
            }
            if ($status === 'completed' && $jobOrder->exists) {
                if (! $jobOrder->canComplete()) {
                    throw ValidationException::withMessages(['status' => 'Production and payment requirements are not complete.']);
                }
                $jobOrder->completed_at ??= now();
            }
        }
    }

    public function created(JobOrder $jobOrder): void
    {
        $this->notifyAssignments($jobOrder, true);
        if ($jobOrder->designer_id || $jobOrder->typist_id) {
            $jobOrder->updateStatus();
        }
    }

    public function updated(JobOrder $jobOrder): void
    {
        $this->notifyAssignments($jobOrder);
        if ($jobOrder->wasChanged('status')) {
            $status = (string) $jobOrder->status;
            if ($status === 'completed' && $jobOrder->production_mode === 'make_to_order') {
                app(CreateSalesJournalEntry::class)->handle($jobOrder);
            }
            $notification = match ($status) {
                'production' => new TaskSentToProductionNotification($jobOrder),
                'completed' => new JobOrderCompletedNotification($jobOrder),
                'cancelled' => new JobOrderCancelledNotification($jobOrder),
                default => null,
            };
            if ($notification) {
                $roles = match ($status) {
                    'production' => [UserRole::Production],
                    'cancelled' => [UserRole::Admin, UserRole::Operations, UserRole::Sales, UserRole::Finance, UserRole::Production, UserRole::Warehouse],
                    default => [UserRole::Operations, UserRole::Sales, UserRole::Finance],
                };
                DB::afterCommit(fn () => Notification::send(NotificationRecipients::roles(...$roles), $notification));
            }
        }
        if ($jobOrder->wasChanged('production_completed_at') && $jobOrder->production_completed_at) {
            DB::afterCommit(fn () => Notification::send(NotificationRecipients::roles(UserRole::Operations, UserRole::Sales), new ProductionTaskCompletedNotification($jobOrder)));
        }
        if ($jobOrder->wasChanged(['designer_id', 'typist_id']) && ! $jobOrder->wasChanged('status')) {
            $jobOrder->updateStatus();
        }
    }

    public function deleting(JobOrder $jobOrder): void
    {
        if ((string) $jobOrder->status !== 'draft' || $jobOrder->proforma_task_id || $jobOrder->payments()->exists()
            || $jobOrder->invoices()->exists() || $jobOrder->materialMovements()->exists()) {
            throw ValidationException::withMessages(['job_order' => 'Only manual drafts without financial or stock history can be deleted. Cancel jobs created from quotations.']);
        }
    }

    private function notifyAssignments(JobOrder $jobOrder, bool $created = false): void
    {
        if ($jobOrder->designer_id && ($created || $jobOrder->wasChanged('designer_id'))) {
            DB::afterCommit(fn () => $jobOrder->designer?->notify(new DesignerAssignedToTask($jobOrder)));
        }
        if ($jobOrder->typist_id && ($created || $jobOrder->wasChanged('typist_id'))) {
            DB::afterCommit(fn () => $jobOrder->typist?->notify(new TypistAssignedToTask($jobOrder)));
        }
    }
}
