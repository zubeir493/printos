<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionPlan extends Model
{
    protected $fillable = ['week_start', 'week_end', 'status'];

    protected function casts(): array
    {
        return [
            'week_start' => 'date',
            'week_end' => 'date',
        ];
    }

    public function machines(): HasMany
    {
        return $this->hasMany(ProductionPlanMachine::class);
    }

    public function reports(): HasMany
    {
        return $this->hasMany(ProductionReport::class);
    }
}
