<?php

namespace App\States\JobOrder;

use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class JobOrderState extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Draft::class)
            ->allowTransition(Draft::class, Active::class)
            ->allowTransition(Active::class, Completed::class)
            ->allowTransition(Active::class, Cancelled::class)
            ->allowTransition(Draft::class, Cancelled::class);
    }
}
