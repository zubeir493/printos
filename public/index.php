<?php

use Illuminate\Foundation\Application;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

$GLOBALS['printos_request_completed'] = false;

// Catch PHP fatal errors (max execution time, memory exhaustion, etc.) that
// kill the process before Laravel can render a response. The shutdown function
// runs even after a fatal, so we can output the custom error page directly.
register_shutdown_function(function (): void {
    $error = error_get_last();

    if ($GLOBALS['printos_request_completed'] ?? false) {
        return;
    }

    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error === null || ! in_array($error['type'], $fatalTypes, true)) {
        return;
    }

    // Only intercept if headers haven't been sent and no output has started.
    if (headers_sent() || ob_get_length() > 0) {
        return;
    }

    $isTimeout = str_contains($error['message'], 'Maximum execution time');
    $isMemory = str_contains($error['message'], 'Allowed memory size');

    if ($isTimeout) {
        $heading = 'The request took too long';
        $message = 'This page took too long to load and was stopped automatically. Try refreshing - if it keeps happening, contact your administrator.';
    } elseif ($isMemory) {
        $heading = 'The server ran out of memory';
        $message = 'This operation used more memory than allowed. Try again with a smaller dataset, or contact your administrator.';
    } else {
        $heading = 'Something went wrong on our end';
        $message = 'An unexpected error occurred. Please try again - if the problem keeps happening, contact your administrator.';
    }

    http_response_code(500);
    header('Content-Type: text/html; charset=UTF-8');

    // Inline the CSS so this page works even if Vite assets are unavailable.
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Something Went Wrong - PrintOS</title>
        <style>
            *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
            body {
                align-items: center;
                background-color: #ffffff;
                color: #030712;
                display: flex;
                font-family: ui-sans-serif, system-ui, sans-serif;
                justify-content: center;
                min-height: 100vh;
                padding: 3rem 1.5rem;
            }
            .wrap { max-width: 28rem; text-align: center; width: 100%; }
            .code { color: #1e1e1e; font-size: 4rem; font-weight: 700; line-height: 1; }
            .heading { font-size: 1.5rem; font-weight: 600; line-height: 2rem; margin-top: 0.75rem; }
            .msg { color: #6b7280; font-size: 0.875rem; line-height: 1.5rem; margin-top: 0.75rem; }
            .actions { display: flex; justify-content: center; margin-top: 2rem; }
            .btn-home {
                align-items: center;
                background: linear-gradient(180deg, #3E3D3E 0%, #403E40 79.91%, #5E5D5E 100%);
                border-radius: 0.5rem;
                box-shadow: inset 0 -2px 2px 0.5px #000000, inset 0 0 2px 1.5px rgba(255, 255, 255, 0.5);
                color: #ffffff;
                display: inline-flex;
                font-size: 0.8rem;
                font-weight: 600;
                justify-content: center;
                padding: 10px 24px;
                text-decoration: none;
                text-transform: capitalize;
            }
        </style>
    </head>
    <body>
        <div class="wrap">
            <p class="code">500</p>
            <h1 class="heading">{$heading}</h1>
            <p class="msg">{$message}</p>
            <div class="actions">
                <a href="/" class="btn-home">Dashboard</a>
            </div>
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

$GLOBALS['printos_request_completed'] = true;
