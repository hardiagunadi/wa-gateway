# Headless Multi Session WhatsApp Gateway

A headless multi-session WhatsApp gateway with multi-device support, runnable directly with Node.js.

- Multi-device support
- Multi-session / multiple phone numbers
- Send text messages, images, videos, and documents
- Webhook integration

---

## ⚠️ Prerequisites

- Node.js 20+
- npm (ships with Node.js)
- A WhatsApp device to pair via QR

---

## Installation & Running (Node.js)

```bash
git clone https://github.com/hardiagunadi/wa-gateway.git
cd wa-gateway
npm install

# Create .env (at minimum set KEY; PORT defaults to 5001)
KEY=$(openssl rand -hex 16)
cat > .env <<EOF
KEY=$KEY
PORT=5001
WEBHOOK_BASE_URL=
EOF

# Start the gateway (or use npm run dev for watch mode)
npm run start
```

Open the browser to scan the QR code from your WhatsApp device:

```
http://localhost:5001/session/start?session=mysession
```

Replace `mysession` with your desired session name.

Send your first text message:

```
http://localhost:5001/message/send-text?session=mysession&to=628123456789&text=Hello
```

---

## API Reference

All API endpoints remain the same as the NodeJS version. Here's a quick reference:

### Create New Session

```bash
GET /session/start?session=NEW_SESSION_NAME
```

or

```bash
POST /session/start
{
  "session": "NEW_SESSION_NAME"
}
```

### Send Text Message

```bash
POST /message/send-text
```

Body fields:

| Field    | Type    | Required | Description                             |
| -------- | ------- | -------- | --------------------------------------- |
| session  | string  | Yes      | The session name you created            |
| to       | string  | Yes      | Target phone number (e.g. 628123456789) |
| text     | string  | Yes      | The text message                        |
| is_group | boolean | No       | True if target is a group               |

### Send Image

```bash
POST /message/send-image
```

Body includes all of the above plus `image_url`.

### Send Document

```bash
POST /message/send-document
```

Body includes:

- `document_url`
- `document_name`

### Send Video

```bash
POST /message/send-video
```

Body fields:

| Field     | Type    | Required | Description                             |
| --------- | ------- | -------- | --------------------------------------- |
| session   | string  | Yes      | The session name you created            |
| to        | string  | Yes      | Target phone number (e.g. 628123456789) |
| text      | string  | No       | Caption for the video (optional)        |
| video_url | string  | Yes      | URL of the video file                   |
| is_group  | boolean | No       | True if target is a group               |

### Delete Session

```bash
GET /session/logout?session=SESSION_NAME
```

---

## Webhook Setup

To receive real-time events, set your webhook URL using the environment variable:

```env
WEBHOOK_BASE_URL="http://yourdomain.com/webhook"
```

Example webhook endpoints:

- Session: `POST /webhook/session`
- Message: `POST /webhook/message`

---

## Access Media Files

Media files are stored inside the local `./media` directory. You can access them via:

```
http://localhost:5001/media/FILE_NAME
```

---

## Upgrading

To update to the latest version:

```bash
git pull
npm install
```
