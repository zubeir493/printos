<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AttendanceDeviceSync extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'never_synced', 'fetched_count' => 0, 'pending_count' => 0];

    protected function casts(): array
    {
        return [
            'last_attempt_at' => 'immutable_datetime',
            'last_connected_at' => 'immutable_datetime',
            'last_download_at' => 'immutable_datetime',
            'last_successful_sync_at' => 'immutable_datetime',
        ];
    }
}
