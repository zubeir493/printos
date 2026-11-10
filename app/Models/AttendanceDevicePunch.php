<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDevicePunch extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'punched_at' => 'immutable_datetime',
            'processed_at' => 'immutable_datetime',
            'raw_data' => 'array',
            'direction' => 'integer',
        ];
    }
}
