<?php

namespace App\States\JobOrder;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class JobOrderState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()->default(Draft::class)
            ->allowTransition(Draft::class, Design::class)
            ->allowTransition(Draft::class, Production::class)
            ->allowTransition(Design::class, Production::class)
            ->allowTransition(Production::class, Completed::class)
            ->allowTransition(Completed::class, Production::class)
            ->allowTransition([Draft::class, Design::class, Production::class], Cancelled::class);
    }
}
