<?php

namespace App\Filament\Resources\EmailLogs\Pages;

use App\Filament\Resources\EmailLogs\EmailLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageEmailLogs extends ManageRecords
{
    protected static string $resource = EmailLogResource::class;
}
