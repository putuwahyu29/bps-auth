<?php

namespace Bps\Sso;

use Bps\Sso\Middleware\EnsureBpsAuthenticated;
use Bps\Sso\Services\BpsSsoManager;
use Bps\Sso\Services\PegawaiApiClient;
use Bps\Sso\View\Components\SsoButton;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class BpsSsoServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/sso-bps.php', 'sso-bps');

        $this->app->singleton('bps-sso', function ($app) {
            return new BpsSsoManager($app['config']['sso-bps'] ?? []);
        });

        $this->app->alias('bps-sso', BpsSsoManager::class);

        $this->app->singleton(PegawaiApiClient::class, function ($app) {
            return $app->make('bps-sso')->pegawai();
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/sso-bps.php' => config_path('sso-bps.php'),
            ], 'sso-bps-config');

            $this->publishes([
                __DIR__ . '/../resources/views' => resource_path('views/vendor/bps-sso'),
            ], 'sso-bps-views');
        }

        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'bps-sso');
        Blade::component('bps-sso-button', SsoButton::class);

        /** @var Router $router */
        $router = $this->app->make(Router::class);
        $router->aliasMiddleware('bps.sso', EnsureBpsAuthenticated::class);

        if ($this->app['config']->get('sso-bps.routes.enabled', true)) {
            $this->loadRoutesFrom(__DIR__ . '/../routes/web.php');
        }
    }
}
