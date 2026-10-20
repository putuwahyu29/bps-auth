<?php

namespace Bps\Sso\Services;

use Bps\Sso\Events\BpsSsoAuthenticated;
use Bps\Sso\Events\BpsSsoLoggedOut;
use Bps\Sso\Exceptions\BpsSsoException;
use Bps\Sso\Exceptions\InvalidStateException;
use Bps\Sso\Exceptions\TokenExchangeException;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Throwable;

class BpsSsoDriver
{
    protected string $name;
    protected array $config;
    protected array $globalConfig;
    protected ?Closure $userResolver = null;
    protected ?PegawaiApiClient $pegawaiClient = null;

    public function __construct(string $name, array $connectionConfig, array $globalConfig, ?Closure $userResolver = null)
    {
        $this->name = $name;
        $this->config = $connectionConfig;
        $this->globalConfig = $globalConfig;
        $this->userResolver = $userResolver;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }

    public function getGlobalConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->globalConfig;
        }

        return data_get($this->globalConfig, $key, $default);
    }

    public function setUserResolver(?Closure $userResolver): void
    {
        $this->userResolver = $userResolver;
    }

    public function getBaseUrl(): string
    {
        return rtrim($this->getConfig('base_url', 'https://accounts.bps.go.id'), '/');
    }

    public function getRealm(): string
    {
        return $this->getConfig('realm', 'pegawai');
    }

    public function getClientId(): string
    {
        return $this->getConfig('client_id', '');
    }

    public function getClientSecret(): string
    {
        return $this->getConfig('client_secret', '');
    }

    public function getBaseRealmUrl(): string
    {
        return "{$this->getBaseUrl()}/realms/{$this->getRealm()}/protocol/openid-connect";
    }

    public function getAuthEndpoint(): string
    {
        return "{$this->getBaseRealmUrl()}/auth";
    }

    public function getTokenEndpoint(): string
    {
        return "{$this->getBaseRealmUrl()}/token";
    }

    public function getUserInfoEndpoint(): string
    {
        return "{$this->getBaseRealmUrl()}/userinfo";
    }

    public function getLogoutEndpoint(): string
    {
        return "{$this->getBaseRealmUrl()}/logout";
    }

    public function getRedirectUri(): string
    {
        $configured = $this->getConfig('redirect_uri');

        if (!empty($configured)) {
            return $configured;
        }

        $defaultDriver = $this->getGlobalConfig('default', 'pusat');

        if ($this->name === $defaultDriver) {
            $routeName = $this->getGlobalConfig('routes.names.callback', 'bps-sso.callback');
            if (Route::has($routeName)) {
                return route($routeName);
            }
            return url('/sso/callback');
        }

        $driverRouteName = $this->getGlobalConfig('routes.names.driver_callback', 'bps-sso.driver.callback');
        if (Route::has($driverRouteName)) {
            return route($driverRouteName, ['driver' => $this->name]);
        }

        return url("/sso/{$this->name}/callback");
    }

    public function getLoginUrl(?string $state = null, array $additionalParams = []): string
    {
        if ($state === null) {
            $state = bin2hex(random_bytes(16));
        }

        $sessionStateKey = $this->getSessionKey('state');
        session([$sessionStateKey => $state]);

                $defaultScopes = $this->name === 'pusat'
            ? ['openid', 'profile', 'email', 'groups', 'organization']
            : ['openid', 'profile', 'email'];
        $scopes = $this->getConfig('scopes', $defaultScopes);
        if (is_array($scopes)) {
            $scopes = implode(' ', $scopes);
        }

        $params = array_merge([
            'response_type' => 'code',
            'client_id' => $this->getClientId(),
            'redirect_uri' => $this->getRedirectUri(),
            'scope' => $scopes,
            'state' => $state,
        ], $additionalParams);

        return $this->getAuthEndpoint() . '?' . http_build_query($params);
    }

    public function getLogoutUrl(?string $idToken = null, ?string $postLogoutRedirectUri = null): string
    {
        $idToken = $idToken ?? $this->getIdToken();
        $postLogoutRedirectUri = $postLogoutRedirectUri
            ?? $this->getConfig('post_logout_redirect_uri')
            ?? $this->getGlobalConfig('post_logout_redirect_uri', url('/'));

        if (!Str::startsWith($postLogoutRedirectUri, ['http://', 'https://'])) {
            $postLogoutRedirectUri = url($postLogoutRedirectUri);
        }

        $params = [
            'post_logout_redirect_uri' => $postLogoutRedirectUri,
        ];

        if (!empty($idToken)) {
            $params['id_token_hint'] = $idToken;
        }

        return $this->getLogoutEndpoint() . '?' . http_build_query($params);
    }

    public function validateState(Request $request): void
    {
        $sessionStateKey = $this->getSessionKey('state');
        $expectedState = session($sessionStateKey);

        session()->forget($sessionStateKey);

        $receivedState = $request->input('state');

        if (empty($expectedState) || empty($receivedState) || !hash_equals((string) $expectedState, (string) $receivedState)) {
            throw new InvalidStateException();
        }
    }

    public function exchangeCodeForTokens(string $code): array
    {
        $params = [
            'grant_type' => 'authorization_code',
            'client_id' => $this->getClientId(),
            'client_secret' => $this->getClientSecret(),
            'code' => $code,
            'redirect_uri' => $this->getRedirectUri(),
        ];

        $verifySsl = (bool) $this->getGlobalConfig('api.verify_ssl', true);
        $timeout = (int) $this->getGlobalConfig('api.timeout', 15);

        try {
            $response = Http::withOptions(['verify' => $verifySsl])
                ->timeout($timeout)
                ->asForm()
                ->post($this->getTokenEndpoint(), $params);
        } catch (Throwable $e) {
            throw new TokenExchangeException("Gagal menghubungi Token Endpoint Keycloak ({$this->name}): {$e->getMessage()}", 0);
        }

        if (!$response->successful()) {
            throw new TokenExchangeException(
                "Gagal menukar code dengan token ({$this->name}): {$response->body()}",
                $response->status(),
                $response->json()
            );
        }

        $data = $response->json();

        if (!is_array($data) || empty($data['access_token'])) {
            throw new TokenExchangeException("Token response tidak valid: {$response->body()}", 0, $data);
        }

        return $data;
    }

    public function fetchUserInfo(string $accessToken): array
    {
        $verifySsl = (bool) $this->getGlobalConfig('api.verify_ssl', true);
        $timeout = (int) $this->getGlobalConfig('api.timeout', 15);

        try {
            $response = Http::withOptions(['verify' => $verifySsl])
                ->timeout($timeout)
                ->withToken($accessToken)
                ->get($this->getUserInfoEndpoint());
        } catch (Throwable $e) {
            throw new BpsSsoException("Gagal menghubungi UserInfo Endpoint Keycloak ({$this->name}): {$e->getMessage()}", 0);
        }

        if (!$response->successful()) {
            throw new BpsSsoException(
                "Gagal mengambil UserInfo ({$this->name}): {$response->body()}",
                $response->status()
            );
        }

        $data = $response->json();

        if (!is_array($data)) {
            throw new BpsSsoException("UserInfo response bukan JSON valid: {$response->body()}");
        }

        return $data;
    }

    public function handleCallback(Request $request): array
    {
        if ($request->has('error')) {
            $errorDesc = $request->input('error_description', $request->input('error'));
            throw new BpsSsoException("Keycloak ({$this->name}) mengembalikan error: {$errorDesc}");
        }

        $this->validateState($request);

        $code = $request->input('code');
        if (empty($code)) {
            throw new BpsSsoException('Authorization Code tidak ditemukan pada request callback.');
        }

        $tokens = $this->exchangeCodeForTokens($code);
        $userInfo = $this->fetchUserInfo($tokens['access_token']);

        $this->validateEmailDomain($userInfo['email'] ?? null);

        $this->storeSessionData($tokens, $userInfo);

        $user = $this->resolveUser($userInfo, $tokens);

        if ($user) {
            Auth::login($user);
            event(new BpsSsoAuthenticated($user, $userInfo, $tokens));
        }

        return [
            'user' => $user,
            'userInfo' => $userInfo,
            'tokens' => $tokens,
            'driver' => $this->name,
        ];
    }

    public function validateEmailDomain(?string $email): void
    {
        $allowedDomains = $this->getGlobalConfig('allowed_email_domains', []);

        if (empty($allowedDomains) || empty($email)) {
            return;
        }

        $emailLower = strtolower($email);
        foreach ($allowedDomains as $domain) {
            $expectedSuffix = '@' . ltrim(strtolower($domain), '@');
            if (str_ends_with($emailLower, $expectedSuffix)) {
                return;
            }
        }

        $allowedStr = implode(', ', $allowedDomains);
        throw new BpsSsoException("Akses ditolak: Alamat email [{$email}] bukan domain yang diizinkan ({$allowedStr}).");
    }

    public function resolveUser(array $userInfo, array $tokens): mixed
    {
        if ($this->userResolver !== null) {
            return ($this->userResolver)($userInfo, $tokens, $this->name);
        }

        $userModelClass = $this->getGlobalConfig('user.model', 'App\\Models\\User');

        if (!class_exists($userModelClass)) {
            return null;
        }

        $lookupColumn = $this->getGlobalConfig('user.lookup_column', 'email');
        $lookupClaim = $this->getGlobalConfig('user.lookup_claim', 'email');

        $lookupValue = $this->extractClaimValue($userInfo, $lookupClaim);

        if (empty($lookupValue)) {
            return null;
        }

        $user = $userModelClass::where($lookupColumn, $lookupValue)->first();

        $createIfNotExists = (bool) $this->getGlobalConfig('user.create_if_not_exists', true);
        $updateOnLogin = (bool) $this->getGlobalConfig('user.update_on_login', true);
        $columnsMapping = (array) $this->getGlobalConfig('user.columns', []);

        $kodeSatker = $this->extractKodeSatker($userInfo);

        if (!$user && $createIfNotExists) {
            $userData = [
                $lookupColumn => $lookupValue,
            ];

            foreach ($columnsMapping as $column => $claim) {
                $val = $this->extractClaimValue($userInfo, $claim);
                if ($val !== null) {
                    $userData[$column] = $val;
                }
            }

            if ($kodeSatker !== null) {
                if (isset($columnsMapping['kode_satker'])) {
                    $userData['kode_satker'] = $kodeSatker;
                } elseif (isset($columnsMapping['kode_kabkot'])) {
                    $userData['kode_kabkot'] = $kodeSatker;
                }
            }

            if ($this->getGlobalConfig('user.generate_random_password', true)) {
                $userData['password'] = Hash::make(Str::random(32));
            }

            $user = $userModelClass::create($userData);
        } elseif ($user && $updateOnLogin) {
            $dirty = false;
            foreach ($columnsMapping as $column => $claim) {
                if ($column === $lookupColumn) {
                    continue;
                }
                $val = $this->extractClaimValue($userInfo, $claim);
                if ($val !== null && $user->{$column} !== $val) {
                    $user->{$column} = $val;
                    $dirty = true;
                }
            }

            if ($kodeSatker !== null) {
                if (isset($columnsMapping['kode_satker']) && $user->kode_satker !== $kodeSatker) {
                    $user->kode_satker = $kodeSatker;
                    $dirty = true;
                } elseif (isset($columnsMapping['kode_kabkot']) && $user->kode_kabkot !== $kodeSatker) {
                    $user->kode_kabkot = $kodeSatker;
                    $dirty = true;
                }
            }

            if ($dirty) {
                $user->save();
            }
        }

        return $user;
    }

    public function extractClaimValue(array $userInfo, string $claim): mixed
    {
        if (array_key_exists($claim, $userInfo)) {
            $val = $userInfo[$claim];
            return is_array($val) ? ($val[0] ?? null) : $val;
        }

        if (isset($userInfo['attributes']) && is_array($userInfo['attributes'])) {
            if (array_key_exists($claim, $userInfo['attributes'])) {
                $val = $userInfo['attributes'][$claim];
                return is_array($val) ? ($val[0] ?? null) : $val;
            }
        }

        if ($claim === 'kode_satker' || $claim === 'kode_kabkot' || $claim === 'satker') {
            return $this->extractKodeSatker($userInfo);
        }

        if ($claim === 'nip_lama' || $claim === 'niplama') {
            return $this->extractNipLama($userInfo);
        }

        if ($claim === 'nip_baru' || $claim === 'nipbaru') {
            return $this->extractNipBaru($userInfo);
        }

        if ($claim === 'nip') {
            return $this->extractNip($userInfo);
        }

        return data_get($userInfo, $claim);
    }

    /**
     * Ekstrak kode satker/kabkot dari payload Keycloak (Mendukung pola SSO Majapahit / SSO BPS / kodeprovinsi + kodekabupaten).
     */
    public function extractKodeSatker(array $userData, ?string $role = null): ?string
    {
        if ($role === 'superadmin') {
            return '3500';
        }

        $kodeProv = $userData['kodeprovinsi']
            ?? $userData['kode_provinsi']
            ?? (isset($userData['attributes']['kodeprovinsi'][0]) ? $userData['attributes']['kodeprovinsi'][0] : null)
            ?? (isset($userData['attributes']['kode_provinsi'][0]) ? $userData['attributes']['kode_provinsi'][0] : null);

        $kodeKab = $userData['kodekabupaten']
            ?? $userData['kode_kabupaten']
            ?? (isset($userData['attributes']['kodekabupaten'][0]) ? $userData['attributes']['kodekabupaten'][0] : null)
            ?? (isset($userData['attributes']['kode_kabupaten'][0]) ? $userData['attributes']['kode_kabupaten'][0] : null);

        if ($kodeProv !== null && $kodeKab !== null && $kodeProv !== '' && $kodeKab !== '') {
            return sprintf('%02d%02d', (int) $kodeProv, (int) $kodeKab);
        }

        $rawCandidates = [
            $userData['satker'] ?? null,
            $userData['groups'] ?? null,
            $userData['attributes']['satker'] ?? null,
            $userData['attributes']['groups'] ?? null,
            $userData['attributes']['kode_satker'] ?? null,
            $userData['kode_satker'] ?? null,
            $userData['attributes']['kode_kabkot'] ?? null,
            $userData['kode_kabkot'] ?? null,
            $userData['attributes']['kdsatker'] ?? null,
            $userData['kdsatker'] ?? null,
            $userData['attributes']['unit'] ?? null,
            $userData['unit'] ?? null,
            $userData['organisasi'] ?? null,
            $userData['attributes']['organisasi'] ?? null,
            $userData['kodeunitorganisasi'] ?? null,
            $userData['attributes']['kodeunitorganisasi'] ?? null,
        ];

        $flattened = [];
        foreach ($rawCandidates as $candidate) {
            if (is_array($candidate)) {
                foreach ($candidate as $item) {
                    if (is_string($item) || is_numeric($item)) {
                        $flattened[] = (string) $item;
                    }
                }
            } elseif (is_string($candidate) || is_numeric($candidate)) {
                $flattened[] = (string) $candidate;
            }
        }

        foreach ($flattened as $value) {
            $value = trim($value);
            if (preg_match('/Majapahit[-_]?(\d{4})/i', $value, $matches)) {
                return $matches[1];
            }
        }

        foreach ($flattened as $value) {
            $value = trim($value);
            if (preg_match('/(?:^|[^\d])(\d{4,5})(?:[^\d]|$)/', $value, $matches)) {
                $num = $matches[1];
                return strlen($num) === 5 ? substr($num, 0, 4) : $num;
            }
        }

        foreach ($flattened as $value) {
            $value = trim($value);
            if (preg_match('/^\d{2}$/', $value)) {
                return $value . '00';
            }
        }

        return null;
    }

    /**
     * Ekstrak NIP Lama (9 digit) dari data klaim Keycloak.
     */
    public function extractNipLama(array $userData): ?string
    {
        $candidates = [
            $userData['niplama'] ?? null,
            $userData['nip-lama'] ?? null,
            $userData['attributes']['niplama'] ?? null,
            $userData['attributes']['nip-lama'] ?? null,
            $userData['nip_lama'] ?? null,
            $userData['attributes']['nip_lama'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $val = is_array($candidate) ? ($candidate[0] ?? null) : $candidate;
            if ($val !== null) {
                $trimmed = trim((string) $val);
                if (strlen($trimmed) === 9 && ctype_digit($trimmed)) {
                    return $trimmed;
                }
            }
        }

        $rawNip = $userData['nip'] ?? $userData['attributes']['nip'] ?? null;
        $val = is_array($rawNip) ? ($rawNip[0] ?? null) : $rawNip;
        if ($val !== null) {
            $trimmed = trim((string) $val);
            if (strlen($trimmed) === 9 && ctype_digit($trimmed)) {
                return $trimmed;
            }
        }

        $username = $userData['preferred_username'] ?? null;
        if ($username !== null) {
            $trimmed = trim((string) $username);
            if (strlen($trimmed) === 9 && ctype_digit($trimmed)) {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Ekstrak NIP Baru (18 digit) dari data klaim Keycloak.
     */
    public function extractNipBaru(array $userData): ?string
    {
        $candidates = [
            $userData['nipbaru'] ?? null,
            $userData['nip_baru'] ?? null,
            $userData['attributes']['nipbaru'] ?? null,
            $userData['attributes']['nip_baru'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            $val = is_array($candidate) ? ($candidate[0] ?? null) : $candidate;
            if ($val !== null) {
                $trimmed = trim((string) $val);
                if (strlen($trimmed) === 18 && ctype_digit($trimmed)) {
                    return $trimmed;
                }
            }
        }

        $rawNip = $userData['nip'] ?? $userData['attributes']['nip'] ?? null;
        $val = is_array($rawNip) ? ($rawNip[0] ?? null) : $rawNip;
        if ($val !== null) {
            $trimmed = trim((string) $val);
            if (strlen($trimmed) === 18 && ctype_digit($trimmed)) {
                return $trimmed;
            }
        }

        $username = $userData['preferred_username'] ?? null;
        if ($username !== null) {
            $trimmed = trim((string) $username);
            if (strlen($trimmed) === 18 && ctype_digit($trimmed)) {
                return $trimmed;
            }
        }

        return null;
    }

    /**
     * Ekstrak NIP (memprioritaskan NIP Baru atau NIP Lama sesuai parameter).
     */
    public function extractNip(array $userData, bool $preferLama = false): ?string
    {
        if ($preferLama) {
            return $this->extractNipLama($userData) ?? $this->extractNipBaru($userData);
        }

        return $this->extractNipBaru($userData) ?? $this->extractNipLama($userData);
    }

    /**
     * Ekstrak roles Keycloak dari realm_access.roles atau resource_access.
     */
    public function extractRoles(array $userData): array
    {
        $roles = [];

        if (isset($userData['realm_access']['roles']) && is_array($userData['realm_access']['roles'])) {
            $roles = array_merge($roles, $userData['realm_access']['roles']);
        }

        if (isset($userData['resource_access']) && is_array($userData['resource_access'])) {
            $clientId = $this->getClientId();
            if (isset($userData['resource_access'][$clientId]['roles'])) {
                $roles = array_merge($roles, $userData['resource_access'][$clientId]['roles']);
            }
        }

        if (isset($userData['roles']) && is_array($userData['roles'])) {
            $roles = array_merge($roles, $userData['roles']);
        }

        return array_values(array_unique($roles));
    }

    public function userFromBearerToken(?string $token = null): mixed
    {
        $token = $token ?? request()->bearerToken();

        if (empty($token)) {
            return null;
        }

        $userInfo = $this->fetchUserInfo($token);

        return $this->resolveUser($userInfo, ['access_token' => $token]);
    }

    public function logout(?string $postLogoutRedirectUri = null): string
    {
        $user = Auth::user();
        $idToken = $this->getIdToken();

        event(new BpsSsoLoggedOut($user));

        Auth::logout();

        session()->forget([
            $this->getSessionKey('access_token'),
            $this->getSessionKey('id_token'),
            $this->getSessionKey('refresh_token'),
            $this->getSessionKey('user_info'),
            $this->getSessionKey('state'),
        ]);

        return $this->getLogoutUrl($idToken, $postLogoutRedirectUri);
    }

    protected function storeSessionData(array $tokens, array $userInfo): void
    {
        session([
            $this->getSessionKey('access_token') => $tokens['access_token'] ?? null,
            $this->getSessionKey('id_token') => $tokens['id_token'] ?? null,
            $this->getSessionKey('refresh_token') => $tokens['refresh_token'] ?? null,
            $this->getSessionKey('user_info') => $userInfo,
            'bps_sso_active_driver' => $this->name,
        ]);
    }

    public function getSessionKey(string $type): string
    {
        $baseKey = $this->getGlobalConfig("session.{$type}", "bps_sso_{$type}");
        return "{$baseKey}_{$this->name}";
    }

    public function getAccessToken(): ?string
    {
        return session($this->getSessionKey('access_token'));
    }

    public function getIdToken(): ?string
    {
        return session($this->getSessionKey('id_token'));
    }

    public function getRefreshToken(): ?string
    {
        return session($this->getSessionKey('refresh_token'));
    }

    public function getUserInfo(): ?array
    {
        return session($this->getSessionKey('user_info'));
    }

    public function pegawai(): PegawaiApiClient
    {
        if ($this->pegawaiClient === null) {
            $mergedConfig = array_merge($this->globalConfig, $this->config);
            $this->pegawaiClient = new PegawaiApiClient($mergedConfig);
        }

        return $this->pegawaiClient;
    }
}
