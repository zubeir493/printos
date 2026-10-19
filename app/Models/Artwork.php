<?php

namespace App\Models;

use App\Support\PrivateStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Artwork extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['job_order_id', 'filename', 'is_approved', 'uploaded_by'])
            ->logOnlyDirty()
            ->useLogName('artwork');
    }

    protected $fillable = [
        'job_order_id',
        'filename',
        'deliverable',
        'is_approved',
        'uploaded_by',
    ];

    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
        ];
    }

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getUrlAttribute(): ?string
    {
        return PrivateStorage::url($this->filename);
    }
}
