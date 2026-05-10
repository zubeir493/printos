<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionReportItem extends Model
{
    protected $fillable = [
        'production_report_machine_id',
        'production_plan_item_id',
        'date',
        'actual_quantity',
        'plates_used',
        'rounds',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
        ];
    }

    public function productionReportMachine(): BelongsTo
    {
        return $this->belongsTo(ProductionReportMachine::class);
    }

    public function productionPlanItem(): BelongsTo
    {
        return $this->belongsTo(ProductionPlanItem::class);
    }
}
