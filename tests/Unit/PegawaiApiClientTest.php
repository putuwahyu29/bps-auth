<?php

namespace Bps\Sso\Tests\Unit;

use Bps\Sso\Facades\BpsSso;
use Bps\Sso\Services\PegawaiApiClient;
use Bps\Sso\Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class PegawaiApiClientTest extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);
        $app['config']->set('sso-bps.client_id', 'service-account-app');
        $app['config']->set('sso-bps.client_secret', 'secret123');
    }

    public function test_it_obtains_and_caches_client_access_token(): void
    {
        Cache::flush();

        Http::fake([
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/token' => Http::response([
                'access_token' => 'client-credentials-token-xyz',
                'expires_in' => 3600,
            ], 200),
        ]);

        $pegawai = BpsSso::pegawai();
        $token = $pegawai->getAccessToken();

        $this->assertEquals('client-credentials-token-xyz', $token);
        $this->assertEquals('client-credentials-token-xyz', Cache::get('bps_sso_client_credentials_token'));

        Http::fake([
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/token' => Http::response([
                'access_token' => 'different-token',
            ], 200),
        ]);

        $cached = $pegawai->getAccessToken();
        $this->assertEquals('client-credentials-token-xyz', $cached);
    }

    public function test_it_finds_pegawai_by_username(): void
    {
        Http::fake([
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
            ], 200),
            'https://accounts.bps.go.id/realms/pegawai/api/username/dewi.lestari' => Http::response([
                [
                    'username' => 'dewi.lestari',
                    'attributes' => [
                        'nama' => ['Dewi Lestari'],
                        'email' => ['dewi.lestari@bps.go.id'],
                        'nip' => ['340056734'],
                        'nipbaru' => ['199001012015012001'],
                        'jabatan' => ['Pranata Komputer Ahli Muda'],
                        'unit' => ['34000'],
                    ],
                ],
            ], 200),
        ]);

        $pegawai = BpsSso::pegawai()->findByUsername('dewi.lestari');

        $this->assertNotNull($pegawai);
        $this->assertEquals('dewi.lestari', $pegawai->getUsername());
        $this->assertEquals('Dewi Lestari', $pegawai->getName());
        $this->assertEquals('dewi.lestari@bps.go.id', $pegawai->getEmail());
        $this->assertEquals('340056734', $pegawai->getNip());
        $this->assertEquals('199001012015012001', $pegawai->getNipBaru());
        $this->assertEquals('Pranata Komputer Ahli Muda', $pegawai->getJabatan());
        $this->assertEquals('34000', $pegawai->getUnit());
    }

    public function test_it_finds_pegawai_by_unit(): void
    {
        Http::fake([
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
            ], 200),
            'https://accounts.bps.go.id/realms/pegawai/api/unit/34000' => Http::response([
                [
                    'username' => 'dewi.lestari',
                    'attributes' => [
                        'nama' => ['Dewi Lestari'],
                        'unit' => ['34000'],
                    ],
                ],
                [
                    'username' => 'eko.prasetyo',
                    'attributes' => [
                        'nama' => ['Eko Prasetyo'],
                        'unit' => ['34000'],
                    ],
                ],
            ], 200),
        ]);

        $list = BpsSso::pegawai()->findByUnit('34000');

        $this->assertCount(2, $list);
        $this->assertEquals('Dewi Lestari', $list[0]->getName());
        $this->assertEquals('Eko Prasetyo', $list[1]->getName());
    }

    public function test_it_returns_null_when_not_found(): void
    {
        Http::fake([
            'https://accounts.bps.go.id/realms/pegawai/protocol/openid-connect/token' => Http::response([
                'access_token' => 'test-token',
            ], 200),
            'https://accounts.bps.go.id/realms/pegawai/api/nip/999999999' => Http::response([], 404),
        ]);

        $pegawai = BpsSso::pegawai()->findByNip('999999999');

        $this->assertNull($pegawai);
    }
}
