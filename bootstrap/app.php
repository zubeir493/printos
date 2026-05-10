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
        $middleware->append(RateLimitRequests::class);
        $middleware->append(ForceHttps::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $e, Request $request) {
            // Handle 403 errors
            if ($e instanceof HttpException && $e->getStatusCode() === 403) {
                if (Auth::check()) {
                    $redirectPath = Auth::user()->role->getRedirectPath();

                    // Avoid infinite redirect if the user still doesn't have access to their home panel
                    if (rtrim($request->getPathInfo(), '/') !== rtrim($redirectPath, '/')) {
                        return new RedirectResponse(url($redirectPath));
                    }
                }
            }

            // Handle 404 errors in Filament context
            if ($e instanceof NotFoundHttpException) {
                if ($request->is('filament/*') || str_starts_with($request->path(), 'filament')) {
                    if (Auth::check()) {
                        // For Filament 404s, redirect to dashboard with notification
                        $redirectPath = Auth::user()->role->getRedirectPath();
                        Notification::make()
                            ->title('Page Not Found')
                            ->body('The page you are looking for does not exist or has been moved.')
                            ->warning()
                            ->send();

                        return new RedirectResponse(url($redirectPath));
                    }
                }
            }

            // Handle timeout and connection errors
            if ($e instanceof ConnectionException ||
                $e instanceof RequestException ||
                str_contains($e->getMessage() ?? '', 'timeout') ||
                str_contains($e->getMessage() ?? '', 'connection')) {
                if ($request->is('filament/*') || str_starts_with($request->path(), 'filament')) {
                    Notification::make()
                        ->title('Connection Timeout')
                        ->body('The request took too long to complete. Please try again.')
                        ->danger()
                        ->send();

                    return new RedirectResponse($request->headers->get('referer') ?? url('/'));
                }
            }

            // Handle validation errors with better UX in Filament
            if ($e instanceof ValidationException) {
                if ($request->is('filament/*') || str_starts_with($request->path(), 'filament')) {
                    Notification::make()
                        ->title('Validation Error')
                        ->body('Please check your input and try again.')
                        ->warning()
                        ->send();
                }
            }

            // Handle server errors (500) in Filament context
            if ($e instanceof HttpException && $e->getStatusCode() >= 500) {
                if ($request->is('filament/*') || str_starts_with($request->path(), 'filament')) {
                    Notification::make()
                        ->title('Server Error')
                        ->body('Something went wrong on our end. Please try again or contact support if the problem persists.')
                        ->danger()
                        ->send();

                    return new RedirectResponse($request->headers->get('referer') ?? url('/'));
                }
            }
        });
    })->create();
