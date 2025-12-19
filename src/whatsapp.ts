import axios from "axios";
import type { MessageReceived, MessageUpdated } from "wa-multi-session";
import {
  SQLiteAdapter,
  Whatsapp,
  startSessionWithPairingCode,
  onPairingCode,
} from "wa-multi-session";
import {
  handleWebhookAudioMessage,
  handleWebhookDocumentMessage,
  handleWebhookImageMessage,
  handleWebhookVideoMessage,
} from "./webhooks/media";
import { getSessionWebhookConfig } from "./session-config";
import { upsertMessageStatus } from "./status-store";
import { getDeviceBySessionId } from "./wa-gateway/registry";
import { findAutoreplyByKeyword } from "./wa-gateway/store";
import { listStoredSessions, removeStoredSession } from "./session-store";
import { deleteDeviceBySessionId } from "./wa-gateway/registry";
import { waCredentialsDir } from "./wa-credentials";
import fs from "fs/promises";
import path from "path";

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

  const fromJid = message.key.remoteJid ?? null;
  const isGroup = Boolean(fromJid && fromJid.includes("@g.us"));
  const senderJid = (message.key as any)?.participant ?? fromJid;

  return {
    session: message.sessionId,
    from: fromJid,
    sender: senderJid,
    participant: senderJid,
    isGroup,
    group: isGroup ? { id: fromJid } : null,
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

  const lintaskuCompatUrl = config.lintaskuCompatWebhookUrl?.trim();
  if (lintaskuCompatUrl) {
    const compatClient = createWebhookClient(config.apiKey);
    compatClient
      .post(lintaskuCompatUrl, {
        message: payload.message,
        receiver: payload.from,
        message_status: "received",
        quota: null,
        // keep extra context for clients that might need it
        session: payload.session,
        sender: payload.sender,
        isGroup: payload.isGroup,
      })
      .catch(console.error);
  }

  // wa-gateway-compatible autoreply rules (token-scoped).
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
    const toJid = (update.key as any)?.remoteJid || null;
    const to = typeof toJid === "string" ? toJid.replace(/@.*/, "") : undefined;
    upsertMessageStatus({
      session: update.sessionId,
      id,
      status: update.messageStatus,
      updatedAt: new Date().toISOString(),
      to,
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

export const requestPairingCode = async (
  sessionId: string,
  phoneNumber: string,
  timeoutMs = 30000
) => {
  const phone = String(phoneNumber || "").trim();
  if (!phone) throw new Error("Nomor telepon wajib diisi");

  const cleanSession = async () => {
    try {
      const adapter = (whatsapp as any).adapter;
      if (adapter?.clearData) {
        await adapter.clearData(sessionId);
      }
      await whatsapp.deleteSession(sessionId);
    } catch (err) {
      console.error("Failed to cleanup pairing session", err);
    }
    await removeStoredSession(sessionId).catch(() => {});
    await deleteDeviceBySessionId(sessionId).catch(() => {});
    try {
      const remaining = await listStoredSessions().catch(() => []);
      if (!remaining || remaining.length === 0) {
        const dbPath = path.join(waCredentialsDir, "database.db");
        const dbShm = path.join(waCredentialsDir, "database.db-shm");
        const dbWal = path.join(waCredentialsDir, "database.db-wal");
        await fs.rm(dbPath, { force: true });
        await fs.rm(dbShm, { force: true });
        await fs.rm(dbWal, { force: true });
      }
    } catch (err) {
      console.error("Failed to purge sqlite database", err);
    }
  };

  const attempt = async () =>
    await new Promise<string>(async (resolve, reject) => {
      let timer: NodeJS.Timeout | null = null;

      const cleanup = () => {
        if (timer) clearTimeout(timer);
        // reset listener to no-op to avoid leaking cross-requests
        onPairingCode(() => {});
      };

      onPairingCode((sid, code) => {
        if (sid !== sessionId) return;
        cleanup();
        resolve(code);
      });

      try {
        const adapter = (whatsapp as any).adapter;
        if (adapter?.clearData) {
          await adapter.clearData(sessionId).catch(() => {});
        }
        await whatsapp.deleteSession(sessionId).catch(() => {});
        const res = await startSessionWithPairingCode(sessionId, {
          phoneNumber: phone,
        });

        const directCode =
          (res as any)?.pairingCode ||
          (res as any)?.code ||
          (typeof res === "string" ? res : null);
        if (directCode) {
          cleanup();
          resolve(directCode);
          return;
        }

        timer = setTimeout(() => {
          cleanup();
          reject(new Error("PAIRING_TIMEOUT"));
        }, timeoutMs);
      } catch (err) {
        cleanup();
        reject(err);
      }
    });

  let lastError: any = null;
  const maxAttempts = 2;
  for (let i = 0; i < maxAttempts; i++) {
    if (i > 0) {
      await cleanSession();
      await new Promise((r) => setTimeout(r, 500));
    }
    try {
      const code = await attempt();
      return code;
    } catch (err: any) {
      lastError = err;
      // If timeout, retry once; other errors break immediately.
      const message = String(err?.message || "");
      if (i === maxAttempts - 1 || !message.includes("TIMEOUT")) {
        await cleanSession();
        break;
      }
    }
  }

  throw new Error(
    lastError?.message || "Pairing code timeout, coba ulangi beberapa saat."
  );
};
