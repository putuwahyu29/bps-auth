<?php

namespace Bps\Sso\Services;

use Bps\Sso\DTO\PegawaiData;
use Bps\Sso\Exceptions\PegawaiApiException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class PegawaiApiClient
{
    protected string $baseUrl;
    protected string $realm;
    protected string $clientId;
    protected string $clientSecret;
    protected int $tokenTtl;
    protected string $cacheKey;
    protected int $timeout;
    protected bool $verifySsl;

    public function __construct(array $config)
    {
        $this->baseUrl = rtrim($config['base_url'] ?? 'https://accounts.bps.go.id', '/');
        $this->realm = $config['realm'] ?? 'pegawai';
        $this->clientId = $config['client_id'] ?? '';
        $this->clientSecret = $config['client_secret'] ?? '';
        $this->tokenTtl = (int) ($config['api']['token_cache_ttl'] ?? 3000);
        $this->cacheKey = $config['api']['cache_key'] ?? 'bps_sso_client_credentials_token';
        $this->timeout = (int) ($config['api']['timeout'] ?? 15);
        $this->verifySsl = (bool) ($config['api']['verify_ssl'] ?? true);
    }

    /**
     * Endpoint Token URL.
     */
    public function getTokenUrl(): string
    {
        return "{$this->baseUrl}/realms/{$this->realm}/protocol/openid-connect/token";
    }

    /**
     * Endpoint Base REST API Pegawai URL.
     */
    public function getApiBaseUrl(): string
    {
        return "{$this->baseUrl}/realms/{$this->realm}/api";
    }

    /**
     * Dapatkan Access Token (Client Credentials Grant) dengan caching otomatis.
     */
    public function getAccessToken(bool $forceRefresh = false): string
    {
        if ($forceRefresh) {
            Cache::forget($this->cacheKey);
        }

        $cachedToken = Cache::get($this->cacheKey);
        if ($cachedToken && !$forceRefresh) {
            return $cachedToken;
        }

        if (empty($this->clientId) || empty($this->clientSecret)) {
            throw new PegawaiApiException('Client ID dan Client Secret belum dikonfigurasi untuk REST API BPS.');
        }

        try {
            $response = Http::withOptions(['verify' => $this->verifySsl])
                ->timeout($this->timeout)
                ->withBasicAuth($this->clientId, $this->clientSecret)
                ->asForm()
                ->post($this->getTokenUrl(), [
                    'grant_type' => 'client_credentials',
                ]);
        } catch (Throwable $e) {
            throw new PegawaiApiException("Koneksi ke Token Endpoint Keycloak gagal: {$e->getMessage()}", 0);
        }

        if (!$response->successful()) {
            throw new PegawaiApiException(
                "Gagal mendapatkan client token dari Keycloak: {$response->body()}",
                $response->status(),
                $response->json()
            );
        }

        $data = $response->json();
        $accessToken = $data['access_token'] ?? null;

        if (!$accessToken) {
            throw new PegawaiApiException("Respons Keycloak tidak memiliki access_token: {$response->body()}");
        }

        $expiresIn = isset($data['expires_in']) ? ((int) $data['expires_in'] - 60) : $this->tokenTtl;
        $ttl = max(60, min($expiresIn, $this->tokenTtl));

        Cache::put($this->cacheKey, $accessToken, $ttl);

        return $accessToken;
    }

    /**
     * Cari pegawai berdasarkan username.
     */
    public function findByUsername(string $username): ?PegawaiData
    {
        $results = $this->query("/username/" . urlencode($username));
        return !empty($results) ? PegawaiData::fromArray($results[0]) : null;
    }

    /**
     * Cari pegawai berdasarkan email.
     */
    public function findByEmail(string $email): ?PegawaiData
    {
        $results = $this->query("/email/" . urlencode($email));
        return !empty($results) ? PegawaiData::fromArray($results[0]) : null;
    }

    /**
     * Cari pegawai berdasarkan NIP lama.
     */
    public function findByNip(string $nip): ?PegawaiData
    {
        $results = $this->query("/nip/" . urlencode($nip));
        return !empty($results) ? PegawaiData::fromArray($results[0]) : null;
    }

    /**
     * Cari pegawai berdasarkan NIP baru.
     */
    public function findByNipBaru(string $nipbaru): ?PegawaiData
    {
        $results = $this->query("/nipbaru/" . urlencode($nipbaru));
        return !empty($results) ? PegawaiData::fromArray($results[0]) : null;
    }

    /**
     * Cari daftar pegawai berdasarkan Kode Unit Organisasi.
     *
     * @return Collection<int, PegawaiData>
     */
    public function findByUnit(string $unitCode): Collection
    {
        $results = $this->query("/unit/" . urlencode($unitCode));

        return collect($results)->map(function ($item) {
            return PegawaiData::fromArray($item);
        });
    }

    /**
     * Menjalankan query langsung ke REST API Pegawai Keycloak BPS.
     */
    public function query(string $path, bool $retryOnUnauthorized = true): array
    {
        $token = $this->getAccessToken();
        $url = $this->getApiBaseUrl() . '/' . ltrim($path, '/');

        try {
            $response = Http::withOptions(['verify' => $this->verifySsl])
                ->timeout($this->timeout)
                ->withHeaders([
                    'Accept' => 'application/json',
                    'Authorization' => "Bearer {$token}",
                ])
                ->get($url);
        } catch (Throwable $e) {
            throw new PegawaiApiException("Request REST API Pegawai BPS gagal: {$e->getMessage()}", 0);
        }

        if ($response->status() === 401 && $retryOnUnauthorized) {
            $this->getAccessToken(forceRefresh: true);
            return $this->query($path, retryOnUnauthorized: false);
        }

        if ($response->status() === 404) {
            return [];
        }

        if (!$response->successful()) {
            throw new PegawaiApiException(
                "API Pegawai BPS mengembalikan error [{$response->status()}]: {$response->body()}",
                $response->status(),
                $response->json()
            );
        }

        $json = $response->json();

        if (is_array($json) && isset($json['error'])) {
            throw new PegawaiApiException("API Pegawai BPS Error: " . ($json['error_description'] ?? $json['error']));
        }

        return is_array($json) ? $json : [];
    }
}
