<?php

namespace App\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Livewire\ComponentHook;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ExceptionHandlerHook extends ComponentHook
{
    public function exception(\Throwable $e, \Closure $stopPropagation): void
    {
        // Let Livewire / Filament handle validation errors natively —
        // they already show inline field errors.
        if ($e instanceof ValidationException) {
            return;
        }

        // Let HTTP exceptions (403, 404, etc.) propagate so the global
        // handler in bootstrap/app.php can redirect appropriately.
        if ($e instanceof HttpException) {
            return;
        }

        $message = self::humanise($e);

        Notification::make()
            ->title('Something went wrong')
            ->body($message)
            ->danger()
            ->persistent()
            ->send();

        $stopPropagation();
    }

    /**
     * Translate a technical exception into a plain-English message.
     */
    private static function humanise(\Throwable $e): string
    {
        if ($e instanceof QueryException) {
            return self::humaniseQuery($e);
        }

        // Null / type errors usually mean a required value was missing
        if ($e instanceof \TypeError || $e instanceof \ValueError) {
            return 'A required value was missing or in the wrong format. Please check your input and try again.';
        }

        // Division by zero / arithmetic
        if ($e instanceof \DivisionByZeroError || $e instanceof \ArithmeticError) {
            return 'A calculation error occurred. Please check the values you entered.';
        }

        // Generic fallback — never expose the raw exception message to users
        return 'An unexpected error occurred. Please try again, or contact your administrator if the problem persists.';
    }

    /**
     * Translate database errors into plain English.
     */
    private static function humaniseQuery(QueryException $e): string
    {
        $code = $e->getCode();
        $raw = strtolower($e->getMessage());

        // MySQL / MariaDB error codes
        return match (true) {
            // Duplicate entry (unique constraint)
            $code == 23000 && str_contains($raw, 'duplicate') => 'This record already exists. Please check for duplicates before saving.',

            // NOT NULL constraint — a required field was left empty
            $code == 23000 && (str_contains($raw, 'cannot be null') || str_contains($raw, 'null value')) => 'One or more required fields were left empty. Please fill in all required fields and try again.',

            // Foreign key constraint — referenced record doesn't exist or is in use
            $code == 23000 && str_contains($raw, 'foreign key') => 'This record is linked to other data and cannot be saved or deleted in its current state. Make sure all related records exist.',

            // General integrity constraint
            $code == 23000 => 'The data you entered conflicts with existing records. Please review your input and try again.',

            // Deadlock
            $code == 40001 => 'The system was busy processing another request at the same time. Please try again.',

            // Lock wait timeout
            $code == 1205 => 'The system took too long to process your request. Please try again in a moment.',

            // Data too long for column
            $code == 1406 => 'One of the values you entered is too long. Please shorten it and try again.',

            // Table / column not found (schema mismatch — should not happen in production)
            in_array($code, [1054, 1146]) => 'A system configuration error occurred. Please contact your administrator.',

            default => 'A database error occurred while saving your data. Please try again or contact your administrator.',
        };
    }
}
