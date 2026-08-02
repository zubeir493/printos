<?php

namespace App\Services\Dielines;

use App\Models\DielineTemplate;
use App\Services\Dielines\Templates\Fefco0210Template;
use App\Services\Dielines\Templates\Fefco0427Template;
use App\Services\Dielines\Templates\ReverseTuckFlapBoxTemplate;
use App\Services\Dielines\Templates\RoundedTuckCartonTemplate;
use Illuminate\Support\Collection;

class DielineTemplateRegistry
{
    /**
     * @return array<string, class-string<DielineTemplateContract>>
     */
    public function fallbackTypes(): array
    {
        return [
            'fefco-0210' => Fefco0210Template::class,
            'fefco-0427' => Fefco0427Template::class,
            'rounded-tuck-carton' => RoundedTuckCartonTemplate::class,
            'reverse-tuck-flap-box' => ReverseTuckFlapBoxTemplate::class,
        ];
    }

    public function template(string $key): DielineTemplateContract
    {
        $class = $this->serviceClasses()[$key] ?? $this->fallbackTypes()['fefco-0210'];

        return app($class);
    }

    /**
     * @return array<string, string>
     */
    public function options(): array
    {
        return collect($this->serviceClasses())
            ->map(fn (string $class): string => app($class)->name())
            ->all();
    }

    /**
     * @return array<string, array<string, float|int>>
     */
    public function defaultsByTemplate(): array
    {
        return collect($this->serviceClasses())
            ->map(fn (string $class): array => app($class)->defaults())
            ->all();
    }

    /**
     * @return array<int, array{key: string, label: string, default: float|int, min?: float|int, suffix?: string, templates: array<int, string>}>
     */
    public function advancedFields(): array
    {
        return collect($this->serviceClasses())
            ->flatMap(function (string $class, string $templateKey): array {
                return collect(app($class)->advancedFields())
                    ->map(function (array $field) use ($templateKey): array {
                        $field['templates'] = [$templateKey];

                        return $field;
                    })
                    ->all();
            })
            ->groupBy('key')
            ->map(function (Collection $fields): array {
                $first = $fields->first();
                $first['templates'] = $fields->pluck('templates')->flatten()->unique()->values()->all();

                return $first;
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, class-string<DielineTemplateContract>>
     */
    private function serviceClasses(): array
    {
        $templates = DielineTemplate::query()
            ->where('active', true)
            ->pluck('service_class', 'key')
            ->all();

        return array_merge($this->fallbackTypes(), $templates);
    }
}
