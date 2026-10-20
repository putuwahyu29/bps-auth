<?php

namespace Bps\Sso\Tests\Feature;

use Bps\Sso\Events\BpsSsoAuthenticated;
use Bps\Sso\Events\BpsSsoLoggedOut;
use Bps\Sso\Facades\BpsSso;
use Bps\Sso\Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;

class TestUser extends Authenticatable
{
    protected $guarded = [];
}

class BpsSsoControllerTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('sso-bps.routes.enabled', true);
        $app['config']->set('sso-bps.redirect_after_login', '/dashboard');
    }

    public function test_login_route_redirects_to_keycloak(): void
    {
        $response = $this->get('/sso/login');

        $response->assertStatus(302);
        $this->assertStringContainsString('https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/auth', $response->headers->get('Location'));
    }

    public function test_driver_login_route_redirects_to_majapahit(): void
    {
        $response = $this->get('/sso/majapahit/login');

        $response->assertStatus(302);
        $this->assertStringContainsString('https://sso-majapahit.bps.go.id/realms/master/protocol/openid-connect/auth', $response->headers->get('Location'));
    }

    public function test_callback_route_authenticates_user_and_fires_event(): void
    {
        Event::fake([BpsSsoAuthenticated::class]);

        $stateKey = BpsSso::driver('pusat')->getSessionKey('state');
        session([$stateKey => 'valid-state-123']);

        Http::fake([
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/token' => Http::response([
                'access_token' => 'access-token-123',
                'id_token' => 'id-token-123',
                'refresh_token' => 'refresh-token-123',
                'expires_in' => 3600,
            ], 200),
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/userinfo' => Http::response([
                'sub' => 'sub-123',
                'preferred_username' => 'budi.santoso',
                'name' => 'Budi Santoso',
                'email' => 'budi@bps.go.id',
                'nip' => '199001012015011001',
            ], 200),
        ]);

        $mockUser = new TestUser([
            'id' => 1,
            'name' => 'Budi Santoso',
            'email' => 'budi@bps.go.id',
        ]);

        BpsSso::resolveUserUsing(function ($userInfo, $tokens) use ($mockUser) {
            return $mockUser;
        });

        $response = $this->get('/sso/callback?code=mock-code&state=valid-state-123');

        $response->assertRedirect('/dashboard');
        $this->assertTrue(Auth::check());
        $this->assertEquals('budi@bps.go.id', Auth::user()->email);

        Event::assertDispatched(BpsSsoAuthenticated::class, function ($event) use ($mockUser) {
            return $event->user === $mockUser
                && $event->userInfo['email'] === 'budi@bps.go.id'
                && $event->tokens['access_token'] === 'access-token-123';
        });
    }

    public function test_logout_route_logs_out_user_and_redirects(): void
    {
        Event::fake([BpsSsoLoggedOut::class]);

        $user = new TestUser(['id' => 1, 'name' => 'Budi']);
        Auth::login($user);
        $idTokenKey = BpsSso::driver('pusat')->getSessionKey('id_token');
        session([$idTokenKey => 'mock-id-token']);

        $response = $this->get('/sso/logout');

        $this->assertFalse(Auth::check());
        $response->assertStatus(302);
        $this->assertStringContainsString('https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/logout', $response->headers->get('Location'));
        $this->assertStringContainsString('id_token_hint=mock-id-token', $response->headers->get('Location'));

        Event::assertDispatched(BpsSsoLoggedOut::class);
    }

    public function test_api_json_requests_receive_json_responses(): void
    {
        $loginRes = $this->getJson('/sso/login');
        $loginRes->assertStatus(200);
        $loginRes->assertJsonStructure(['login_url', 'driver']);

        $stateKey = BpsSso::driver('pusat')->getSessionKey('state');
        session([$stateKey => 'api-state-456']);

        Http::fake([
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/token' => Http::response([
                'access_token' => 'api-token-456',
                'id_token' => 'api-id-456',
                'expires_in' => 3600,
            ], 200),
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/userinfo' => Http::response([
                'email' => 'api.user@bps.go.id',
                'name' => 'API User',
            ], 200),
        ]);

        $mockUser = new TestUser(['id' => 2, 'name' => 'API User', 'email' => 'api.user@bps.go.id']);
        BpsSso::resolveUserUsing(fn () => $mockUser);

        $callbackRes = $this->getJson('/sso/callback?code=mock-code&state=api-state-456');
        $callbackRes->assertStatus(200);
        $callbackRes->assertJson([
            'message' => 'Login SSO (pusat) berhasil.',
            'driver' => 'pusat',
            'user' => [
                'email' => 'api.user@bps.go.id',
            ],
        ]);

        $logoutRes = $this->getJson('/sso/logout');
        $logoutRes->assertStatus(200);
        $logoutRes->assertJsonStructure(['message', 'logout_url']);
    }
}
