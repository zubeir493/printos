<?php

namespace App\Services\Dielines;

interface DielineTemplateContract
{
    public function key(): string;

    public function name(): string;

    public function description(): string;

    /**
     * @return array<string, float|int>
     */
    public function defaults(): array;

    /**
     * @return array<int, array{key: string, label: string, default: float|int, min?: float|int, suffix?: string}>
     */
    public function advancedFields(): array;

    /**
     * @param  array<string, mixed>  $dimensions
     * @return array<string, mixed>
     */
    public function generate(array $dimensions): array;
}
