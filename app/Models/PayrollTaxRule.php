<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayrollTaxRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'jurisdiction',
        'effective_from',
        'effective_until',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_until' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function brackets(): HasMany
    {
        return $this->hasMany(PayrollTaxBracket::class);
    }

    public function scopeEffectiveOn(Builder $query, string $date): Builder
    {
        return $query
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date)
            ->where(function (Builder $query) use ($date): void {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $date);
            });
    }
}
