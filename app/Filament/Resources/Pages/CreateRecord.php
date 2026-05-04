<?php

namespace App\Filament\Resources\Pages;

use Filament\Resources\Pages\CreateRecord as BaseCreateRecord;

abstract class CreateRecord extends BaseCreateRecord
{
    protected static bool $canCreateAnother = false;
}
