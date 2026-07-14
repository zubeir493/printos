<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class JournalEntry extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['date', 'reference', 'narration', 'total_debit', 'total_credit', 'status', 'posted_at', 'voided_at'])
            ->logOnlyDirty()
            ->useLogName('journal_entry');
    }

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
        'attachment',
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

    public function accountingExports(): BelongsToMany
    {
        return $this->belongsToMany(AccountingExport::class)
            ->withPivot('accounting_integration_id')
            ->withTimestamps();
    }
}
