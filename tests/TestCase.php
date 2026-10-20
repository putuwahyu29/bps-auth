<?php

namespace Bps\Sso\Tests;

use Bps\Sso\BpsSsoServiceProvider;
use Bps\Sso\Facades\BpsSso;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();
    }

    protected function getPackageProviders($app): array
    {
        return [
            BpsSsoServiceProvider::class,
        ];
    }

    protected function getPackageAliases($app): array
    {
        return [
            'BpsSso' => BpsSso::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(str_repeat('1', 32)));
        $app['config']->set('sso-bps.default', 'pusat');
        $app['config']->set('sso-bps.connections', [
            'pusat' => [
                'name' => 'SSO BPS',
                'base_url' => 'https://accounts.bps.go.id',
                'realm' => 'pegawai',
                'client_id' => 'test_client_id',
                'client_secret' => 'test_client_secret',
                'redirect_uri' => 'http://localhost:8000/sso/callback',
                'scopes' => ['openid', 'profile', 'email'],
            ],
            'majapahit' => [
                'name' => 'SSO Majapahit',
                'base_url' => 'https://sso-majapahit.bps.go.id',
                'realm' => 'master',
                'client_id' => 'majapahit_client_id',
                'client_secret' => 'majapahit_client_secret',
                'redirect_uri' => 'http://localhost:8000/sso/majapahit/callback',
                'scopes' => ['openid', 'profile', 'email'],
            ],
        ]);
    }
}
