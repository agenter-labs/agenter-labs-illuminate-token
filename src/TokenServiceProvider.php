<?php

namespace AgenterLab\Token;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use AgenterLab\Uid\Uid;

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
            $tokenManager = new TokenManager(
                $app['config']->get('app.instance_id'),
                $app->make('cache')->driver(
                    $app['config']->get('token.store')
                ),
                $app['hash'],
                $app['config']->get('token')
            );

            $tokenManager->setUid($app->make(Uid::class));
            return $tokenManager;
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
