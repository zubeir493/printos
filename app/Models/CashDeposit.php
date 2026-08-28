<?php

namespace App\Models;

use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class CashDeposit extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STATUS_PENDING = 'pending';

    public const STATUS_POSTED = 'posted';

    public const STATUS_REVERSED = 'reversed';

    public const TYPE_CASH_TRANSFER = 'cash_transfer';

    public const TYPE_OTHER_SOURCES = 'other_sources';

    public const TYPE_OTHER_INCOME = 'other_income';

    protected $fillable = [
        'deposit_number',
        'deposit_type',
        'bank_id',
        'cash_account_id',
        'income_account_id',
        'amount',
        'deposit_date',
        'reference',
        'attachment',
        'notes',
        'status',
        'created_by',
        'posted_by',
        'reversed_by',
        'posted_at',
        'reversed_at',
        'reversal_reason',
    ];

    protected function casts(): array
    {
        return [
            'bank_id' => 'integer',
            'cash_account_id' => 'integer',
            'income_account_id' => 'integer',
            'amount' => 'decimal:2',
            'deposit_date' => 'date',
            'created_by' => 'integer',
            'posted_by' => 'integer',
            'reversed_by' => 'integer',
            'posted_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('cash_deposit');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function cashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'cash_account_id');
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'income_account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function reversedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function journalEntries(): MorphMany
    {
        return $this->morphMany(JournalEntry::class, 'source');
    }

    public static function cashOnHandAccount(): Account
    {
        return Account::getSystemAccount(
            Account::CODE_CASH,
            'Cash on Hand',
            'Asset',
        );
    }

    public static function otherIncomeAccount(): Account
    {
        return Account::getSystemAccount(
            Account::CODE_OTHER_INCOME,
            'Other Income',
            'Revenue',
        );
    }

    public static function cashOnHandBalance(): float
    {
        return (float) JournalItem::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_items.journal_entry_id')
            ->where('journal_items.account_id', self::cashOnHandAccount()->id)
            ->where('journal_entries.status', 'posted')
            ->selectRaw('COALESCE(SUM(journal_items.debit - journal_items.credit), 0) AS balance')
            ->value('balance');
    }

    protected static function booted(): void
    {
        static::creating(function (self $deposit): void {
            $deposit->deposit_number ??= SequentialNumber::next(
                lockName: 'cash_deposits',
                modelClass: self::class,
                column: 'deposit_number',
                prefix: 'CD-',
                padding: 6,
                likePattern: 'CD-%',
            );
            $deposit->deposit_type ??= self::TYPE_CASH_TRANSFER;
            $deposit->status ??= self::STATUS_PENDING;
            $deposit->created_by ??= auth()->id();
        });

        static::saving(function (self $deposit): void {
            if ((float) $deposit->amount <= 0) {
                throw new \InvalidArgumentException('Deposit amount must be positive.');
            }
        });
    }
}
