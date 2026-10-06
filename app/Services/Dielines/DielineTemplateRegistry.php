<?php

namespace App\Services\Dielines;

use App\Models\DielineTemplate;
use App\Services\Dielines\Templates\AutoBottomTuckTopTemplate;
use App\Services\Dielines\Templates\FoodTrayTemplate;
use App\Services\Dielines\Templates\FullOverlapCartonTemplate;
use App\Services\Dielines\Templates\ReverseTuckFlapBoxTemplate;
use App\Services\Dielines\Templates\SleeveBoxTemplate;
use App\Services\Dielines\Templates\SnapLockBottomTemplate;
use App\Services\Dielines\Templates\StraightTuckFlapTemplate;
use Illuminate\Support\Collection;

class DielineTemplateRegistry
{
    /**
     * @return array<string, class-string<DielineTemplateContract>>
     */
    public function fallbackTypes(): array
    {
        return [
            'auto-bottom-tuck-top' => AutoBottomTuckTopTemplate::class,
            'four-corner-food-tray' => FoodTrayTemplate::class,
            'full-overlap-carton' => FullOverlapCartonTemplate::class,
            'open-ended-sleeve' => SleeveBoxTemplate::class,
            'reverse-tuck-flap-box' => ReverseTuckFlapBoxTemplate::class,
            'snap-lock-bottom-tuck-top' => SnapLockBottomTemplate::class,
            'straight-tuck-flap' => StraightTuckFlapTemplate::class,
        ];
    }

    public function template(string $key): DielineTemplateContract
    {
        $class = $this->serviceClasses()[$key] ?? $this->fallbackTypes()['reverse-tuck-flap-box'];

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
