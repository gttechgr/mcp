<?php

declare(strict_types=1);

namespace Gttechgr\Mcp;

use Illuminate\Support\ServiceProvider;
use Laravel\Mcp\Client;
use Laravel\Mcp\Facades\Mcp;

class GttechServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(dirname(__DIR__).'/config/gttech.php', 'gttech');
        $this->app->singleton(HostSession::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                dirname(__DIR__).'/config/gttech.php' => config_path('gttech.php'),
            ], 'gttech-config');
        }

        $token = config('gttech.token');
        $url = config('gttech.url');

        if (! is_string($token) || $token === '' || ! is_string($url) || $url === '') {
            return;
        }

        $timeout = config('gttech.timeout');

        Mcp::registerClient('gttech', function () use ($url, $token, $timeout): Client {
            $client = Client::web($url)
                ->withToken($token)
                ->onlyTools([
                    'seo-audit',
                    'website-health',
                    'page-experience',
                    'speed-revenue',
                ]);

            if (is_numeric($timeout)) {
                $client->withTimeout((float) $timeout);
            }

            return $client;
        });
    }
}
