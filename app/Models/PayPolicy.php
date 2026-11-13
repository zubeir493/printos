<?php

namespace App\Models;

use Database\Factories\PayPolicyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PayPolicy extends Model
{
    /** @use HasFactory<PayPolicyFactory> */
    use HasFactory;

    protected $guarded = ['id'];

    protected $attributes = ['version' => 1, 'monthly_day_divisor' => 30, 'standard_daily_minutes' => 480, 'day_overtime_multiplier' => 1.5, 'night_overtime_multiplier' => 1.75, 'rest_overtime_multiplier' => 2, 'holiday_overtime_multiplier' => 2.5, 'night_start' => '22:00:00', 'night_end' => '06:00:00', 'deduct_lateness' => false, 'prorate_allowance' => false, 'overtime_increment_minutes' => 1, 'maximum_daily_overtime_minutes' => 240, 'maximum_weekly_overtime_minutes' => 720, 'is_active' => true];

    protected function casts(): array
    {
        return ['deduct_lateness' => 'boolean', 'prorate_allowance' => 'boolean', 'is_active' => 'boolean', 'day_overtime_multiplier' => 'decimal:4', 'night_overtime_multiplier' => 'decimal:4', 'rest_overtime_multiplier' => 'decimal:4', 'holiday_overtime_multiplier' => 'decimal:4'];
    }

    protected static function booted(): void
    {
        static::saving(function (self $policy): void {
            Validator::make($policy->attributesToArray(), [
                'name' => ['required', 'string', 'max:255'], 'code' => ['required', 'string', 'max:255'],
                'version' => ['required', 'integer', 'min:1', Rule::unique('pay_policies')->where('code', $policy->code)->ignore($policy->id)], 'monthly_day_divisor' => ['required', 'integer', 'between:1,31'],
                'standard_daily_minutes' => ['required', 'integer', 'between:1,1440'],
                'night_start' => ['required', 'date_format:H:i:s'], 'night_end' => ['required', 'date_format:H:i:s', 'different:night_start'],
                'day_overtime_multiplier' => ['required', 'numeric', 'min:1'], 'night_overtime_multiplier' => ['required', 'numeric', 'min:1'],
                'rest_overtime_multiplier' => ['required', 'numeric', 'min:1'], 'holiday_overtime_multiplier' => ['required', 'numeric', 'min:1'],
                'overtime_increment_minutes' => ['required', 'integer', 'between:1,60'],
                'maximum_daily_overtime_minutes' => ['required', 'integer', 'between:1,1440'], 'maximum_weekly_overtime_minutes' => ['required', 'integer', 'between:1,10080'],
            ])->validate();
            if ($policy->exists && collect(array_keys($policy->getDirty()))->diff(['is_active', 'updated_at'])->isNotEmpty() && $policy->assignments()->exists()) {
                throw ValidationException::withMessages(['name' => 'This policy is assigned. Duplicate it as a new version to change its rules.']);
            }
        });
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeWorkAssignment::class);
    }

    public static function forDate(string $date): ?self
    {
        return self::where('is_active', true)->where(fn ($query) => $query->whereNull('effective_from')->orWhereDate('effective_from', '<=', $date))->orderByDesc('effective_from')->latest('id')->first();
    }

    public function hourlyRate(float $salary): float
    {
        return $salary / $this->monthly_day_divisor / ($this->standard_daily_minutes / 60);
    }

    public function multiplier(string $category): float
    {
        return (float) $this->getAttribute($category.'_overtime_multiplier');
    }
}
