<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use RuntimeException;

class BondActionFeedback
{
    public static function fail(Action $action, RuntimeException $exception): void
    {
        $message = $exception->getMessage();
        if ($exception instanceof QueryException) {
            report($exception);
            $message = 'The bond could not be saved. Try again or contact your administrator if the problem persists.';
        }

        Notification::make()->title('Bond could not be saved')->body($message)->danger()->persistent()->send();
        $action->halt();
    }
}
