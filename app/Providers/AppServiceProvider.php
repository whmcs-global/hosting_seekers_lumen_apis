<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PleskX\Api\Client;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(Client::class, function ($app) {
            $client = new Client("51.83.123.186");
            $client->setCredentials("root", "1wR2guc3J@rujrOl");
            return $client;
        });
    }
}