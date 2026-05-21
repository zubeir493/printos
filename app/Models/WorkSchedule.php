<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkSchedule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'is_default',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
        ];
    }

    public function days(): HasMany
    {
        return $this->hasMany(WorkScheduleDay::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeScheduleAssignment::class);
    }
}
