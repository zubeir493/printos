<?php

namespace App\Services\Dielines;

class DielineGeometryService
{
    public function __construct(private DielineTemplateRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $dimensions
     * @return array<string, mixed>
     */
    public function generate(string $templateKey, array $dimensions): array
    {
        return $this->registry->template($templateKey)->generate($this->normalize($templateKey, $dimensions));
    }

    /**
     * @param  array<string, mixed>  $dimensions
     * @return array<string, float|int>
     */
    public function normalize(string $templateKey, array $dimensions): array
    {
        $defaults = $this->registry->template($templateKey)->defaults();

        $normalized = collect($defaults)
            ->map(fn (float|int $default, string $key): float|int => (float) ($dimensions[$key] ?? $default))
            ->all();

        return $normalized;
    }
}
