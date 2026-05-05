<?php

namespace App\Providers;

use App\Mail\MailtrapApiTransport;
use Illuminate\Mail\MailManager;
use Illuminate\Support\ServiceProvider;

class MailtrapServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->make(MailManager::class)->extend('mailtrap', function (array $config) {
            return new MailtrapApiTransport(
                apiKey: (string) ($config['api_key'] ?? ''),
                endpoint: (string) ($config['endpoint'] ?? 'https://send.api.mailtrap.io/api/send'),
            );
        });
    }
}
