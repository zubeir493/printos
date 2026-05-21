<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Holiday extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'date',
        'type',
        'is_paid',
        'counts_as_holiday_overtime',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'is_paid' => 'boolean',
            'counts_as_holiday_overtime' => 'boolean',
        ];
    }
}
