<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class SequentialNumber
{
    /**
     * @param  class-string<Model>  $modelClass
     * @param  callable(string): int  $numberResolver
     */
    public static function next(
        string $lockName,
        string $modelClass,
        string $column,
        string $prefix,
        int $padding,
        ?string $likePattern = null,
        ?callable $numberResolver = null,
    ): string {
        return Cache::lock("sequence:{$lockName}", 10)->block(5, function () use ($modelClass, $column, $prefix, $padding, $likePattern, $numberResolver): string {
            return DB::transaction(function () use ($modelClass, $column, $prefix, $padding, $likePattern, $numberResolver): string {
                $query = $modelClass::query()
                    ->when($likePattern, fn ($query) => $query->where($column, 'like', $likePattern))
                    ->whereNotNull($column)
                    ->lockForUpdate()
                    ->orderByDesc('id');

                $lastNumber = 0;

                foreach ($query->limit(25)->pluck($column) as $value) {
                    $resolved = $numberResolver
                        ? $numberResolver((string) $value)
                        : self::trailingNumber((string) $value);

                    if ($resolved !== null) {
                        $lastNumber = $resolved;
                        break;
                    }
                }

                return $prefix.str_pad((string) ($lastNumber + 1), $padding, '0', STR_PAD_LEFT);
            });
        });
    }

    public static function trailingNumber(string $value): ?int
    {
        if (! preg_match('/(\d+)$/', $value, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
