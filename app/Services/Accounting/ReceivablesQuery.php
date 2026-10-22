<?php

namespace App\Services\Accounting;

use App\Models\JobOrder;
use App\Models\Payment;
use App\Models\SalesOrder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

class ReceivablesQuery
{
    public function documents(?string $asOfDate = null): Builder
    {
        $asOfDate ??= today()->toDateString();
        $age = match (DB::getDriverName()) {
            'sqlite' => 'CAST(julianday(?) - julianday(order_date) AS INTEGER)',
            'pgsql' => '(CAST(? AS date) - CAST(order_date AS date))',
            default => 'DATEDIFF(?, order_date)',
        };
        $sales = $this->source(SalesOrder::class, 'sales_orders', 'order_number', 'order_date', $asOfDate)
            ->whereIn('sales_orders.status', [SalesOrder::STATUS_DEPOSIT_RECEIVED, SalesOrder::STATUS_SUBMITTED, SalesOrder::STATUS_COMPLETED]);
        $jobs = $this->source(JobOrder::class, 'job_orders', 'job_order_number', 'submission_date', $asOfDate)
            ->where('job_orders.production_mode', 'make_to_order')
            ->whereIn('job_orders.status', ['design', 'production', 'completed']);

        return SalesOrder::query()->fromSub($sales->unionAll($jobs), 'sales_orders')
            ->select('sales_orders.*')
            ->selectRaw($age.' as age_days', [$asOfDate])
            ->selectRaw("CASE WHEN {$age} <= 30 THEN 'Current' WHEN {$age} <= 60 THEN '31-60' WHEN {$age} <= 90 THEN '61-90' ELSE '90+' END as bucket", [$asOfDate, $asOfDate, $asOfDate])
            ->with('partner')->where('balance', '>', 0)->orderByDesc('order_date');
    }

    public function total(?string $asOfDate = null): float
    {
        return (float) $this->documents($asOfDate)->toBase()->sum('balance');
    }

    public function customerBalances(?string $asOfDate = null): QueryBuilder
    {
        return DB::query()->fromSub($this->documents($asOfDate)->reorder()->toBase(), 'receivables')
            ->select('partner_id')->selectRaw('SUM(balance) as total_balance')->groupBy('partner_id');
    }

    /** @param class-string<JobOrder|SalesOrder> $model */
    private function source(string $model, string $table, string $number, string $date, string $asOfDate): QueryBuilder
    {
        $payments = Payment::query()->select('payable_id')->selectRaw('SUM(amount) as paid_amount')
            ->where('payable_type', $model)->where('direction', 'inbound')->whereNull('voided_at')
            ->whereDate('payment_date', '<=', $asOfDate)->groupBy('payable_id');
        $query = $model::query()->leftJoinSub($payments, 'received_payments', fn ($join) => $join->on('received_payments.payable_id', '=', $table.'.id'))
            ->select([$table.'.partner_id', $table.'.due_date', $table.'.subtotal', $table.'.tax_amount', $table.'.total', $table.'.status', $table.'.created_at', $table.'.updated_at'])
            ->selectRaw("({$table}.id * 2 + ".($model === JobOrder::class ? 1 : 0).") as id, {$table}.id as source_id")
            ->selectRaw("{$table}.{$number} as order_number, {$table}.{$date} as order_date")
            ->selectRaw("({$table}.total - COALESCE(received_payments.paid_amount, 0)) as balance")
            ->selectRaw('COALESCE(received_payments.paid_amount, 0) as payments_sum_amount')
            ->selectRaw('? as document_type', [$model === JobOrder::class ? 'job_order' : 'sales_order'])
            ->whereDate($table.'.'.$date, '<=', $asOfDate);

        return $query->toBase();
    }
}
