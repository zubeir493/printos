<?php

namespace App\Services\Dielines;

interface DielineTemplateContract
{
    public function key(): string;

    public function name(): string;

    public function description(): string;

    /** @return array{l: string, w: string, h: string} */
    public function baseDimensionLabels(): array;

    public function referenceUrl(): ?string;

    public function referenceDrawingUrl(): ?string;

    /**
     * @return array<string, float|int>
     */
    public function defaults(): array;

    /**
     * @return array<int, array{key: string, label: string, default: float|int, min?: float|int, max?: float|int, suffix?: string, integer?: bool}>
     */
    public function advancedFields(): array;

    /**
     * @param  array<string, mixed>  $dimensions
     * @return array<string, array<int, string>>
     */
    public function dimensionRules(array $dimensions): array;

    /**
     * @param  array<string, mixed>  $dimensions
     * @return array<string, mixed>
     */
    public function generate(array $dimensions): array;
}
