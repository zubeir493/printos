<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class ProductionReport extends Model
{
    protected $fillable = ['production_plan_id', 'status'];

    public function productionPlan(): BelongsTo
    {
        return $this->belongsTo(ProductionPlan::class);
    }

    public function machines(): HasMany
    {
        return $this->hasMany(ProductionReportMachine::class);
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(
            ProductionReportItem::class,
            ProductionReportMachine::class,
        );
    }
}
