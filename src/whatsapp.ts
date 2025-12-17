import axios from "axios";
import type { MessageReceived, MessageUpdated } from "wa-multi-session";
import { SQLiteAdapter, Whatsapp } from "wa-multi-session";
import {
  handleWebhookAudioMessage,
  handleWebhookDocumentMessage,
  handleWebhookImageMessage,
  handleWebhookVideoMessage,
} from "./webhooks/media";
import { getSessionWebhookConfig } from "./session-config";
import { setMessageStatus } from "./status-store";

const createWebhookClient = (apiKey?: string) =>
  axios.create({
    headers: apiKey ? { key: apiKey } : {},
  });

const normalizeWebhookUrl = (url?: string) => (url || "").trim().replace(/\/$/, "");

const buildTrackingUrl = (sessionId: string, id: string) => {
  const base = (process.env.PUBLIC_BASE_URL || "").trim().replace(/\/$/, "");
  const relative = `/message/status?session=${encodeURIComponent(
    sessionId
  )}&id=${encodeURIComponent(id)}`;
  return base ? `${base}${relative}` : relative;
};

async function sendDeviceStatus(
  sessionId: string,
  status: "connected" | "disconnected" | "connecting"
) {
  const config = await getSessionWebhookConfig(sessionId);
  if (config.deviceStatusEnabled === false) return;

  const webhookUrl = normalizeWebhookUrl(config.webhookBaseUrl);
  if (!webhookUrl) return;

  await createWebhookClient(config.apiKey)
    .post(webhookUrl, {
      event: "device_status",
      session: sessionId,
      status,
      tl_code: config.apiKey || undefined,
    })
    .catch(console.error);
}

async function buildIncomingPayload(message: MessageReceived) {
  const image = await handleWebhookImageMessage(message);
  const video = await handleWebhookVideoMessage(message);
  const document = await handleWebhookDocumentMessage(message);
  const audio = await handleWebhookAudioMessage(message);

  return {
    session: message.sessionId,
    from: message.key.remoteJid ?? null,
    message:
      message.message?.conversation ||
      message.message?.extendedTextMessage?.text ||
      message.message?.imageMessage?.caption ||
      message.message?.videoMessage?.caption ||
      message.message?.documentMessage?.caption ||
      message.message?.contactMessage?.displayName ||
      message.message?.locationMessage?.comment ||
      message.message?.liveLocationMessage?.caption ||
      null,
    media: {
      image,
      video,
      document,
      audio,
    },
  };
}

async function onIncomingMessage(message: MessageReceived) {
  if (message.key.fromMe || message.key.remoteJid?.includes("broadcast"))
    return;

  const config = await getSessionWebhookConfig(message.sessionId);
  const webhookUrl = normalizeWebhookUrl(config.webhookBaseUrl);
  if (!webhookUrl) return;

  const client = createWebhookClient(config.apiKey);
  const payload = await buildIncomingPayload(message);

  if (config.incomingEnabled !== false) {
    client
      .post(webhookUrl, {
        event: "incoming_message",
        ...payload,
        tl_code: config.apiKey || undefined,
      })
      .catch(console.error);
  }

  if (config.autoReplyEnabled) {
    try {
      const res = await client.post(webhookUrl, {
        event: "auto_reply",
        ...payload,
        tl_code: config.apiKey || undefined,
      });
      const reply =
        res?.data?.reply ?? res?.data?.message ?? res?.data?.text ?? null;
      if (typeof reply === "string" && reply.trim() && message.key.remoteJid) {
        await whatsapp.sendText({
          sessionId: message.sessionId,
          to: message.key.remoteJid,
          text: reply.trim(),
          isGroup: message.key.remoteJid.includes("@g.us"),
          answering: message,
        });
      }
    } catch (err) {
      console.error("Auto-reply webhook failed", err);
    }
  }
}

async function onMessageUpdated(update: MessageUpdated) {
  const id = update.key?.id;
  if (id) {
    setMessageStatus({
      session: update.sessionId,
      id,
      status: update.messageStatus,
      updatedAt: new Date().toISOString(),
      key: update.key,
      update,
    });
  }

  const config = await getSessionWebhookConfig(update.sessionId);
  if (config.trackingEnabled === false) return;

  const webhookUrl = normalizeWebhookUrl(config.webhookBaseUrl);
  if (!webhookUrl) return;

  createWebhookClient(config.apiKey)
    .post(webhookUrl, {
      event: "message_status",
      session: update.sessionId,
      message_id: id ?? null,
      message_status: update.messageStatus,
      tracking_url: id ? buildTrackingUrl(update.sessionId, id) : null,
      tl_code: config.apiKey || undefined,
      key: update.key,
      update,
    })
    .catch(console.error);
}

export const whatsapp = new Whatsapp({
  adapter: new SQLiteAdapter(),

  onConnecting(sessionId) {
    console.log(`[${sessionId}] connecting`);
    sendDeviceStatus(sessionId, "connecting");
  },
  onConnected(sessionId) {
    console.log(`[${sessionId}] connected`);
    sendDeviceStatus(sessionId, "connected");
  },
  onDisconnected(sessionId) {
    console.log(`[${sessionId}] disconnected`);
    sendDeviceStatus(sessionId, "disconnected");
  },

  onMessageReceived: onIncomingMessage,
  onMessageUpdated,
});
