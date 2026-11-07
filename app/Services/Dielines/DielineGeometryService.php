<?php

namespace App\Services\Dielines;

use Illuminate\Support\Facades\Validator;

class DielineGeometryService
{
    public function __construct(private DielineTemplateRegistry $registry) {}

    /**
     * @param  array<string, mixed>  $dimensions
     * @return array<string, mixed>
     */
    public function generate(string $templateKey, array $dimensions): array
    {
        $template = $this->registry->template($templateKey);
        $values = array_replace($template->defaults(), array_intersect_key($dimensions, $template->defaults()));
        $rules = ['l' => ['required', 'numeric', 'min:1'], 'w' => ['required', 'numeric', 'min:1'], 'h' => ['required', 'numeric', 'min:1']];

        foreach ($template->advancedFields() as $field) {
            $rules[$field['key']] = ['required', 'numeric', 'min:'.($field['min'] ?? 0)];

            if (isset($field['max'])) {
                $rules[$field['key']][] = 'max:'.$field['max'];
            }

            if ($field['integer'] ?? false) {
                $rules[$field['key']][] = 'integer';
            }
        }

        Validator::make($values, $rules)->validate();

        foreach ($template->dimensionRules($values) as $key => $constraints) {
            $rules[$key] = [...($rules[$key] ?? []), ...$constraints];
        }

        Validator::make($values, $rules)->validate();

        return $template->generate($this->normalize($templateKey, $dimensions));
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
