# wa-gateway-Compatible API (wa-gateway)

Dokumen ini menjelaskan **endpoint kompatibilitas wa-gateway** yang disediakan oleh `wa-gateway`.

Catatan penting:
- `wa-gateway` **tidak memerlukan** `{$secret_key}` pada header `Authorization`. Jika klien mengirim `Authorization: token.secret`, server hanya mengambil bagian `token`.
- Selain `Authorization`, token juga bisa dikirim via query `?token=...`.
- Jika `KEY` gateway di-set (env `KEY`), maka semua request tetap wajib membawa header/query `key=<KEY>` seperti endpoint lain di `wa-gateway`.

## Konsep Mapping

- **Device token (wa-gateway)** → dipetakan ke **sessionId (wa-gateway)** lewat registry `wa_credentials/device-registry.json`.
- Semua aksi “device” dan “send-*” memilih session berdasarkan token.

## Authentication

Pilih salah satu:
- Header: `Authorization: <token>`
- Header: `Authorization: Bearer <token>`
- Header: `Authorization: <token>.<secret_key>` (secret diabaikan)
- Query: `?token=<token>`

Jika gateway memakai master key:
- Header: `key: <KEY>` atau query `?key=<KEY>`

---

## Device

### POST `/api/device/create`
Membuat device + session dan mengembalikan QR (jika perlu scan).

Content-Type: `application/x-www-form-urlencoded`

Fields:
- `name` (optional)
- `phone` (optional) → dipakai sebagai `sessionId` default (kalau tidak ada, auto dibuat)
- `product`, `bank`, `periode` (diabaikan, hanya untuk kompatibilitas)

Response:
```json
{
  "status": true,
  "message": "create device successfully",
  "data": {
    "device_name": "My Device",
    "device": "62812xxxx",
    "token": "generated-token",
    "qr": "qr-string-or-null"
  }
}
```

### POST `/api/device/create-code`
Membuat device + session dan mengembalikan pairing code (tanpa QR).

Content-Type: `application/x-www-form-urlencoded`

Fields:
- `phone` (required) → nomor WA (sekaligus `sessionId`)
- `name` (optional)

Response:
```json
{
  "status": true,
  "message": "pairing code generated",
  "data": {
    "device_name": "My Device",
    "device": "62812xxxx",
    "token": "generated-token",
    "pairing_code": "123-456"
  }
}
```

### GET `/api/device/info`
Mengambil status device berdasar token.

Response:
```json
{
  "status": "success",
  "message": "Success",
  "data": [
    {
      "phone": "62812...@s.whatsapp.net",
      "device": "sessionId",
      "status": "online|offline",
      "webhook_url": "https://..."
    }
  ]
}
```

### GET `/api/device/scan`
Memulai session dan mengembalikan QR.

### POST `/api/device/disconnect`
Logout session (tanpa menghapus registry device).

### POST `/api/device/delete`
Logout session + hapus registry device.

---

## Messaging

### GET/POST `/api/send-message`
Mengirim text message.

GET contoh:
`/api/send-message?token=...&phone=62812xxx&message=halo&isGroup=false`

POST (form) fields:
- `phone` (required)
- `message` (required)
- `isGroup` (optional, `true|false`)
- `ref_id` (optional)

Response:
```json
{ "status": true, "message": "Success", "data": { "phone":"...", "message":"...", "ref_id":null, "id":"..." } }
```

### POST `/api/send-image`
Fields (form):
- `phone` (required)
- `image` atau `url` (required) → URL media
- `caption` (optional)
- `isGroup` (optional)

### POST `/api/send-video`
Fields (form):
- `phone` (required)
- `video` atau `url` (required)
- `caption` / `message` (optional)
- `isGroup` (optional)

### POST `/api/send-document`
Fields (form):
- `phone` (required)
- `document` atau `url` (required)
- `filename` (optional, default `document.pdf`)
- `caption` / `message` (optional)
- `isGroup` (optional)

### POST `/api/send-audio`
Fields (form):
- `phone` (required)
- `audio` atau `url` (required)
- `ptt` / `asVoiceNote` (optional)
- `isGroup` (optional)

---

## V2 Endpoints

`wa-gateway` menyediakan endpoint **V2** dengan format payload JSON.

Catatan:
- `Authorization: {token}.{secret_key}` diterima, tetapi **secret_key diabaikan**. Anda cukup kirim `Authorization: {token}`.
- Semua endpoint V2 menggunakan device/session berdasarkan token (1 token → 1 device/session).
- Webhook bawaan tetap memakai payload standar `wa-gateway`, tetapi tersedia opsi **compat** untuk beberapa platform.

### Webhook compat lintasku/topsetting

