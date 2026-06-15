<?php

namespace App\Models;

use App\Support\SequentialNumber;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Bid extends Model
{
    use HasFactory;
    use LogsActivity;
    use SoftDeletes;

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_AWARDED = 'awarded';

    public const STATUS_BOND_SENT = 'bond_sent';

    public const STATUS_BOND_RECOVERED = 'bond_recovered';

    public const STATUS_LOST = 'lost';

    protected $fillable = [
        'bid_number',
        'title',
        'tender_reference',
        'partner_id',
        'status',
        'submission_date',
        'deadline_date',
        'estimated_value',
        'bid_bond_amount',
        'bid_files',
        'notes',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('bid');
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'partner_id' => 'integer',
            'submission_date' => 'date',
            'deadline_date' => 'date',
            'estimated_value' => 'decimal:2',
            'bid_bond_amount' => 'decimal:2',
            'bid_files' => 'array',
        ];
    }

    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }

    public function bidBonds(): HasMany
    {
        return $this->hasMany(Bond::class)->where('type', Bond::TYPE_BID);
    }

    public function bidBond(): HasOne
    {
        return $this->hasOne(Bond::class)->where('type', Bond::TYPE_BID)->latestOfMany();
    }

    public function performanceBonds(): HasMany
    {
        return $this->hasMany(Bond::class)->where('type', Bond::TYPE_PERFORMANCE);
    }

    public function performanceBond(): HasOne
    {
        return $this->hasOne(Bond::class)->where('type', Bond::TYPE_PERFORMANCE)->latestOfMany();
    }

    protected static function booted(): void
    {
        static::creating(function (self $bid): void {
            if (! empty($bid->bid_number)) {
                return;
            }

            $bid->bid_number = SequentialNumber::next(
                lockName: 'bids',
                modelClass: self::class,
                column: 'bid_number',
                prefix: 'BID-',
                padding: 6,
                likePattern: 'BID-%',
            );
        });

        static::saving(function (self $bid): void {
            if (
                $bid->isDirty('status') &&
                $bid->status === self::STATUS_SUBMITTED &&
                blank($bid->submission_date)
            ) {
                $bid->submission_date = now()->toDateString();
            }
        });
    }

    public static function statusOptions(): array
    {
        return [
            self::STATUS_DRAFT => 'Draft',
            self::STATUS_SUBMITTED => 'Submitted',
            self::STATUS_AWARDED => 'Awarded',
            self::STATUS_BOND_SENT => 'Bond Sent',
            self::STATUS_BOND_RECOVERED => 'Bond Recovered',
            self::STATUS_LOST => 'Lost',
        ];
    }

    public function markSubmitted(): void
    {
        $this->update(['status' => self::STATUS_SUBMITTED]);
    }

    public function markAwarded(): void
    {
        $this->update(['status' => self::STATUS_AWARDED]);
    }

    public function markBondSent(): void
    {
        $this->update(['status' => self::STATUS_BOND_SENT]);
    }

    public function markBondRecovered(): void
    {
        $this->update(['status' => self::STATUS_BOND_RECOVERED]);
    }

    public function markLost(): void
    {
        $this->update(['status' => self::STATUS_LOST]);
    }

    public function currentBidBond(): ?Bond
    {
        return $this->bidBond()->first();
    }

    public function currentPerformanceBond(): ?Bond
    {
        return $this->performanceBond()->first();
    }

    public function activePerformanceBond(): ?Bond
    {
        return $this->performanceBonds()
            ->whereNotNull('issue_payment_id')
            ->whereNull('recovery_payment_id')
            ->latest('id')
            ->first();
    }

    public function prepareBidBond(): Bond
    {
        if (blank($this->bid_bond_amount) || (float) $this->bid_bond_amount <= 0) {
            throw new \RuntimeException('Enter a bid bond amount before sending the bond.');
        }

        $bond = $this->currentBidBond();

        if ($bond?->issue_payment_id) {
            throw new \RuntimeException('This bid bond has already been sent.');
        }

        if (! $bond) {
            return $this->bidBonds()->create([
                'type' => Bond::TYPE_BID,
                'issuing_partner_id' => $this->partner_id,
                'amount' => $this->bid_bond_amount,
                'status' => Bond::STATUS_PENDING,
            ]);
        }

        $bond->update([
            'issuing_partner_id' => $this->partner_id,
            'amount' => $this->bid_bond_amount,
        ]);

        return $bond;
    }

    public function preparePerformanceBond(float $amount, ?string $reference = null, ?string $notes = null): Bond
    {
        if ($this->status !== self::STATUS_AWARDED) {
            throw new \RuntimeException('Performance bonds can only be sent after the bid is awarded.');
        }

        if ($amount <= 0) {
            throw new \RuntimeException('Enter a performance bond amount before sending the bond.');
        }

        $bond = $this->activePerformanceBond();

        if ($bond) {
            throw new \RuntimeException('An active performance bond already exists for this bid.');
        }

        return $this->performanceBonds()->create([
            'type' => Bond::TYPE_PERFORMANCE,
            'issuing_partner_id' => $this->partner_id,
            'amount' => $amount,
            'status' => Bond::STATUS_PENDING,
            'reference' => $reference,
            'notes' => $notes,
        ]);
    }
}
