<?php

namespace Bps\Sso\Facades;

use Bps\Sso\Services\BpsSsoDriver;
use Bps\Sso\Services\BpsSsoManager;
use Bps\Sso\Services\PegawaiApiClient;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Facade;

/**
 * @method static BpsSsoDriver driver(?string $name = null)
 * @method static string getLoginUrl(?string $state = null, array $additionalParams = [])
 * @method static string getLogoutUrl(?string $idToken = null, ?string $postLogoutRedirectUri = null)
 * @method static array handleCallback(Request $request)
 * @method static array exchangeCodeForTokens(string $code)
 * @method static array fetchUserInfo(string $accessToken)
 * @method static void resolveUserUsing(Closure $callback)
 * @method static mixed resolveUser(array $userInfo, array $tokens)
 * @method static mixed userFromBearerToken(?string $token = null)
 * @method static string logout(?string $postLogoutRedirectUri = null)
 * @method static PegawaiApiClient pegawai()
 * @method static string|null extractKodeSatker(array $userData, ?string $role = null)
 * @method static array extractRoles(array $userData)
 * @method static string|null getAccessToken()
 * @method static string|null getIdToken()
 * @method static string|null getRefreshToken()
 * @method static array|null getUserInfo()
 * @method static mixed getConfig(?string $key = null, mixed $default = null)
 *
 * @see BpsSsoManager
 * @see BpsSsoDriver
 */
class BpsSso extends Facade
{
    /**
     * Get the registered name of the component.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'bps-sso';
    }
}
