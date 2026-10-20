<?php

namespace Bps\Sso\Tests\Unit;

use Bps\Sso\Exceptions\BpsSsoException;
use Bps\Sso\Exceptions\InvalidStateException;
use Bps\Sso\Facades\BpsSso;
use Bps\Sso\Services\BpsSsoManager;
use Bps\Sso\Tests\TestCase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class BpsSsoManagerTest extends TestCase
{
    public function test_it_generates_correct_endpoints_for_default_driver(): void
    {
        /** @var BpsSsoManager $manager */
        $manager = app('bps-sso');

        $this->assertEquals('https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/auth', $manager->getAuthEndpoint());
        $this->assertEquals('https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/token', $manager->getTokenEndpoint());
        $this->assertEquals('https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/userinfo', $manager->getUserInfoEndpoint());
        $this->assertEquals('https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/logout', $manager->getLogoutEndpoint());
    }

    public function test_it_supports_majapahit_driver(): void
    {
        $majapahit = BpsSso::driver('majapahit');

        $this->assertEquals('majapahit', $majapahit->getName());
        $this->assertEquals('https://sso-majapahit.bps.go.id/realms/master/protocol/openid-connect/auth', $majapahit->getAuthEndpoint());
        $this->assertEquals('https://sso-majapahit.bps.go.id/realms/master/protocol/openid-connect/token', $majapahit->getTokenEndpoint());

        $url = $majapahit->getLoginUrl();
        $this->assertStringContainsString('https://sso-majapahit.bps.go.id/realms/master/protocol/openid-connect/auth', $url);
        $this->assertStringContainsString('client_id=majapahit_client_id', $url);
    }

    public function test_it_generates_login_url_with_state(): void
    {
        $url = BpsSso::getLoginUrl();

        $this->assertStringContainsString('https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/auth', $url);
        $this->assertStringContainsString('client_id=test_client_id', $url);
        $this->assertStringContainsString('response_type=code', $url);
        $this->assertStringContainsString('state=', $url);

        $stateKey = BpsSso::driver()->getSessionKey('state');
        $this->assertNotEmpty(session($stateKey));
    }

    public function test_it_generates_logout_url(): void
    {
        $logoutUrl = BpsSso::getLogoutUrl('sample-id-token', 'https://myapp.com');

        $this->assertStringContainsString('https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/logout', $logoutUrl);
        $this->assertStringContainsString('id_token_hint=sample-id-token', $logoutUrl);
        $this->assertStringContainsString('post_logout_redirect_uri=' . urlencode('https://myapp.com'), $logoutUrl);
    }

    public function test_it_validates_state_successfully(): void
    {
        $stateKey = BpsSso::driver()->getSessionKey('state');
        session([$stateKey => 'secret123']);

        $request = Request::create('/sso/callback?state=secret123');

        /** @var BpsSsoManager $manager */
        $manager = app('bps-sso');
        $manager->validateState($request);

        $this->assertNull(session($stateKey));
    }

    public function test_it_throws_exception_on_invalid_state(): void
    {
        $this->expectException(InvalidStateException::class);

        $stateKey = BpsSso::driver()->getSessionKey('state');
        session([$stateKey => 'secret123']);

        $request = Request::create('/sso/callback?state=wrong_state');

        /** @var BpsSsoManager $manager */
        $manager = app('bps-sso');
        $manager->validateState($request);
    }

    public function test_it_exchanges_code_for_tokens(): void
    {
        Http::fake([
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/token' => Http::response([
                'access_token' => 'mocked-access-token',
                'id_token' => 'mocked-id-token',
                'refresh_token' => 'mocked-refresh-token',
                'expires_in' => 3600,
            ], 200),
        ]);

        $tokens = BpsSso::exchangeCodeForTokens('sample-auth-code');

        $this->assertEquals('mocked-access-token', $tokens['access_token']);
        $this->assertEquals('mocked-id-token', $tokens['id_token']);
    }

    public function test_it_fetches_user_info(): void
    {
        Http::fake([
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/userinfo' => Http::response([
                'sub' => '12345',
                'preferred_username' => 'budi.santoso',
                'name' => 'Budi Santoso',
                'email' => 'budi@bps.go.id',
                'nip' => '199001012015011001',
            ], 200),
        ]);

        $userInfo = BpsSso::fetchUserInfo('mocked-access-token');

        $this->assertEquals('Budi Santoso', $userInfo['name']);
        $this->assertEquals('budi.santoso', $userInfo['preferred_username']);
        $this->assertEquals('budi@bps.go.id', $userInfo['email']);
    }

    public function test_it_extracts_majapahit_kode_satker(): void
    {
        $payload1 = ['groups' => ['/Majapahit-3514']];
        $this->assertEquals('3514', BpsSso::extractKodeSatker($payload1));

        $payload2 = ['satker' => 'Majapahit_3578'];
        $this->assertEquals('3578', BpsSso::extractKodeSatker($payload2));

        $this->assertEquals('3500', BpsSso::extractKodeSatker([], 'superadmin'));

        $payload3 = ['kode_satker' => '3501'];
        $this->assertEquals('3501', BpsSso::extractKodeSatker($payload3));

        $payload4 = ['kodeprovinsi' => '35', 'kodekabupaten' => '78'];
        $this->assertEquals('3578', BpsSso::extractKodeSatker($payload4));

        $payload5 = ['organisasi' => '35140'];
        $this->assertEquals('3514', BpsSso::extractKodeSatker($payload5));
    }

    public function test_it_extracts_nip_lama_and_nip_baru(): void
    {
        $payload = [
            'niplama' => '340012345',
            'nipbaru' => '199001012015011001',
        ];

        $this->assertEquals('340012345', BpsSso::extractNipLama($payload));
        $this->assertEquals('199001012015011001', BpsSso::extractNipBaru($payload));
        $this->assertEquals('340012345', BpsSso::extractNip($payload, preferLama: true));
        $this->assertEquals('199001012015011001', BpsSso::extractNip($payload, preferLama: false));

        $payloadCentral = [
            'nip-lama' => '340056789',
            'nip_baru' => '199501012020011002',
        ];

        $this->assertEquals('340056789', BpsSso::extractNipLama($payloadCentral));
        $this->assertEquals('199501012020011002', BpsSso::extractNipBaru($payloadCentral));
    }

    public function test_it_extracts_keycloak_roles(): void
    {
        $payload = [
            'realm_access' => [
                'roles' => ['kalingga-admin-wilayah', 'viewer', 'default-roles-master'],
            ],
            'resource_access' => [
                'test_client_id' => [
                    'roles' => ['app-operator'],
                ],
            ],
        ];

        $roles = BpsSso::extractRoles($payload);

        $this->assertContains('kalingga-admin-wilayah', $roles);
        $this->assertContains('viewer', $roles);
        $this->assertContains('app-operator', $roles);
    }

    public function test_it_validates_allowed_email_domains(): void
    {
        config(['sso-bps.allowed_email_domains' => ['bps.go.id']]);

        BpsSso::driver()->validateEmailDomain('budi@bps.go.id');
        $this->assertTrue(true);

        $this->expectException(BpsSsoException::class);
        BpsSso::driver()->validateEmailDomain('budi@gmail.com');
    }

    public function test_it_supports_custom_user_resolver(): void
    {
        BpsSso::resolveUserUsing(function ($userInfo, $tokens, $driver) {
            return (object) [
                'id' => 999,
                'email' => $userInfo['email'],
                'name' => $userInfo['name'],
                'driver' => $driver,
                'custom' => true,
            ];
        });

        $user = BpsSso::resolveUser([
            'email' => 'custom@bps.go.id',
            'name' => 'Custom User',
        ], ['access_token' => 'abc']);

        $this->assertEquals(999, $user->id);
        $this->assertEquals('custom@bps.go.id', $user->email);
        $this->assertEquals('pusat', $user->driver);
        $this->assertTrue($user->custom);
    }

    public function test_it_resolves_user_from_bearer_token(): void
    {
        Http::fake([
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/userinfo' => Http::response([
                'email' => 'bearer@bps.go.id',
                'name' => 'Bearer User',
            ], 200),
        ]);

        BpsSso::resolveUserUsing(function ($userInfo) {
            return (object) ['email' => $userInfo['email'], 'name' => $userInfo['name']];
        });

        $user = BpsSso::userFromBearerToken('some-keycloak-token');

        $this->assertNotNull($user);
        $this->assertEquals('bearer@bps.go.id', $user->email);
        $this->assertEquals('Bearer User', $user->name);
    }
}
