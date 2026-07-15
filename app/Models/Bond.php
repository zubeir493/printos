<?php

namespace App\Models;

use App\Enums\PaymentTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Bond extends Model
{
    use HasFactory;
    use LogsActivity;

    public const TYPE_BID = 'bid';

    public const TYPE_PERFORMANCE = 'performance';

    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_RECOVERED = 'recovered';

    public const STATUS_FORFEITED = 'forfeited';

    public const STATUS_EXPIRED = 'expired';

    protected $fillable = [
        'type',
        'bid_id',
        'issuing_partner_id',
        'bank_id',
        'cpo_bank_name',
        'amount',
        'issue_date',
        'recovery_date',
        'expiry_date',
        'status',
        'issue_payment_id',
        'recovery_payment_id',
        'reference',
        'notes',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('bond');
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'bid_id' => 'integer',
            'issuing_partner_id' => 'integer',
            'bank_id' => 'integer',
            'amount' => 'decimal:2',
            'issue_date' => 'date',
            'recovery_date' => 'date',
            'expiry_date' => 'date',
            'issue_payment_id' => 'integer',
            'recovery_payment_id' => 'integer',
        ];
    }

    public function bid(): BelongsTo
    {
        return $this->belongsTo(Bid::class);
    }

    public function issuingPartner(): BelongsTo
    {
        return $this->belongsTo(Partner::class, 'issuing_partner_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(Bank::class);
    }

    public function issuePayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'issue_payment_id');
    }

    public function recoveryPayment(): BelongsTo
    {
        return $this->belongsTo(Payment::class, 'recovery_payment_id');
    }

    public function payments(): MorphMany
    {
        return $this->morphMany(Payment::class, 'payable');
    }

    public function issue(string $paymentDate, string $method, ?int $bankId = null, ?string $reference = null, ?string $cpoBankName = null): Payment
    {
        if ($this->issue_payment_id) {
            throw new RuntimeException('This bond has already been issued.');
        }

        $this->guardBankBackedPayment($method, $bankId);

        return DB::transaction(function () use ($paymentDate, $method, $bankId, $reference, $cpoBankName): Payment {
            $payment = Payment::create([
                'partner_id' => $this->issuing_partner_id,
                'bank_id' => $method === 'cpo' ? null : $bankId,
                'payment_date' => $paymentDate,
                'amount' => $this->amount,
                'transaction_type' => $this->issueTransactionType()->value,
                'method' => $method,
                'reference' => $reference ?: $this->defaultReference('issue'),
                'payable_type' => self::class,
                'payable_id' => $this->id,
            ]);

            $this->update([
                'bank_id' => $method === 'cpo' ? null : $bankId,
                'cpo_bank_name' => $method === 'cpo' ? $cpoBankName : null,
                'issue_date' => $paymentDate,
                'issue_payment_id' => $payment->id,
                'status' => self::STATUS_ACTIVE,
            ]);

            return $payment;
        });
    }

    public function recover(string $paymentDate, string $method, ?int $bankId = null, ?string $reference = null, ?string $cpoBankName = null): Payment
    {
        if (! $this->issue_payment_id) {
            throw new RuntimeException('This bond must be issued before it can be recovered.');
        }

        if ($this->recovery_payment_id) {
            throw new RuntimeException('This bond has already been recovered.');
        }

        $this->guardBankBackedPayment($method, $bankId);

        return DB::transaction(function () use ($paymentDate, $method, $bankId, $reference, $cpoBankName): Payment {
            $payment = Payment::create([
                'partner_id' => $this->issuing_partner_id,
                'bank_id' => $method === 'cpo' ? null : $bankId,
                'payment_date' => $paymentDate,
                'amount' => $this->amount,
                'transaction_type' => $this->recoveryTransactionType()->value,
                'method' => $method,
                'reference' => $reference ?: $this->defaultReference('recovery'),
                'payable_type' => self::class,
                'payable_id' => $this->id,
            ]);

            $this->update([
                'bank_id' => $method === 'cpo' ? null : $bankId,
                'cpo_bank_name' => $method === 'cpo' ? ($cpoBankName ?: $this->cpo_bank_name) : $this->cpo_bank_name,
                'recovery_date' => $paymentDate,
                'recovery_payment_id' => $payment->id,
                'status' => self::STATUS_RECOVERED,
            ]);

            return $payment;
        });
    }

    public function markForfeited(): void
    {
        if (! $this->issue_payment_id) {
            throw new RuntimeException('Only issued bonds can be forfeited.');
        }

        if ($this->recovery_payment_id) {
            throw new RuntimeException('Recovered bonds cannot be forfeited.');
        }

        $this->update(['status' => self::STATUS_FORFEITED]);
    }

    public function issueTransactionType(): PaymentTransactionType
    {
        return $this->type === self::TYPE_PERFORMANCE
            ? PaymentTransactionType::PERFORMANCE_BOND_ISSUE
            : PaymentTransactionType::BID_BOND_ISSUE;
    }

    public function recoveryTransactionType(): PaymentTransactionType
    {
        return $this->type === self::TYPE_PERFORMANCE
            ? PaymentTransactionType::PERFORMANCE_BOND_RECOVERY
            : PaymentTransactionType::BID_BOND_RECOVERY;
    }

    public static function typeOptions(): array
    {
        return [
            self::TYPE_BID => 'Bid',
            self::TYPE_PERFORMANCE => 'Performance',
        ];
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_PENDING => 'Pending',
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_RECOVERED => 'Recovered',
            self::STATUS_FORFEITED => 'Forfeited',
            self::STATUS_EXPIRED => 'Expired',
        ];
    }

    private function guardBankBackedPayment(string $method, ?int $bankId): void
    {
        if (in_array($method, ['bank', 'bank_transfer', 'cheque', 'check'], true) && ! $bankId) {
            throw new RuntimeException('Select a bank account for this payment method.');
        }
    }

    private function defaultReference(string $action): string
    {
        $type = self::typeOptions()[$this->type] ?? 'Bond';

        return "{$type} bond {$action} for ".$this->bid->bid_number;
    }
}
