<?php

namespace App\Models;

use Database\Factories\DielineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Dieline extends Model
{
    /** @use HasFactory<DielineFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'template_key',
        'job_order_task_id',
        'created_by',
        'dimensions',
        'geometry',
    ];

    protected function casts(): array
    {
        return [
            'dimensions' => 'array',
            'geometry' => 'array',
        ];
    }

    public function jobOrderTask(): BelongsTo
    {
        return $this->belongsTo(JobOrderTask::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(DielineTemplate::class, 'template_key', 'key');
    }
}
