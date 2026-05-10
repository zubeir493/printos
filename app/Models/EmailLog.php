<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $fillable = [
        'recipient_email',
        'subject',
        'message',
        'artwork_id',
        'sent_by',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function artwork(): BelongsTo
    {
        return $this->belongsTo(Artwork::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }
}
