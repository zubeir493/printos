<?php

namespace App\Models;

use Database\Factories\AccountingExportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class AccountingExport extends Model
{
    /** @use HasFactory<AccountingExportFactory> */
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'accounting_integration_id', 'generated_by', 'cutoff_at', 'status', 'file_path',
        'file_name', 'journal_count', 'row_count', 'total_debit', 'total_credit',
        'checksum', 'error_message', 'generated_at',
    ];

    protected function casts(): array
    {
        return [
            'cutoff_at' => 'datetime',
            'generated_at' => 'datetime',
            'total_debit' => 'decimal:2',
            'total_credit' => 'decimal:2',
        ];
    }

    public function integration(): BelongsTo
    {
        return $this->belongsTo(AccountingIntegration::class, 'accounting_integration_id');
    }

    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    public function journalEntries(): BelongsToMany
    {
        return $this->belongsToMany(JournalEntry::class)
            ->withPivot('accounting_integration_id')
            ->withTimestamps();
    }
}
