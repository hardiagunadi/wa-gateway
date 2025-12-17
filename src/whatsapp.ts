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
import { getDeviceBySessionId } from "./wablas/registry";
import { findAutoreplyByKeyword } from "./wablas/store";

const createWebhookClient = (apiKey?: string) =>
  axios.create({
    headers: apiKey ? { key: apiKey } : {},
  });

const normalizeBaseUrl = (baseUrl?: string) =>
  (baseUrl || "").trim().replace(/\/$/, "");

async function sendDeviceStatus(
  sessionId: string,
  status: "connected" | "disconnected" | "connecting"
) {
  const config = await getSessionWebhookConfig(sessionId);
  if (config.deviceStatusEnabled === false) return;

  const baseUrl = normalizeBaseUrl(
    config.deviceStatusWebhookBaseUrl || config.webhookBaseUrl
  );
  if (!baseUrl) return;

  await createWebhookClient(config.apiKey)
    .post(`${baseUrl}/session`, { session: sessionId, status })
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
  const baseUrl = normalizeBaseUrl(config.webhookBaseUrl);

  const payload = await buildIncomingPayload(message);
  const incomingText = typeof payload.message === "string" ? payload.message : "";

  if (baseUrl) {
    const client = createWebhookClient(config.apiKey);
    if (config.incomingEnabled !== false) {
      client.post(`${baseUrl}/message`, payload).catch(console.error);
    }

    if (config.autoReplyEnabled) {
      try {
        const res = await client.post(`${baseUrl}/auto-reply`, payload);
        const reply =
          res?.data?.reply ?? res?.data?.message ?? res?.data?.text ?? null;
        if (
          typeof reply === "string" &&
          reply.trim() &&
          message.key.remoteJid
        ) {
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

  // Wablas-compatible autoreply rules (token-scoped).
  // Only apply when webhook auto-reply is disabled to avoid double replies.
  if (!config.autoReplyEnabled && incomingText && message.key.remoteJid) {
    const device = await getDeviceBySessionId(message.sessionId);
    if (device) {
      const matches = await findAutoreplyByKeyword(device.token, incomingText);
      const reply = matches[0]?.response;
      if (typeof reply === "string" && reply.trim()) {
        await whatsapp.sendText({
          sessionId: message.sessionId,
          to: message.key.remoteJid,
          text: reply.trim(),
          isGroup: message.key.remoteJid.includes("@g.us"),
          answering: message,
        });
      }
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

  const baseUrl = normalizeBaseUrl(
    config.trackingWebhookBaseUrl || config.webhookBaseUrl
  );
  if (!baseUrl) return;

  createWebhookClient(config.apiKey)
    .post(`${baseUrl}/status`, {
      session: update.sessionId,
      message_id: id ?? null,
      message_status: update.messageStatus,
      tracking_url: id
        ? `/message/status?session=${encodeURIComponent(
            update.sessionId
          )}&id=${encodeURIComponent(id)}`
        : null,
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
