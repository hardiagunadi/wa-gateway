# Checklist Deployment wa-gateway + Panel

Panduan ringkas supaya setup production (mis. `/var/www/wa-gateway`) tidak ada langkah yang terlewat.

## 1) Persiapan server
- Pastikan Node.js, npm/pnpm/yarn, PHP + ekstensi Laravel, Composer terpasang.
- Buat user/service yang punya akses tulis ke `wa_credentials`, `media`, dan file log.
- Jika pakai pm2/systemd, siapkan service untuk server Node dan PHP-FPM/nginx sesuai path baru.

## 2) Path & environment
- Salin repo ke lokasi final (mis. `/var/www/wa-gateway`).
  - Set env panel (Laravel) di `.env`:
    - `WA_GATEWAY_BASE=http://localhost:5001` (atau domain gateway)
    - `WA_GATEWAY_KEY=` (isi jika gateway pakai key)
    - `PASSWORD_RESET_SESSIONS=session1,session2` (opsional; device yang boleh kirim reset password)
  - `NPM_SERVER_WORKDIR=/var/www/wa-gateway` (opsional, override default)
  - `NPM_SERVER_COMMAND=` (opsional, override command node)
  - `SESSION_CONFIG_PATH=/var/www/wa_credentials/session-config.json` (default: `dirname(base_path())/wa_credentials/session-config.json`)
  - `DEVICE_REGISTRY_PATH=/var/www/wa_credentials/device-registry.json` (default: `dirname(base_path())/wa_credentials/device-registry.json`)
  - `GATEWAY_NODE_ENV=/var/www/wa-gateway/.env` (opsional jika file .env node ada di lokasi lain)
  - Registry auth (wajib di production):
    - `REGISTRY_USER=admin` dan `REGISTRY_PASS=secret`
  - Target sinkron token (Jadwal):
    - `JADWAL_ENV_PATH=/var/www/jadwal/.env` (lokasi .env target)
    - `JADWAL_ENV_KEY=WA_GATEWAY_TOKEN` (opsional; default WA_GATEWAY_TOKEN)
    - `JADWAL_ALLOWED_SESSIONS=session1,session2` (opsional; batasi device yang boleh kirim token)
- Set env untuk server Node (`KEY`, `PORT`, dll.) di `.env` Node sesuai kebutuhan.
- Jalankan `php artisan config:clear` setelah mengubah env.

## 3) Permissions & struktur
- Pastikan direktori:
  - `/var/www/wa-gateway/wa_credentials` (config & registry)
  - `/var/www/wa-gateway/media` (media pesan)
  - file log Node (`npm.log` dari status panel)
  bisa ditulis oleh user service.
- Jika memindahkan path ke luar `/home/wa-gateway`, perbarui semua env path di atas agar menunjuk lokasi baru.
- **Non-root-friendly**: panel akan menampilkan peringatan jika folder/file kunci tidak bisa ditulis oleh user yang sedang berjalan. Jika muncul:
  - `wa_credentials`/`session-config.json`/`device-registry.json` → `chown -R <user>:<group> /var/www/wa_credentials` lalu pastikan `chmod 750/640` sesuai kebutuhan.
  - `media` → pastikan user service bisa menulis file media.
  - `.env target` sinkron token (`JADWAL_ENV_PATH`) → pastikan file ada dan writable.

## 4) Start layanan
- Install dependencies: `npm install` (atau pnpm/yarn) di workdir Node; `composer install` di panel.
- Build/siapkan Node server command:
  - Default: `node --import node_modules/tsx/dist/loader.mjs src/index.ts`
  - Override via `NPM_SERVER_COMMAND` jika perlu.
- Jalankan Node server via pm2/systemd atau tombol Start di panel (butuh env benar).
- Pastikan PHP-FPM/nginx sudah mengarah ke folder panel (public) dengan HTTPS.

## 5) Pengamanan
- Amankan endpoint registry `/admin/device-registry` dengan token/basic auth.
- Gunakan HTTPS di panel dan gateway.
- Batasi akses file `wa_credentials` dan `.env` hanya untuk user service.
- Gunakan `JADWAL_ALLOWED_SESSIONS` untuk mencegah token salah kirim ke aplikasi lain.

## 6) Uji fungsi (smoke test)
- Buka panel: cek API status hijau, NPM server berjalan.
- Tambah device (QR/pairing) → pastikan status Connected dan webhook config tersimpan.
- Kirim pesan test via modal settings (Test Kirim WA) untuk 1 device.
- Buka Group Finder dan Message Status Log untuk memastikan endpoint session bekerja.
- Sync token: klik ikon kunci (Sync WA_GATEWAY_TOKEN) di Device Management, pilih target; verifikasi `.env` target terisi `WA_GATEWAY_TOKEN` yang benar.
- Cek `/admin/device-registry` dengan auth untuk memastikan registry bisa diambil aplikasi lain.

## 7) Monitoring & maintenance
- Pantau log Node (path di panel), log Laravel, dan log nginx/PHP-FPM.
- Jika mengubah path atau env, ulangi `php artisan config:clear` dan restart layanan.
- Backup `wa_credentials` secara berkala (registry, session-config, jadwal, dsb.).
