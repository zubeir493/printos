<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
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
        return DB::transaction(function () use ($lockName, $modelClass, $column, $prefix, $padding, $likePattern, $numberResolver): string {
            DB::table('document_sequences')->insertOrIgnore([
                'name' => $lockName, 'last_number' => 0, 'initialized' => false,
            ]);
            $sequence = DB::table('document_sequences')->where('name', $lockName)->lockForUpdate()->firstOrFail();
            $lastNumber = (int) $sequence->last_number;

            if (! $sequence->initialized) {
                $query = DB::table((new $modelClass)->getTable())
                    ->when($likePattern, fn ($query) => $query->where($column, 'like', $likePattern))
                    ->whereNotNull($column);

                foreach ($query->select($column)->cursor() as $record) {
                    $value = (string) $record->{$column};
                    $resolved = $numberResolver ? $numberResolver($value) : self::trailingNumber($value);
                    $lastNumber = max($lastNumber, $resolved ?? 0);
                }
            }

            $nextNumber = $lastNumber + 1;
            DB::table('document_sequences')->where('name', $lockName)->update([
                'last_number' => $nextNumber, 'initialized' => true,
            ]);

            return $prefix.str_pad((string) $nextNumber, $padding, '0', STR_PAD_LEFT);
        }, 3);
    }

    public static function trailingNumber(string $value): ?int
    {
        if (! preg_match('/(\d+)$/', $value, $matches)) {
            return null;
        }

        return (int) $matches[1];
    }
}
