<?php

namespace AgenterLab\Token;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class TokenServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {

        $this->app->singleton('token.manager', function ($app) {
            return new TokenManager(
                $app['config']->get('app.instance_id'),
                $app->make('cache')->driver(
                    $app['config']->get('token.store')
                ),
                $app['hash'],
                $app['config']->get('token')
            );
        });

        $this->mergeConfigFrom(__DIR__ . '/../config/token.php', 'token');
    }

    /**
     * Boot the authentication services for the application.
     *
     * @return void
     */
    public function boot()
    {
        
        
    }
}
