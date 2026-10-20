<?php

use Bps\Sso\Controllers\BpsSsoController;
use Illuminate\Support\Facades\Route;

$prefix = config('sso-bps.routes.prefix', 'sso');
$middleware = config('sso-bps.routes.middleware', ['web']);
$names = config('sso-bps.routes.names', [
    'login' => 'bps-sso.login',
    'callback' => 'bps-sso.callback',
    'logout' => 'bps-sso.logout',
    'driver_login' => 'bps-sso.driver.login',
    'driver_callback' => 'bps-sso.driver.callback',
    'driver_logout' => 'bps-sso.driver.logout',
]);

Route::prefix($prefix)
    ->middleware($middleware)
    ->group(function () use ($names) {
        Route::get('login', [BpsSsoController::class, 'login'])
            ->name($names['login'] ?? 'bps-sso.login');

        Route::get('callback', [BpsSsoController::class, 'callback'])
            ->name($names['callback'] ?? 'bps-sso.callback');

        Route::match(['get', 'post'], 'logout', [BpsSsoController::class, 'logout'])
            ->name($names['logout'] ?? 'bps-sso.logout');

        Route::get('{driver}/login', [BpsSsoController::class, 'login'])
            ->name($names['driver_login'] ?? 'bps-sso.driver.login');

        Route::get('{driver}/callback', [BpsSsoController::class, 'callback'])
            ->name($names['driver_callback'] ?? 'bps-sso.driver.callback');

        Route::match(['get', 'post'], '{driver}/logout', [BpsSsoController::class, 'logout'])
            ->name($names['driver_logout'] ?? 'bps-sso.driver.logout');
    });
