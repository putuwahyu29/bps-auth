# Changelog

Semua perubahan pada paket **BPS Auth for Laravel** didokumentasikan dalam berkas ini.

Format mengacu pada [Keep a Changelog](https://keepachangelog.com/en/1.0.0/) dan [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-10-20

### Added
- Autentikasi Single Sign-On Keycloak OpenID Connect (SSO BPS dan SSO Majapahit).
- Dukungan driver ganda (`pusat` dan `majapahit`) via `.env` atau runtime.
- Ekstraksi NIP Lama (9 digit), NIP Baru (18 digit), kode satker wilayah, dan roles.
- Dukungan response JSON pada endpoint SSO untuk aplikasi mobile dan SPA.
- Helper verifikasi Bearer Token Keycloak (`userFromBearerToken`).
- Klien REST API Pegawai dengan Client Credentials dan cache token otomatis.
- Validasi CSRF state token dan Single Logout (RP-Initiated).
- Opsi whitelist domain email.
- Sinkronisasi pengguna otomatis dan kustomisasi resolver (`resolveUserUsing`).
- Komponen tombol login Blade `<x-bps-sso-button />` dan middleware rute `bps.sso`.
- Event hooks `BpsSsoAuthenticated` dan `BpsSsoLoggedOut`.
