<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\RateLimitRequests::class);
        $middleware->append(\App\Http\Middleware\ForceHttps::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
            if ($e instanceof \Symfony\Component\HttpKernel\Exception\HttpException && $e->getStatusCode() === 403) {
                if (\Illuminate\Support\Facades\Auth::check()) {
                    $redirectPath = \Illuminate\Support\Facades\Auth::user()->role->getRedirectPath();
                    
                    // Avoid infinite redirect if the user still doesn't have access to their home panel
                    if (rtrim($request->getPathInfo(), '/') !== rtrim($redirectPath, '/')) {
                        return redirect($redirectPath);
                    }
                }
            }
        });
    })->create();
