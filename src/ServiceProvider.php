<?php

namespace Mitoop\Signature;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Contracts\Auth\UserProvider;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

class ServiceProvider extends LaravelServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $configName = 'api-clients';

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__."/config/{$configName}.php" => config_path("{$configName}.php"),
            ]);
        }

        $this->app->singleton(ClientManager::class, function ($app) use ($configName) {
            return new ClientManager($app, $configName, new \GuzzleHttp\Client);
        });

        $this->app->singleton(Signature::class, function ($app) use ($configName) {
            return new Signature($app, $configName);
        });
    }

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        Auth::viaRequest('signature', function (Request $request, UserProvider $provider = null) {
            return (new RequestGuardHandler($this->app))->user($request, $provider);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides()
    {
        return [ClientManager::class, Signature::class];
    }
}
