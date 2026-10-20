<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default SSO Connection
    |--------------------------------------------------------------------------
    | Driver koneksi Keycloak default yang digunakan:
    | - 'pusat'     : SSO BPS (accounts.bps.go.id, realm: pegawai)
    | - 'majapahit' : SSO Majapahit
    */
    'default' => env('BPS_SSO_DRIVER', 'pusat'),

    /*
    |--------------------------------------------------------------------------
    | SSO Connections
    |--------------------------------------------------------------------------
    | Konfigurasi masing-masing server Keycloak SSO yang dapat digunakan aplikasi.
    | Anda dapat beralih via .env (BPS_SSO_DRIVER) atau lewat kode:
    | BpsSso::driver('majapahit') atau BpsSso::driver('pusat').
    */
    'connections' => [
        // 1. SSO BPS
        'pusat' => [
            'name' => 'SSO BPS',
            'base_url' => env('BPS_SSO_KEYCLOAK_URL', env('KEYCLOAK_URL', 'https://accounts.bps.go.id')),
            'realm' => env('BPS_SSO_REALM', env('REALM', 'pegawai')),
            'client_id' => env('BPS_SSO_CLIENT_ID', env('CLIENT_ID', '')),
            'client_secret' => env('BPS_SSO_CLIENT_SECRET', env('CLIENT_SECRET', '')),
            'redirect_uri' => env('BPS_SSO_REDIRECT_URI', null),
            'post_logout_redirect_uri' => env('BPS_SSO_POST_LOGOUT_REDIRECT_URI', '/'),
            'scopes' => array_values(array_filter(explode(',', env('BPS_SSO_SCOPES', 'openid,profile,email')))),
            'has_pegawai_api' => true,
        ],

        // 2. SSO Majapahit
        'majapahit' => [
            'name' => 'SSO Majapahit',
            'base_url' => env('MAJAPAHIT_SSO_URL', env('KEYCLOAK_BASE_URL', 'https://sso-majapahit.bps.go.id')),
            'realm' => env('MAJAPAHIT_SSO_REALM', env('KEYCLOAK_REALM', 'master')),
            'client_id' => env('MAJAPAHIT_SSO_CLIENT_ID', env('KEYCLOAK_CLIENT_ID', '')),
            'client_secret' => env('MAJAPAHIT_SSO_CLIENT_SECRET', env('KEYCLOAK_CLIENT_SECRET', '')),
            'redirect_uri' => env('MAJAPAHIT_SSO_REDIRECT_URI', null),
            'post_logout_redirect_uri' => env('MAJAPAHIT_SSO_POST_LOGOUT_REDIRECT_URI', '/'),
            'scopes' => array_values(array_filter(explode(',', env('BPS_SSO_SCOPES', 'openid,profile,email')))),
            'has_pegawai_api' => false,
            'extract_satker' => true, // Otomatis ekstrak Majapahit-35xx -> '3514'
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Validasi Domain Email (Security Whitelist)
    |--------------------------------------------------------------------------
    | Jika diisi (contoh: ['bps.go.id']), hanya pengguna dengan email berakhiran
    | domain tersebut yang diizinkan masuk. Kosongkan [] jika izinkan semua domain.
    */
    'allowed_email_domains' => array_values(array_filter(explode(',', env('BPS_SSO_ALLOWED_EMAIL_DOMAINS', '')))),

    /*
    |--------------------------------------------------------------------------
    | Built-in Routes
    |--------------------------------------------------------------------------
    | Konfigurasi routing bawaan package untuk login, callback, dan logout.
    | Mendukung default rute (/sso/login) dan multi-driver (/sso/{driver}/login).
    */
    'routes' => [
        'enabled' => env('BPS_SSO_ROUTES_ENABLED', true),
        'prefix' => env('BPS_SSO_ROUTE_PREFIX', 'sso'),
        'middleware' => ['web'],
        'names' => [
            'login' => 'bps-sso.login',
            'callback' => 'bps-sso.callback',
            'logout' => 'bps-sso.logout',
            'driver_login' => 'bps-sso.driver.login',
            'driver_callback' => 'bps-sso.driver.callback',
            'driver_logout' => 'bps-sso.driver.logout',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect Setelah Login Berhasil
    |--------------------------------------------------------------------------
    */
    'redirect_after_login' => env('BPS_SSO_REDIRECT_AFTER_LOGIN', '/home'),
    'use_intended_url' => true,

    /*
    |--------------------------------------------------------------------------
    | Sinkronisasi Pengguna (User Resolution & Mapping)
    |--------------------------------------------------------------------------
    */
    'user' => [
        'model' => env('BPS_SSO_USER_MODEL', 'App\\Models\\User'),
        'lookup_column' => env('BPS_SSO_USER_LOOKUP_COLUMN', 'email'),
        'lookup_claim' => env('BPS_SSO_USER_LOOKUP_CLAIM', 'email'),
        'create_if_not_exists' => env('BPS_SSO_AUTO_CREATE_USER', true),
        'update_on_login' => env('BPS_SSO_UPDATE_USER_ON_LOGIN', true),
        'columns' => [
            'name' => 'name',
            'email' => 'email',
            // 'username' => 'preferred_username',
            // 'nip' => 'nip',
            // 'nip_baru' => 'nip_baru',
            // 'kode_kabkot' => 'satker', // Otomatis diisi untuk Majapahit (misal: 3514)
            // 'kode_satker' => 'satker',
        ],
        'generate_random_password' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | REST API Data Pegawai (Client Credentials Grant)
    |--------------------------------------------------------------------------
    */
    'api' => [
        'token_cache_ttl' => 3000,
        'cache_key' => 'bps_sso_client_credentials_token',
        'timeout' => 15,
        'verify_ssl' => env('BPS_SSO_VERIFY_SSL', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Session Storage Keys
    |--------------------------------------------------------------------------
    */
    'session' => [
        'state' => 'bps_sso_oauth_state',
        'access_token' => 'bps_sso_access_token',
        'id_token' => 'bps_sso_id_token',
        'refresh_token' => 'bps_sso_refresh_token',
        'user_info' => 'bps_sso_user_info',
    ],
];
