<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Machine extends Model
{
    use LogsActivity;

    public const array OPERATION_TYPES = [
        'printing' => 'Printing',
        'die_cutting' => 'Die Cutting',
        'folder_gluer' => 'Folder Gluer',
        'folding' => 'Folding',
        'stitching' => 'Stitching',
        'perfect_binding' => 'Perfect Binding',
        'laminating' => 'Laminating',
        'hard_cover_gluing' => 'Hard Cover Gluing',
        'slitting' => 'Slitting',
        'rewinding' => 'Rewinding',
        'cutting' => 'Cutting',
        'packing' => 'Packing',
        'collating' => 'Collating / Gathering',
        'case_making' => 'Case Making',
        'sewing' => 'Book Sewing',
        'kiss_cutting' => 'Kiss Cutting',
        'foiling' => 'Foil Stamping',
        'embossing' => 'Embossing',
        'coating' => 'Coating / Varnishing',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('production');
    }

    protected $fillable = [
        'name',
        'code',
        'baseline_rounds_per_week',
        'operation_type',
        'hourly_cost',
        'production_speed',
        'setup_time_minutes', 'setup_waste', 'speed_unit', 'costing_configured', 'is_active',
        'color_units', 'perfecting_colors_per_side', 'min_sheet_width_mm', 'min_sheet_height_mm',
        'max_sheet_width_mm', 'max_sheet_height_mm', 'max_print_width_mm', 'max_print_height_mm',
        'gripper_margin_mm', 'side_margin_mm', 'trailing_margin_mm', 'min_gsm', 'max_gsm',
        'min_thickness_mm', 'max_thickness_mm', 'run_waste_percent', 'minimum_run_minutes',
    ];

    protected function casts(): array
    {
        return [
            'baseline_rounds_per_week' => 'integer',
            'hourly_cost' => 'decimal:2',
            'production_speed' => 'decimal:2',
            'costing_configured' => 'boolean',
            'is_active' => 'boolean',
            'color_units' => 'integer',
            'perfecting_colors_per_side' => 'integer',
            ...array_fill_keys([
                'setup_time_minutes', 'setup_waste', 'min_sheet_width_mm', 'min_sheet_height_mm',
                'max_sheet_width_mm', 'max_sheet_height_mm', 'max_print_width_mm', 'max_print_height_mm',
                'gripper_margin_mm', 'side_margin_mm', 'trailing_margin_mm', 'min_gsm', 'max_gsm',
                'run_waste_percent', 'minimum_run_minutes',
            ], 'decimal:2'),
            'min_thickness_mm' => 'decimal:4',
            'max_thickness_mm' => 'decimal:4',
        ];
    }

    public function scopeOperation(Builder $query, string $operationType): Builder
    {
        return $query->where('operation_type', $operationType);
    }

    public function productionPlanItems(): HasMany
    {
        return $this->hasMany(ProductionPlanItem::class);
    }
}
