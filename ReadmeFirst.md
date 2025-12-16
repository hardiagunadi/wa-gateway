# WA Gateway – Catatan Penting Operasional

## 1) Start/Stop Gateway (API WhatsApp)
- Pastikan `.env` di root sudah berisi `KEY` (API key) dan `PORT=5001`.
- Jalankan API via panel (menu NPM Server) atau manual:
  ```bash
  cd /home/wa-gateway
  node --import node_modules/tsx/dist/loader.mjs src/index.ts
  ```
- Uji cepat:
  ```bash
  curl -s "http://localhost:5001/health"
  ```

## 2) Generate / Ganti API Key
- Buat key baru:
  ```bash
  openssl rand -hex 16
  ```
- Set di:
  - `docker-compose.yaml` (ENV `KEY`) jika pakai Docker.
  - `/home/wa-gateway/.env` (KEY) untuk mode Node langsung.
  - `panel/.env` (`WA_GATEWAY_KEY=`) agar panel memanggil API dengan key yang sama.
- Restart gateway (Stop/Start dari panel atau restart proses Node/Docker).

## 3) Panel Laravel
- Lokasi: `/home/wa-gateway/panel`
- Login awal: `admin / admin` → segera ganti di menu **Profil**.
- Jalankan panel (dev server):
  ```bash
  cd /home/wa-gateway/panel
  php artisan serve --host 0.0.0.0 --port 8000
  ```
  Akses: `http://localhost:8000/login`
- Start/Stop NPM Server dari dashboard mengontrol proses Node (gateway) dengan perintah:
  ```
  node --import /home/wa-gateway/node_modules/tsx/dist/loader.mjs /home/wa-gateway/src/index.ts
  ```
- Log proses Node (saat start via panel): `panel/storage/logs/npm-server.log`

## 4) Run via Docker (opsional)
```bash
cd /home/wa-gateway
docker compose up -d
```
Pastikan `KEY` di `docker-compose.yaml` sama dengan yang dipakai panel.

## 5) Workflow Operasional Ringkas
1. Tentukan/ubah KEY (lihat bagian 2), sinkronkan ke panel dan gateway.
2. Start gateway (panel NPM Server atau perintah manual / Docker).
3. Cek health: `http://localhost:5001/health`.
4. Login panel → ganti password admin di Profil → kelola sesi/QR dari dashboard.
5. Pantau log jika ada kendala:
   - Gateway Node (via panel start): `panel/storage/logs/npm-server.log`
   - Docker mode: `docker compose logs -f`

## 6) Installer Otomatis (Server Baru)
Script ini akan memasang dependensi (Docker + Compose, Node 20, PHP 8.4 + ekstensi, Composer), menyiapkan `.env` gateway/panel, menginstal dependensi Node/Composer, menjalankan migrasi, dan build panel. Jika suatu komponen sudah ada, akan dilewati.

### Menjalankan
```bash
sudo bash scripts/install-wa-gateway.sh
```

### Sesudah Installer
- Panel: `cd /home/wa-gateway/panel && php artisan serve --host 0.0.0.0 --port 8000`
  - Login awal: `admin / admin`, segera ganti di menu Profil.
- Gateway: `cd /home/wa-gateway && node --import node_modules/tsx/dist/loader.mjs src/index.ts`
  - Atau kontrol via tombol Start/Stop di panel.
