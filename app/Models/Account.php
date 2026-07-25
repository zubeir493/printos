<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Account extends Model
{
    use HasFactory;
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('accounting');
    }

    public const CODE_CASH = '1010';

    public const CODE_AR = '1200';

    public const CODE_AP = '2000';

    public const CODE_BID_BONDS_RECEIVABLE = '1240';

    public const CODE_PERFORMANCE_BONDS_RECEIVABLE = '1250';

    public const CODE_WITHHOLDING_RECEIVABLE = '1260';

    public const CODE_WITHHOLDING_PAYABLE = '2180';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'type',
        'default_tracking_type',
    ];

    public static function getSystemAccount(string $code, string $defaultName, string $type = 'Asset')
    {
        return self::firstOrCreate(
            ['code' => $code],
            [
                'name' => $defaultName,
                'type' => $type,
            ]
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
        ];
    }

    public function journalItems(): HasMany
    {
        return $this->hasMany(JournalItem::class);
    }

    public function accountingMappings(): HasMany
    {
        return $this->hasMany(AccountingAccountMapping::class);
    }

    public function peachtreeMapping(): HasOne
    {
        return $this->hasOne(AccountingAccountMapping::class)
            ->whereHas('integration', fn ($query) => $query
                ->where('provider', AccountingIntegration::PROVIDER_PEACHTREE_DESKTOP));
    }
}
