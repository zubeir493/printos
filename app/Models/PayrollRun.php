<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollRun extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'period_start',
        'period_end',
        'pay_date',
        'status',
        'created_by',
        'posted_at',
        'paid_at',
        'journal_entry_id',
    ];

    protected $attributes = [
        'status' => 'draft',
    ];

    protected function casts(): array
    {
        return [
            'period_start' => 'date',
            'period_end' => 'date',
            'pay_date' => 'date',
            'posted_at' => 'datetime',
            'paid_at' => 'datetime',
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(PayrollRunEmployee::class);
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }
}
