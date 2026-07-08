<?php

namespace App\Models;

use App\Enums\ExpenseTrackingType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ExpenseTrackingItem extends Model
{
    use HasFactory;
    use LogsActivity;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'type',
        'code',
        'name',
        'status',
        'notes',
    ];

    protected $attributes = [
        'status' => self::STATUS_ACTIVE,
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logAll()
            ->logOnlyDirty()
            ->useLogName('finance');
    }

    protected function casts(): array
    {
        return [
            'id' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    public function getDisplayNameAttribute(): string
    {
        return filled($this->code)
            ? "{$this->code} - {$this->name}"
            : $this->name;
    }

    /**
     * @return array<string, string>
     */
    public static function statusOptions(): array
    {
        return [
            self::STATUS_ACTIVE => 'Active',
            self::STATUS_INACTIVE => 'Inactive',
        ];
    }

    public function typeLabel(): string
    {
        return ExpenseTrackingType::tryFrom($this->type)?->label() ?? str($this->type)->headline()->toString();
    }
}
