<?php

namespace App\Filament\Forms\Components;

use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;

class MinimalRepeater extends Repeater
{
    /** @param list<Action> $actions */
    public function inlineAdd(string $label, string $name = 'addRow', array $actions = []): static
    {
        return $this->compact()
            ->addActionLabel($label)
            ->addable(fn (Repeater $component): bool => empty($component->getRawState()))
            ->extraItemActions([
                $this->getAddAction()
                    ->name($name)
                    ->icon('heroicon-o-plus')
                    ->visible(fn (Repeater $component): bool => ! $component->isDisabled())
                    ->iconButton(),
                ...$actions,
            ]);
    }
}
