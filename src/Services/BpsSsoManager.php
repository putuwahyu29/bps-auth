<?php

namespace Bps\Sso\Services;

use Closure;
use InvalidArgumentException;

/**
 * Manager untuk mengelola koneksi SSO Keycloak (SSO BPS, SSO Majapahit, atau Custom).
 *
 * @mixin BpsSsoDriver
 */
class BpsSsoManager
{
    protected array $config;
    protected array $drivers = [];
    protected ?Closure $userResolver = null;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * Dapatkan instance driver koneksi SSO tertentu ('pusat', 'majapahit', dll).
     */
    public function driver(?string $name = null): BpsSsoDriver
    {
        $name = $name ?: $this->getDefaultDriver();

        if (!isset($this->drivers[$name])) {
            $this->drivers[$name] = $this->resolve($name);
        }

        return $this->drivers[$name];
    }

    /**
     * Buat instance driver baru berdasarkan konfigurasi koneksi.
     */
    protected function resolve(string $name): BpsSsoDriver
    {
        $connections = $this->config['connections'] ?? [];

        if (empty($connections)) {
            $connectionConfig = [
                'base_url' => $this->config['base_url'] ?? 'https://accounts.bps.go.id',
                'realm' => $this->config['realm'] ?? 'pegawai',
                'client_id' => $this->config['client_id'] ?? '',
                'client_secret' => $this->config['client_secret'] ?? '',
                'redirect_uri' => $this->config['redirect_uri'] ?? null,
                'scopes' => $this->config['scopes'] ?? ['openid', 'profile', 'email'],
            ];
            return new BpsSsoDriver($name, $connectionConfig, $this->config, $this->userResolver);
        }

        if (!isset($connections[$name])) {
            throw new InvalidArgumentException("SSO Driver [{$name}] tidak ditemukan dalam konfigurasi sso-bps.connections.");
        }

        return new BpsSsoDriver($name, $connections[$name], $this->config, $this->userResolver);
    }

    /**
     * Nama driver default.
     */
    public function getDefaultDriver(): string
    {
        return $this->config['default'] ?? 'pusat';
    }

    /**
     * Set driver default.
     */
    public function setDefaultDriver(string $name): void
    {
        $this->config['default'] = $name;
    }

    /**
     * Daftarkan custom callback untuk pembuatan/pencarian user aplikasi.
     */
    public function resolveUserUsing(Closure $callback): void
    {
        $this->userResolver = $callback;

        foreach ($this->drivers as $driver) {
            $driver->setUserResolver($callback);
        }
    }

    /**
     * Ambil seluruh konfigurasi.
     */
    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) {
            return $this->config;
        }

        return data_get($this->config, $key, $default);
    }

    /**
     * Proxy semua pemanggilan method dinamis ke default driver.
     */
    public function __call(string $method, array $parameters): mixed
    {
        return $this->driver()->$method(...$parameters);
    }
}
