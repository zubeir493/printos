<?php

namespace App\Services\Dielines;

use App\Models\DielineTemplate;
use App\Services\Dielines\Templates\AutoBottomTuckTopTemplate;
use App\Services\Dielines\Templates\BurgerBoxTemplate;
use App\Services\Dielines\Templates\CarryBagTemplate;
use App\Services\Dielines\Templates\ClamshellBurgerBoxTemplate;
use App\Services\Dielines\Templates\FlipTopBurgerBoxTemplate;
use App\Services\Dielines\Templates\FoodTrayTemplate;
use App\Services\Dielines\Templates\FriesScoopTemplate;
use App\Services\Dielines\Templates\FullOverlapCartonTemplate;
use App\Services\Dielines\Templates\GableBoxTemplate;
use App\Services\Dielines\Templates\HangTabCartonTemplate;
use App\Services\Dielines\Templates\LockTabPizzaBoxTemplate;
use App\Services\Dielines\Templates\OpenFoodTrayTemplate;
use App\Services\Dielines\Templates\PizzaBoxTemplate;
use App\Services\Dielines\Templates\ReverseTuckFlapBoxTemplate;
use App\Services\Dielines\Templates\RolloverPizzaBoxTemplate;
use App\Services\Dielines\Templates\SandwichWedgeTemplate;
use App\Services\Dielines\Templates\SealEndCartonTemplate;
use App\Services\Dielines\Templates\SleeveBoxTemplate;
use App\Services\Dielines\Templates\SnapLockBottomTemplate;
use App\Services\Dielines\Templates\StraightTuckFlapTemplate;
use App\Services\Dielines\Templates\VialInsertTemplate;
use Illuminate\Support\Collection;

class DielineTemplateRegistry
{
    /** @var array<string, class-string<DielineTemplateContract>>|null */
    private ?array $resolvedServiceClasses = null;

    /**
     * @return array<string, class-string<DielineTemplateContract>>
     */
    public function fallbackTypes(): array
    {
        return [
            'auto-bottom-tuck-top' => AutoBottomTuckTopTemplate::class,
            'burger-box' => BurgerBoxTemplate::class,
            'carry-bag' => CarryBagTemplate::class,
            'clamshell-burger-box' => ClamshellBurgerBoxTemplate::class,
            'flip-top-burger-box' => FlipTopBurgerBoxTemplate::class,
            'four-corner-food-tray' => FoodTrayTemplate::class,
            'fries-scoop' => FriesScoopTemplate::class,
            'full-overlap-carton' => FullOverlapCartonTemplate::class,
            'gable-box' => GableBoxTemplate::class,
            'hang-tab-carton' => HangTabCartonTemplate::class,
            'lock-tab-pizza-box' => LockTabPizzaBoxTemplate::class,
            'open-food-tray' => OpenFoodTrayTemplate::class,
            'open-ended-sleeve' => SleeveBoxTemplate::class,
            'pizza-box' => PizzaBoxTemplate::class,
            'reverse-tuck-flap-box' => ReverseTuckFlapBoxTemplate::class,
            'rollover-pizza-box' => RolloverPizzaBoxTemplate::class,
            'sandwich-wedge' => SandwichWedgeTemplate::class,
            'seal-end-carton' => SealEndCartonTemplate::class,
            'snap-lock-bottom-tuck-top' => SnapLockBottomTemplate::class,
            'straight-tuck-flap' => StraightTuckFlapTemplate::class,
            'vial-insert' => VialInsertTemplate::class,
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
            ->sort()
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

    /** @return array<int, array<string, mixed>> */
    public function fieldsForTemplate(string $templateKey): array
    {
        $template = $this->template($templateKey);
        $defaults = $template->defaults();

        return array_map(function (array $field) use ($defaults): array {
            $field['default'] = $defaults[$field['key']];

            return $field;
        }, $template->advancedFields());
    }

    /** @return array<string, mixed> */
    public function fieldForTemplate(string $templateKey, string $key): array
    {
        return collect($this->fieldsForTemplate($templateKey))->firstWhere('key', $key) ?? [];
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
        if ($this->resolvedServiceClasses !== null) {
            return $this->resolvedServiceClasses;
        }

        $templates = DielineTemplate::query()
            ->where('active', true)
            ->pluck('service_class', 'key')
            ->all();

        return $this->resolvedServiceClasses = array_merge($this->fallbackTypes(), $templates);
    }
}