Jika Anda ingin menerima webhook masuk dengan format sederhana seperti yang ditampilkan dashboard `https://lintasku.topsetting.com/billing/webhook.php` (field `message`, `receiver`, `message status`, `quota`), tambahkan konfigurasi berikut di `wa_credentials/session-config.json` untuk session terkait:

```json
{
  "<sessionId>": {
    "webhookBaseUrl": "https://server-anda.com/webhook",
    "lintaskuCompatWebhookUrl": "https://lintasku.topsetting.com/billing/webhook.php"
  }
}
```

Setiap pesan masuk akan tetap dikirim ke `webhookBaseUrl` dengan payload standar, **dan tambahan POST** ke `lintaskuCompatWebhookUrl` dengan payload:

```json
{
  "message": "teks pesan atau caption",
  "receiver": "628xxx@s.whatsapp.net",   // JID pengirim WhatsApp
  "message_status": "received",
  "quota": null,
  "session": "session-id-device",
  "sender": "628xxx@s.whatsapp.net",
  "isGroup": false
}
```

Opsi ini tidak mengubah atau menonaktifkan webhook lain yang sudah berjalan.

### POST `/api/v2/send-message`
Content-Type: `application/json`

```json
{
  "data": [
    { "phone": "62812xxx", "message": "Hello", "isGroup": false, "ref_id": "trx-1" }
  ]
}
```

### POST `/api/v2/send-image|send-video|send-document|send-audio`
Contoh `send-image`:

```json
{
  "data": [
    { "phone": "62812xxx", "image": "https://...", "caption": "caption", "isGroup": false }
  ]
}
```

Field yang dipakai:
- `image|video|document|audio` atau `url`
- `caption` (opsional), `isGroup` (opsional), `ref_id` (opsional)

### POST `/api/v2/send-link`
```json
{
  "data": [
    { "phone": "62812xxx", "message": { "text": "Info", "link": "https://wa-gateway.example" } }
  ]
}
```

### POST `/api/v2/send-list`
Best-effort mapping ke WhatsApp list message (Baileys).
```json
{
  "data": [
    {
      "phone": "62812xxx",
      "message": {
        "title": "Menu",
        "description": "Pilih salah satu",
        "buttonText": "Pilih",
        "footer": "Footer",
        "lists": [
          { "title": "Item 1", "description": "Desc 1" }
        ]
      }
    }
  ]
}
```

### POST `/api/v2/send-location`
```json
{
  "data": [
    {
      "phone": "62812xxx",
      "message": { "name": "Place", "address": "Street", "latitude": -7.36, "longitude": 109.91 }
    }
  ]
}
```

### Group endpoints `/api/v2/group/*`
wa-gateway “group” berbeda dengan WhatsApp group. Di `wa-gateway` endpoint ini menganggap `group_id` adalah **WhatsApp group id/JID**.
- `GET /api/v2/group/text?group_id=12345-67890@g.us&message=halo`
- `POST /api/v2/group/text` body JSON `{ "data": [{ "group_id": "...", "message": "..." }] }`
- `POST /api/v2/group/image|video|audio|document`

### Schedule `/api/v2/schedule`
Menyimpan jadwal ke `wa_credentials/wa-gateway-schedules.json` dan diproses oleh scheduler internal.

```json
{
  "data": [
    { "category": "text", "phone": "62812xxx", "scheduled_at": "2025-12-17 20:00:00", "text": "Hallo" }
  ]
}
```

Endpoints:
- `POST /api/v2/schedule`
- `PUT /api/v2/schedule/{schedule_id}`
- `DELETE /api/v2/delete-schedule?id=id1,id2`

### Auto Reply `/api/v2/autoreply`
Rules disimpan per-token dan akan dipakai saat incoming message **jika webhook auto-reply dimatikan**.

Endpoints:
- `POST /api/v2/autoreply` body `{ "keyword": "hello", "response": "hello too" }`
- `PUT /api/v2/autoreply/{id}`
- `DELETE /api/v2/autoreply/{id}`
- `GET /api/v2/autoreply/getData?keyword=hello`

### Contact `/api/v2/contact`
Menyimpan kontak per-token.

Endpoints:
- `POST /api/v2/contact`
- `POST /api/v2/contact/update`
- `GET /api/v2/contact?phone=...` (atau tanpa `phone` untuk list)

### Media delete `/api/v2/media/delete/{id}`
Menghapus file lokal di folder `./media` yang mengandung substring `{id}` di nama file.

---

## Catatan Keterbatasan

Dokumentasi wa-gateway mencakup banyak fitur lanjutan (reporting, reminder, upload media wa-gateway, blacklist global, agent, dll). Pada versi ini, endpoint tersebut akan mengembalikan HTTP `501 Not Implemented` dengan pesan yang jelas.
