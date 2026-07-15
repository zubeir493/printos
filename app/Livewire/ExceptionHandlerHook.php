<?php

namespace App\Livewire;

use Filament\Notifications\Notification;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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

        if ($e instanceof HttpException) {
            if ($e->getStatusCode() === 403) {
                Notification::make()
                    ->title('Action not allowed')
                    ->body('You do not have permission to perform this action.')
                    ->warning()
                    ->send();

                $stopPropagation();

                return;
            }

            if ($e->getStatusCode() === 404) {
                Notification::make()
                    ->title('Record not found')
                    ->body('That record no longer exists or is no longer available.')
                    ->warning()
                    ->send();

                $stopPropagation();

                return;
            }
        }

        $message = self::humanise($e);

        if ($message === self::unexpectedErrorMessage()) {
            report($e);
        }

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

        if ($e instanceof AuthorizationException) {
            return 'You do not have permission to perform this action.';
        }

        if ($e instanceof ModelNotFoundException) {
            return 'That record no longer exists or is no longer available.';
        }

        if (str_contains($e->getMessage(), 'would go negative')) {
            return 'There is not enough stock in the selected warehouse for this movement. Please reduce the quantity or choose another warehouse.';
        }

        if (str_contains($e->getMessage(), 'Insufficient stock')) {
            return $e->getMessage();
        }

        // Null / type errors usually mean a required value was missing
        if ($e instanceof \TypeError || $e instanceof \ValueError) {
            return 'A required value was missing or in the wrong format. Please check your input and try again.';
        }

        // Division by zero / arithmetic
        if ($e instanceof \DivisionByZeroError || $e instanceof \ArithmeticError) {
            return 'A calculation error occurred. Please check the values you entered.';
        }

        // Preserve known domain messages while hiding technical exception details.
        if ($message = self::userSafeDomainMessage($e)) {
            return $message;
        }

        return self::unexpectedErrorMessage();
    }

    private static function userSafeDomainMessage(\Throwable $e): ?string
    {
        $message = trim($e->getMessage());
        $safePrefixes = [
            'Approved ',
            'Bank balance ',
            'Calculate ',
            'Cannot ',
            'Could not read ',
            'Credit sales ',
            'Destination bank ',
            'Enter ',
            'Insufficient ',
            'Issued ',
            'No ',
            'Only ',
            'Payroll ',
            'Repayment ',
            'Requested ',
            'Required ',
            'Select ',
            'Source bank ',
            'The selected ',
            'This ',
            'Transfer ',
            'Unable to apply ',
            'Unable to match ',
        ];

        foreach ($safePrefixes as $prefix) {
            if (str_starts_with($message, $prefix)) {
                return $message;
            }
        }

        return null;
    }

    private static function unexpectedErrorMessage(): string
    {
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
