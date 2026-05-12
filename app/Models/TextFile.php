<?php

namespace App\Models;

use App\Support\PrivateStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

class TextFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_order_task_id',
        'uploaded_by',
        'filename',
        'original_name',
    ];

    public function jobOrderTask(): BelongsTo
    {
        return $this->belongsTo(JobOrderTask::class);
    }

    public function jobOrder(): HasOneThrough
    {
        return $this->hasOneThrough(
            JobOrder::class,
            JobOrderTask::class,
            'id',             // Foreign key on job_order_tasks table
            'id',             // Foreign key on job_orders table
            'job_order_task_id', // Local key on text_files table
            'job_order_id'    // Local key on job_order_tasks table
        );
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function getDownloadUrlAttribute(): ?string
    {
        return PrivateStorage::downloadUrl($this->filename, now()->addMinutes(60));
    }
}
