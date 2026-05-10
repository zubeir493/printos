<?php

namespace App\Models;

use App\Support\PrivateStorage;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TextFile extends Model
{
    use HasFactory;

    protected $fillable = [
        'job_order_id',
        'uploaded_by',
        'filename',
        'original_name',
    ];

    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
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
