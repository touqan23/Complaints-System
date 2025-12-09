<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use GuzzleHttp\Client;

class HttpClientServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Create ONE shared instance of Guzzle Client (Singleton)
        $this->app->singleton(Client::class, function ($app) {
            return new Client();
        });
    }

    public function boot()
    {
        //
    }
}
