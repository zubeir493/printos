<?php

namespace App\Models;

use App\Support\PrivateStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Artwork extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_order_task_id',
        'filename',
        'is_approved',
        'uploaded_by',
    ];

    protected $casts = [
        'is_approved' => 'boolean',
    ];

    public function jobOrderTask(): BelongsTo
    {
        return $this->belongsTo(JobOrderTask::class);
    }

    public function jobOrder(): \Illuminate\Database\Eloquent\Relations\HasOneThrough
    {
        return $this->hasOneThrough(
            JobOrder::class,
            JobOrderTask::class,
            'id', // Foreign key on job_order_tasks table
            'id', // Foreign key on job_orders table
            'job_order_task_id', // Local key on artworks table
            'job_order_id' // Local key on job_order_tasks table
        );
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
