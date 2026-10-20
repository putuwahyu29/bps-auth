# Panduan Kontribusi

Terima kasih atas kontribusi Anda pada paket **BPS Auth for Laravel**.

---

## Alur Kontribusi (Pull Request)

1. **Fork Repositori**:
   Fork repositori ini ke akun GitHub Anda, lalu clone ke lokal.

2. **Buat Branch**:
   Gunakan nama branch yang deskriptif:
   ```bash
   git checkout -b feature/nama-fitur
   # atau
   git checkout -b fix/deskripsi-perbaikan
   ```

3. **Install Dependensi**:
   ```bash
   composer install
   ```

4. **Kode dan Pengujian**:
   - Ikuti standar PSR-12.
   - Sertakan unit test atau feature test pada direktori `tests/` untuk fitur atau perbaikan baru.

5. **Jalankan Pengujian**:
   Pastikan seluruh pengujian berhasil:
   ```bash
   composer test
   ```

6. **Commit**:
   Gunakan format commit konvensional:
   - `feat: Deskripsi fitur`
   - `fix: Deskripsi perbaikan`
   - `docs: Pembaruan dokumentasi`

7. **Kirim Pull Request**:
   Buka PR ke branch `main` dengan ringkasan perubahan yang jelas.

---

## Pedoman Kode

- Jangan menyimpan kredensial atau token nyata di dalam repositori maupun test.
- Utamakan fitur native Laravel (`Http`, `Cache`, `Session`) untuk meminimalkan dependensi eksternal.
- Pastikan kompatibel dengan PHP 8.1 - 8.4 dan Laravel 9.x - 12.x.
