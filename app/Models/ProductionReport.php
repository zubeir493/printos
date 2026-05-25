<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ProductionReport extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('production');
    }

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
