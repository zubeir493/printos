<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public const CODE_AP = '2100';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'type',
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
}
