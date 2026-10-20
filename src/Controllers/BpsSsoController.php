<?php

namespace Bps\Sso\Controllers;

use Bps\Sso\Exceptions\InvalidStateException;
use Bps\Sso\Exceptions\TokenExchangeException;
use Bps\Sso\Facades\BpsSso;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class BpsSsoController extends Controller
{
    /**
     * Redirect pengguna ke halaman login Keycloak SSO (SSO BPS / SSO Majapahit).
     */
    public function login(Request $request, ?string $driver = null): Response
    {
        $driverName = $driver ?? $request->query('driver');
        $sso = BpsSso::driver($driverName);

        if (Auth::check()) {
            if ($request->wantsJson()) {
                return response()->json([
                    'message' => 'Pengguna sudah login.',
                    'user' => Auth::user(),
                ]);
            }

            $redirectAfterLogin = config('sso-bps.redirect_after_login', '/home');
            return redirect($redirectAfterLogin);
        }

        $loginUrl = $sso->getLoginUrl();

        if ($request->wantsJson()) {
            return response()->json([
                'login_url' => $loginUrl,
                'driver' => $sso->getName(),
            ]);
        }

        return redirect()->away($loginUrl);
    }

    /**
     * Menerima callback dari Keycloak, menukar code dengan token,
     * mengautentikasi pengguna, dan mengembalikan response Web Redirect atau JSON API.
     */
    public function callback(Request $request, ?string $driver = null): Response
    {
        $driverName = $driver ?? $request->query('driver');
        $sso = BpsSso::driver($driverName);

        try {
            $result = $sso->handleCallback($request);

            if (!$result['user']) {
                Log::warning("[BPS SSO] Login berhasil di Keycloak ({$sso->getName()}), namun User tidak dapat ditemukan atau dibuat di database aplikasi.");

                if ($request->wantsJson()) {
                    return response()->json([
                        'message' => 'Akun SSO Anda terdaftar tetapi belum memiliki akses pada aplikasi ini.',
                    ], 403);
                }

                return redirect()->route('login')->with('error', 'Akun SSO Anda terdaftar tetapi belum memiliki akses pada aplikasi ini.');
            }

            if ($request->wantsJson()) {
                return response()->json([
                    'message' => "Login SSO ({$sso->getName()}) berhasil.",
                    'user' => $result['user'],
                    'tokens' => $result['tokens'],
                    'userInfo' => $result['userInfo'],
                    'driver' => $sso->getName(),
                ]);
            }

            $useIntended = (bool) config('sso-bps.use_intended_url', true);
            $fallbackUrl = config('sso-bps.redirect_after_login', '/home');

            if ($useIntended) {
                return redirect()->intended($fallbackUrl);
            }

            return redirect($fallbackUrl);
        } catch (InvalidStateException $e) {
            Log::error("[BPS SSO] Invalid State Exception ({$sso->getName()}): " . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['message' => $e->getMessage()], 400);
            }

            return redirect('/')->with('error', $e->getMessage());
        } catch (TokenExchangeException $e) {
            Log::error("[BPS SSO] Token Exchange Exception ({$sso->getName()}): " . $e->getMessage(), ['response' => $e->getResponseBody()]);

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Gagal memverifikasi login SSO BPS.'], 400);
            }

            return redirect('/')->with('error', 'Gagal memverifikasi login SSO BPS. Silakan coba kembali.');
        } catch (Throwable $e) {
            Log::error("[BPS SSO] Authentication Error ({$sso->getName()}): " . $e->getMessage());

            if ($request->wantsJson()) {
                return response()->json(['message' => 'Terjadi kesalahan saat proses login SSO: ' . $e->getMessage()], 500);
            }

            return redirect('/')->with('error', 'Terjadi kesalahan saat proses login SSO: ' . $e->getMessage());
        }
    }

    /**
     * Logout pengguna dari aplikasi dan Keycloak SSO (Web Redirect atau JSON).
     */
    public function logout(Request $request, ?string $driver = null): Response
    {
        $driverName = $driver ?? $request->query('driver') ?? session('bps_sso_active_driver');
        $sso = BpsSso::driver($driverName);

        $redirectUrl = $sso->logout();

        if ($request->wantsJson()) {
            return response()->json([
                'message' => 'Logout berhasil.',
                'logout_url' => $redirectUrl,
                'driver' => $sso->getName(),
            ]);
        }

        return redirect()->away($redirectUrl);
    }
}
