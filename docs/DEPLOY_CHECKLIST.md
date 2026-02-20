# Checklist Deployment wa-gateway + Panel

Panduan ringkas supaya setup production (mis. `/var/www/wa-gateway`) tidak ada langkah yang terlewat.

## 1) Persiapan server

- Pastikan **Node.js ≥ 20**, **PHP ≥ 8.4** + ekstensi Laravel, **Composer**, dan **PM2** terpasang.
- Buat user/service yang punya akses tulis ke `wa_credentials`, `media`, dan file log.
- Jalankan installer otomatis jika tersedia: `bash scripts/install-wa-gateway.sh`

## 2) Path & environment

Salin repo ke lokasi final (mis. `/var/www/wa-gateway`).

### Panel (Laravel) – `panel/.env`

| Variabel | Nilai contoh | Keterangan |
|---|---|---|
| `WA_GATEWAY_BASE` | `http://localhost:5001` | URL/domain gateway Node |
| `WA_GATEWAY_KEY` | `abc123...` | Master key gateway (wajib di prod) |
| `ADMIN_EMAIL` | `admin@domain.com` | Email admin awal |
| `ADMIN_PASSWORD` | `password_kuat` | Password admin awal |
| `PM2_APP_NAME` | `wa-gateway` | Nama proses di PM2 |
| `PM2_CONFIG_FILE` | `/var/www/wa-gateway/ecosystem.config.js` | Path ecosystem PM2 |
| `PM2_WORKDIR` | `/var/www/wa-gateway` | Direktori kerja PM2 |
| `SESSION_CONFIG_PATH` | `/var/www/wa-gateway/wa_credentials/session-config.json` | Path config session |
| `GATEWAY_NODE_ENV` | `/var/www/wa-gateway/.env` | Path .env Node (opsional) |
| `PASSWORD_RESET_SESSIONS` | `session1,session2` | Device yang boleh kirim reset password |

### Gateway (Node.js) – `.env` di root repo

| Variabel | Keterangan |
|---|---|
| `KEY` | Master key API (wajib di production) |
| `PORT` | Port server (default: 5001) |
| `WEBHOOK_BASE_URL` | Default webhook URL (opsional) |

Jalankan `php artisan config:clear` setelah mengubah env panel.

## 3) Permissions & struktur

- Direktori yang perlu bisa ditulis oleh user service:
  - `/var/www/wa-gateway/wa_credentials` — config & session
  - `/var/www/wa-gateway/media` — file media pesan
  - `/var/www/wa-gateway/logs` — log PM2 output/error
  - `panel/storage` dan `panel/bootstrap/cache` — Laravel
- Database SQLite panel harus bisa ditulis web user (`www-data`):
  ```bash
  chown www-data:www-data panel/database/database.sqlite
  chmod 664 panel/database/database.sqlite
  ```
- **Non-root-friendly**: panel menampilkan peringatan jika folder kunci tidak writable.

## 4) Start layanan

### Install dependencies
```bash
# Node.js dependencies
cd /var/www/wa-gateway && npm ci

# PHP dependencies
cd /var/www/wa-gateway/panel && composer install --no-dev --optimize-autoloader

# Migrasi database
php artisan migrate --force
```

### Jalankan server Node via PM2
```bash
cd /var/www/wa-gateway
pm2 start ecosystem.config.js      # pertama kali / jika belum terdaftar
pm2 restart wa-gateway             # restart jika sudah terdaftar
pm2 save                           # simpan list agar auto-start setelah reboot
pm2 startup systemd                # setup auto-start (ikuti instruksi yang muncul)
```

Atau gunakan tombol **Start / Restart / Stop** langsung dari dashboard panel.

### Jalankan panel
```bash
# Development (artisan serve)
cd panel && php artisan serve --host 0.0.0.0 --port 8000

# Production: arahkan Nginx/Apache ke panel/public/
```

## 5) Anti-Spam (opsional)

Konfigurasi anti-spam tersedia di **Device Settings** setiap device di halaman Device Management.

| Pengaturan | Default | Keterangan |
|---|---|---|
| Anti-Spam Enabled | Off | On/Off per device |
| Max Pesan/Menit | 20 | Rate limit per sesi |
| Delay antar Pesan | 1000 ms | Jeda minimum antar pengiriman |
| Interval Penerima | 0 detik | Cooldown kirim ke nomor yang sama (0 = nonaktif) |

Jika rate limit tercapai, pesan **tidak ditolak** — melainkan **diantrekan** dan dikirim begitu slot tersedia.

## 6) Pengamanan

- Gunakan HTTPS di panel dan gateway.
- Batasi akses file `wa_credentials` dan `.env` hanya untuk user service.
- Set `KEY` gateway dan `WA_GATEWAY_KEY` panel yang kuat dan sama.

## 6.1) Reverse proxy (Apache, path-based)

Jika ingin panel dan API di domain yang sama:

```apache
ProxyPreserveHost On
RequestHeader set X-Forwarded-Proto "https"

ProxyPass /gateway/ http://127.0.0.1:5001/
ProxyPassReverse /gateway/ http://127.0.0.1:5001/
```

Set `WA_GATEWAY_BASE=https://domain/gateway`. Modul yang harus aktif: `proxy`, `proxy_http`, `headers`.

## 7) Uji fungsi (smoke test)

- Buka dashboard panel: cek **API Status** hijau dan **WA Gateway Server (PM2)** berstatus Online.
- Tambah device (QR/pairing) → pastikan status Connected.
- Buka **Device Settings** → konfigurasi webhook dan anti-spam → Save.
- Kirim pesan test via modal **Test Kirim WA** untuk 1 device.
- Buka **Group Finder** dan **Message Status Log** pastikan berfungsi.
- Akses dokumentasi via browser: `http://domain:port/docs/`

## 8) Monitoring & maintenance

- Log PM2: `pm2 logs wa-gateway` atau lihat di `logs/pm2-out.log` dan `logs/pm2-error.log`.
- Log Laravel: `panel/storage/logs/laravel.log`.
- Jika mengubah path atau env, jalankan `php artisan config:clear` dan restart layanan.
- Backup `wa_credentials` secara berkala (session-config, jadwal, dsb.).
- PM2 tersimpan secara otomatis; untuk restore setelah reboot: `pm2 resurrect`.
