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
  - Admin seed (wajib di production, agar tidak pakai default admin):
    - `ADMIN_EMAIL=admin@domain.com`
    - `ADMIN_PASSWORD=password_kuat`
    - `ADMIN_NAME=admin` (opsional)
  - `NPM_SERVER_WORKDIR=/var/www/wa-gateway` (opsional, override default)
  - `NPM_SERVER_COMMAND=` (opsional, override command node)
  - `SESSION_CONFIG_PATH=/var/www/wa_credentials/session-config.json` (default: `dirname(base_path())/wa_credentials/session-config.json`)
  - `GATEWAY_NODE_ENV=/var/www/wa-gateway/.env` (opsional jika file .env node ada di lokasi lain)
- Set env untuk server Node (`KEY`, `PORT`, dll.) di `.env` Node sesuai kebutuhan. **KEY wajib di production.**
- Jalankan `php artisan config:clear` setelah mengubah env.

## 3) Permissions & struktur
- Pastikan direktori:
  - `/var/www/wa-gateway/wa_credentials` (config)
  - `/var/www/wa-gateway/media` (media pesan)
  - file log Node (`npm.log` dari status panel)
  bisa ditulis oleh user service.
- Jika memindahkan path ke luar `/home/wa-gateway`, perbarui semua env path di atas agar menunjuk lokasi baru.
- **Non-root-friendly**: panel akan menampilkan peringatan jika folder/file kunci tidak bisa ditulis oleh user yang sedang berjalan. Jika muncul:
  - `wa_credentials`/`session-config.json` → `chown -R <user>:<group> /var/www/wa_credentials` lalu pastikan `chmod 750/640` sesuai kebutuhan.
  - `media` → pastikan user service bisa menulis file media.

## 4) Start layanan
- Install dependencies: `npm install` (atau pnpm/yarn) di workdir Node; `composer install` di panel.
- Build/siapkan Node server command:
  - Default: `node --import node_modules/tsx/dist/loader.mjs src/index.ts`
  - Override via `NPM_SERVER_COMMAND` jika perlu.
- Jalankan Node server via pm2/systemd atau tombol Start di panel (butuh env benar).
- Pastikan PHP-FPM/nginx sudah mengarah ke folder panel (public) dengan HTTPS.

## 5) Pengamanan
- Gunakan HTTPS di panel dan gateway.
- Batasi akses file `wa_credentials` dan `.env` hanya untuk user service.

## 6) Uji fungsi (smoke test)
- Buka panel: cek API status hijau, NPM server berjalan.
- Tambah device (QR/pairing) → pastikan status Connected dan webhook config tersimpan.
- Kirim pesan test via modal settings (Test Kirim WA) untuk 1 device.
- Buka Group Finder dan Message Status Log untuk memastikan endpoint session bekerja.

## 7) Monitoring & maintenance
- Pantau log Node (path di panel), log Laravel, dan log nginx/PHP-FPM.
- Jika mengubah path atau env, ulangi `php artisan config:clear` dan restart layanan.
- Backup `wa_credentials` secara berkala (session-config, jadwal, dsb.).
