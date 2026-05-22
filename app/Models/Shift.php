<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'break_minutes',
        'grace_minutes',
        'expected_minutes',
        'is_night_shift',
        'overtime_eligible',
    ];

    protected function casts(): array
    {
        return [
            'break_minutes' => 'integer',
            'grace_minutes' => 'integer',
            'expected_minutes' => 'integer',
            'is_night_shift' => 'boolean',
            'overtime_eligible' => 'boolean',
        ];
    }

    public function attendanceSegments(): HasMany
    {
        return $this->hasMany(AttendanceSegment::class);
    }
}
