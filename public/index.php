<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

// Catch PHP fatal errors (max execution time, memory exhaustion, etc.) that
// kill the process before Laravel can render a response. The shutdown function
// runs even after a fatal, so we can output the custom error page directly.
register_shutdown_function(function (): void {
    $error = error_get_last();

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error === null || ! in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    // Only intercept if headers haven't been sent and no output has started.
    if (headers_sent() || ob_get_length() > 0) {
        return;
    }

    $isTimeout = str_contains($error['message'], 'Maximum execution time');
    $isMemory  = str_contains($error['message'], 'Allowed memory size');

    if ($isTimeout) {
        $heading = 'The request took too long';
        $message = 'This page took too long to load and was stopped automatically. Try refreshing — if it keeps happening, contact your administrator.';
    } elseif ($isMemory) {
        $heading = 'The server ran out of memory';
        $message = 'This operation used more memory than allowed. Try again with a smaller dataset, or contact your administrator.';
    } else {
        $heading = 'Something went wrong on our end';
        $message = 'An unexpected error occurred. Please try again — if the problem keeps happening, contact your administrator.';
    }

    $logoPath = __DIR__.'/images/logo.svg';
    $logoTag  = file_exists($logoPath)
        ? '<img src="/images/logo.svg" alt="PrintOS" class="h-8" style="filter: brightness(0);" onerror="this.style.display:none">'
        : '';

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');

    // Inline the CSS so this page works even if Vite assets are unavailable.
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Something Went Wrong — PrintOS</title>
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
                background-color: #f9fafb;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem;
            }
            .wrap { width: 100%; max-width: 32rem; }
            .logo { display: flex; justify-content: center; margin-bottom: 2.5rem; }
            .card {
                background: #fef2f2;
                border: 1px solid #fecaca;
                border-radius: 0.75rem;
                padding: 1.5rem;
                display: flex;
                gap: 1rem;
            }
            .icon { flex-shrink: 0; margin-top: 0.125rem; color: #ef4444; }
            .icon svg { width: 1.5rem; height: 1.5rem; }
            .body { flex: 1; min-width: 0; }
            .heading { font-size: 0.875rem; font-weight: 600; color: #991b1b; }
            .msg { margin-top: 0.25rem; font-size: 0.875rem; color: #b91c1c; }
            .actions { margin-top: 1rem; display: flex; gap: 0.75rem; flex-wrap: wrap; }
            .btn-back {
                display: inline-flex; align-items: center; gap: 0.375rem;
                border-radius: 0.5rem; padding: 0.375rem 0.75rem;
                font-size: 0.875rem; font-weight: 600; text-decoration: none;
                background: #fee2e2; color: #991b1b;
                box-shadow: inset 0 0 0 1px #fca5a5;
                transition: background 0.15s;
            }
            .btn-back:hover { background: #fecaca; }
            .btn-home {
                display: inline-flex; align-items: center; gap: 0.375rem;
                border-radius: 0.5rem; padding: 0.375rem 0.75rem;
                font-size: 0.875rem; font-weight: 600; text-decoration: none;
                background: #4f46e5; color: #fff;
                transition: background 0.15s;
            }
            .btn-home:hover { background: #4338ca; }
            .code { margin-top: 1.5rem; text-align: center; font-size: 0.75rem; color: #9ca3af; }
        </style>
    </head>
    <body>
        <div class="wrap">
            <div class="logo">{$logoTag}</div>
            <div class="card">
                <div class="icon">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="body">
                    <p class="heading">{$heading}</p>
                    <p class="msg">{$message}</p>
                    <div class="actions">
                        <a href="javascript:history.back()" class="btn-back">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width:0.875rem;height:0.875rem">
                                <path fill-rule="evenodd" d="M14 8a.75.75 0 0 1-.75.75H4.56l3.22 3.22a.75.75 0 1 1-1.06 1.06l-4.5-4.5a.75.75 0 0 1 0-1.06l4.5-4.5a.75.75 0 0 1 1.06 1.06L4.56 7.25h8.69A.75.75 0 0 1 14 8Z" clip-rule="evenodd" />
                            </svg>
                            Go back
                        </a>
                        <a href="/" class="btn-home">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" style="width:0.875rem;height:0.875rem">
                                <path d="M8.543 2.232a.75.75 0 0 0-1.085 0l-5.25 5.5A.75.75 0 0 0 2.75 9H4v4a1 1 0 0 0 1 1h1.5a.5.5 0 0 0 .5-.5v-3h2v3a.5.5 0 0 0 .5.5H11a1 1 0 0 0 1-1V9h1.25a.75.75 0 0 0 .543-1.268l-5.25-5.5Z" />
                            </svg>
                            Dashboard
                        </a>
                    </div>
                </div>
            </div>
            <p class="code">Error 500</p>
        </div>
    </body>
    </html>
    HTML;
});

// Determine if the application is in maintenance mode...
if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

// Register the Composer autoloader...
require __DIR__.'/../vendor/autoload.php';

// Bootstrap Laravel and handle the request...
/** @var Application $app */
$app = require_once __DIR__.'/../bootstrap/app.php';

$app->handleRequest(Request::capture());
