<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class JournalEntry extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'date',
        'reference',
        'source_type',
        'source_id',
        'narration',
        'total_debit',
        'total_credit',
        'status',
        'posted_at',
        'voided_at',
        'reversal_of_journal_entry_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'date' => 'date',
            'source_id' => 'integer',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
            'posted_at' => 'datetime',
            'voided_at' => 'datetime',
        ];
    }

    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class);
    }
}
