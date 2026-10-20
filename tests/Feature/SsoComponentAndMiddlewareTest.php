<?php

namespace Bps\Sso\Tests\Feature;

use Bps\Sso\Middleware\EnsureBpsAuthenticated;
use Bps\Sso\Tests\TestCase;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

class ComponentTestUser extends Authenticatable
{
    protected $guarded = [];
}

class SsoComponentAndMiddlewareTest extends TestCase
{
    public function test_sso_button_component_renders_html(): void
    {
        $rendered = Blade::render('<x-bps-sso-button text="Masuk Akun BPS" class="custom-class" />');

        $this->assertStringContainsString('Masuk Akun BPS', $rendered);
        $this->assertStringContainsString('custom-class', $rendered);
        $this->assertStringContainsString('/sso/login', $rendered);
        $this->assertStringContainsString('bps-sso-btn-pusat', $rendered);
        $this->assertStringContainsString('<svg class="bps-sso-icon"', $rendered);

        $majapahitRendered = Blade::render('<x-bps-sso-button driver="majapahit" />');
        $this->assertStringContainsString('Masuk dengan SSO Majapahit', $majapahitRendered);
        $this->assertStringContainsString('/sso/majapahit/login', $majapahitRendered);
        $this->assertStringContainsString('bps-sso-btn-majapahit', $majapahitRendered);
    }

    public function test_middleware_redirects_unauthenticated_user_to_login(): void
    {
        Route::get('/protected-page', function () {
            return 'Protected Content';
        })->middleware(EnsureBpsAuthenticated::class);

        $response = $this->get('/protected-page');

        $response->assertStatus(302);
        $this->assertStringContainsString('/sso/login', $response->headers->get('Location'));
    }

    public function test_middleware_allows_authenticated_user(): void
    {
        Route::get('/protected-page-2', function () {
            return 'Protected Content';
        })->middleware(EnsureBpsAuthenticated::class);

        $user = new ComponentTestUser(['id' => 1, 'name' => 'Budi']);
        Auth::login($user);

        $response = $this->get('/protected-page-2');

        $response->assertStatus(200);
        $response->assertSee('Protected Content');
    }

    public function test_middleware_returns_401_for_json_request_when_unauthenticated(): void
    {
        Route::get('/api/protected-resource', function () {
            return response()->json(['status' => 'ok']);
        })->middleware(EnsureBpsAuthenticated::class);

        $response = $this->getJson('/api/protected-resource');

        $response->assertStatus(401);
        $response->assertJson(['message' => 'Unauthenticated.']);
    }
}
