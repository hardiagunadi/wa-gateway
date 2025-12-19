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
