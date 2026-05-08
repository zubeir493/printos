<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionPlanItem extends Model
{
    protected $fillable = [
        'production_plan_id',
        'production_plan_machine_id',
        'machine_id',
        'job_order_task_id',
        'planned_quantity',
        'planned_plates',
        'planned_rounds',
    ];

    public function productionPlanMachine(): BelongsTo
    {
        return $this->belongsTo(ProductionPlanMachine::class);
    }

    public function jobOrderTask(): BelongsTo
    {
        return $this->belongsTo(JobOrderTask::class);
    }

    public function reportItems(): HasMany
    {
        return $this->hasMany(ProductionReportItem::class);
    }
}
