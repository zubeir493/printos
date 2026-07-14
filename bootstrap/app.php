<?php

use App\Http\Middleware\ForceHttps;
use App\Http\Middleware\RateLimitRequests;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(
            at: env('TRUSTED_PROXIES'),
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
                | Request::HEADER_X_FORWARDED_PREFIX,
        );
        $middleware->alias([
            'rate.requests' => RateLimitRequests::class,
        ]);
        $middleware->append(ForceHttps::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            $isFilamentRequest = $request->is('filament/*') || str_starts_with($request->path(), 'filament');
            $isJsonRequest = $request->expectsJson() || $request->ajax() || $request->hasHeader('X-Livewire') || $request->hasHeader('X-Requested-With');

            // PHP fatal errors surfaced as Error instances (max execution time, memory, etc.)
            if ($e instanceof Error) {
                $message = $e->getMessage() ?? '';

                if (str_contains($message, 'Maximum execution time')) {
                    $body = 'This page took too long to load and was stopped automatically. Try refreshing — if it keeps happening, contact your administrator.';
                } elseif (str_contains($message, 'Allowed memory size')) {
                    $body = 'This operation used more memory than allowed. Try again with a smaller dataset, or contact your administrator.';
                } else {
                    $body = 'An unexpected error occurred. Please try again or contact your administrator if it keeps happening.';
                }

                if ($isFilamentRequest) {
                    Notification::make()
                        ->title('Something went wrong')
                        ->body($body)
                        ->danger()
                        ->send();

                    return $isJsonRequest
                        ? response()->json(['message' => $body], 500)
                        : response()->view('errors.500', [], 500);
                }

                return response()->view('errors.500', [], 500);
            }

            // 403 — redirect authenticated users to their role home; others see the 403 view
            if ($e instanceof HttpException && $e->getStatusCode() === 403) {
                if (Auth::check()) {
                    $redirectPath = Auth::user()->role->getRedirectPath();

                    if (rtrim($request->getPathInfo(), '/') !== rtrim($redirectPath, '/')) {
                        return new RedirectResponse(url($redirectPath));
                    }
                }
            }

            // 404 on Filament routes — redirect with notification instead of a full-page error
            if ($e instanceof NotFoundHttpException && $isFilamentRequest) {
                if (Auth::check()) {
                    $redirectPath = Auth::user()->role->getRedirectPath();
                    Notification::make()
                        ->title('Page not found')
                        ->body('That page doesn\'t exist or may have been moved.')
                        ->warning()
                        ->send();

                    return new RedirectResponse(url($redirectPath));
                }
            }

            // Connection / timeout errors on Filament routes
            if ($isFilamentRequest && (
                $e instanceof ConnectionException ||
                $e instanceof RequestException ||
                str_contains($e->getMessage() ?? '', 'timeout') ||
                str_contains($e->getMessage() ?? '', 'connection')
            )) {
                $body = 'The request took too long to complete. Please try again.';

                Notification::make()
                    ->title('Connection problem')
                    ->body($body)
                    ->danger()
                    ->send();

                return $isJsonRequest
                    ? response()->json(['message' => $body], 500)
                    : response()->view('errors.500', [], 500);
            }

            // Validation errors on Filament routes — Livewire handles inline errors,
            // but this catches any that bubble up unexpectedly
            if ($e instanceof ValidationException && $isFilamentRequest) {
                Notification::make()
                    ->title('Please check your input')
                    ->body('Some fields have errors. Review the form and try again.')
                    ->warning()
                    ->send();
            }

            // All other server errors (5xx) on Filament routes — render a single error response
            if ($isFilamentRequest && ! ($e instanceof ValidationException) && ! ($e instanceof NotFoundHttpException)) {
                $statusCode = $e instanceof HttpException ? $e->getStatusCode() : 500;

                if ($statusCode >= 500) {
                    $body = 'An unexpected error occurred. Please try again or contact your administrator if it keeps happening.';

                    Notification::make()
                        ->title('Something went wrong')
                        ->body($body)
                        ->danger()
                        ->send();

                    return $isJsonRequest
                        ? response()->json(['message' => $body], 500)
                        : response()->view('errors.500', [], 500);
                }
            }
        });
    })->create();
