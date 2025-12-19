# WA Gateway Registry Sync (Token/API Key)

Dokumen ini menjelaskan perubahan di sisi **wa-gateway** untuk menyediakan endpoint registry
yang bisa dipakai aplikasi eksternal untuk sinkronisasi `token/apiKey`.

## Ringkasan

- Registry token disimpan di: `wa_credentials/device-registry.json`
- Endpoint baru (aman): `GET /admin/device-registry`
- Auth opsi:
  - Bearer token (`REGISTRY_TOKEN`)
  - atau Basic auth (`REGISTRY_USER` + `REGISTRY_PASS`)

## 1) Pastikan file registry tersedia

File registry biasanya otomatis dibuat setelah device/session dibuat.
Lokasi default:

```
wa_credentials/device-registry.json
```

Contoh isi:

```
[
  {
    "token": "generated-token",
    "apiKey": "generated-token",
    "sessionId": "62812xxxx",
    "createdAt": "2025-12-19T05:41:40.081Z"
  }
]
```

## 2) Atur autentikasi endpoint registry (disarankan)

Pilih salah satu cara:

### Opsi A: Bearer Token

Tambahkan di file `.env` wa-gateway:

```
REGISTRY_TOKEN=token_rahasia
```

### Opsi B: Basic Auth

Tambahkan di file `.env` wa-gateway:

```
REGISTRY_USER=admin
REGISTRY_PASS=secret
```

> Jika **tidak** diisi, endpoint registry akan **terbuka** (tidak disarankan).

## 3) Restart wa-gateway

Setelah mengubah `.env`, restart service:

```
pm2 restart wa-gateway
```

Atau gunakan cara restart yang sesuai dengan server Anda.

## 4) Uji endpoint registry

### Bearer Token

```
curl -H "Authorization: Bearer token_rahasia" \
  http://localhost:5001/admin/device-registry
```

### Basic Auth

```
curl -u admin:secret \
  http://localhost:5001/admin/device-registry
```

Jika sukses, server mengembalikan JSON array dari registry.

## 5) Integrasi di aplikasi eksternal (contoh umum)

Aplikasi eksternal cukup membaca endpoint ini dan mengambil:

- `token` atau `apiKey`
- berdasarkan `sessionId` (jika ada banyak device)

Kemudian menyinkronkan ke konfigurasi aplikasi.

## Catatan Keamanan

- Jangan expose endpoint ini tanpa auth di server publik.
- Jika perlu, batasi akses via firewall atau VPN.

## Troubleshooting

- **401 Unauthorized**: cek token/basic auth di `.env`
- **Kosong**: pastikan device sudah dibuat dan registry file terisi
- **Tidak update**: restart wa-gateway setelah perubahan `.env`

## Ekspor token ke .env (otomasi)

Jika ada aplikasi lain yang membaca token lewat environment variable (mis. `WA_GATEWAY_TOKEN`), ambil nilai dari `wa_credentials/device-registry.json` lalu tulis ke `.env`. Berikut dua skenario umum.

### Opsi 1: Satu VPS, beda folder

Contoh struktur: wa-gateway di `/var/www/wa`, aplikasi lain (mis. `beta.watumalang.online`) dengan `.env` di `/var/www/beta/.env`.

Script (`sync-token-env.sh`):
```bash
#!/usr/bin/env bash
set -euo pipefail

WA_DIR="/var/www/wa"           # folder wa-gateway (punya wa_credentials/)
BETA_ENV="/var/www/beta/.env"  # lokasi .env aplikasi lain
SESSION_ID=""                  # isi jika mau pilih session spesifik, kosongkan untuk entry pertama

REG="$WA_DIR/wa_credentials/device-registry.json"
token=$(node - <<'NODE'
const fs = require('fs');
const path = process.env.REG_PATH;
const sessionId = process.env.SESSION_ID || "";
const list = JSON.parse(fs.readFileSync(path, 'utf8'));
const pick = sessionId
  ? (list.find(r => r.sessionId === sessionId) || {})
  : (Array.isArray(list) ? list[0] || {} : {});
const token = pick.token || pick.apiKey || "";
if (!token) throw new Error("Token not found");
console.log(token);
NODE
REG_PATH="$REG" SESSION_ID="$SESSION_ID")

tmp=$(mktemp)
grep -v '^WA_GATEWAY_TOKEN=' "$BETA_ENV" 2>/dev/null || true >"$tmp"
echo "WA_GATEWAY_TOKEN=$token" >>"$tmp"
mv "$tmp" "$BETA_ENV"
echo "Updated $BETA_ENV with WA_GATEWAY_TOKEN=$token"
```

Cara pakai:
1) Simpan file, beri izin: `chmod +x sync-token-env.sh`
2) Jalankan: `./sync-token-env.sh`
3) Jika perlu pilih session tertentu, set `SESSION_ID="6285xxxxxxx"` di script.

Contoh kasus: Anda baru mengganti `apiKey` untuk session `6285158663803` di `session-config.json`; jalankan script ini agar `.env` aplikasi lain ikut terisi token baru.

### Opsi 2: Dua VPS (wa.watumalang.online → beta.watumalang.online)

Ambil token via SSH dari host wa-gateway, lalu tulis ke `.env` di host aplikasi lain.

Script (`sync-token-remote.sh`):
```bash
#!/usr/bin/env bash
set -euo pipefail

WA_HOST="deploy@wa.watumalang.online"     # host wa-gateway
BETA_HOST="deploy@beta.watumalang.online" # host aplikasi lain
WA_DIR="/var/www/wa"
BETA_ENV="/var/www/beta/.env"
SESSION_ID="" # isi jika mau session spesifik

token=$(ssh "$WA_HOST" "REG='$WA_DIR/wa_credentials/device-registry.json'; node -e \"
const fs=require('fs');
const list=JSON.parse(fs.readFileSync(process.env.REG,'utf8'));
const sid='${SESSION_ID}';
const pick=sid?(list.find(r=>r.sessionId===sid)||{}):(Array.isArray(list)?list[0]||{}:{});
const t=pick.token||pick.apiKey||'';
if(!t) throw new Error('Token not found');
console.log(t);
\"")

ssh "$BETA_HOST" "tmp=\$(mktemp); \
  grep -v '^WA_GATEWAY_TOKEN=' '$BETA_ENV' 2>/dev/null || true >\$tmp; \
  echo 'WA_GATEWAY_TOKEN=$token' >>\$tmp; \
  mv \$tmp '$BETA_ENV'; \
  echo 'Updated $BETA_ENV'"
```

Cara pakai:
1) Pastikan SSH key-based login antara kedua host sudah berfungsi untuk user yang sama.
2) Simpan file, beri izin: `chmod +x sync-token-remote.sh`
3) Jalankan dari mesin yang bisa SSH ke kedua host: `./sync-token-remote.sh`
4) Sesuaikan `WA_HOST`, `BETA_HOST`, `WA_DIR`, `BETA_ENV`, dan opsional `SESSION_ID` sebelum menjalankan.

Contoh kasus: wa-gateway berjalan di `wa.watumalang.online`, aplikasi pembaca token ada di `beta.watumalang.online`; jalankan script ini setelah sinkronisasi registry agar `.env` di host beta terisi token terbaru.
