import axios from "axios";
import type { MessageReceived, MessageUpdated } from "wa-multi-session";
import { jidDecode } from "baileys";
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
import { recordIncomingMessage, upsertMessageStatus } from "./status-store";
import { getDeviceBySessionId } from "./wa-gateway/registry";
import { findAutoreplyByKeyword, upsertContactName } from "./wa-gateway/store";
import { listStoredSessions, removeStoredSession } from "./session-store";
import { deleteDeviceBySessionId } from "./wa-gateway/registry";
import { waCredentialsDir } from "./wa-credentials";
import fs from "fs/promises";
import path from "path";

const createWebhookClient = (apiKey?: string) =>
  axios.create({
    headers: apiKey ? { key: apiKey } : {},
    timeout: 10000,
  });

const sanitizeJidUser = (jid?: string | null) => {
  if (!jid) return null;
  const noDevice = jid.split(":")[0] || jid;
  const user = noDevice.replace(/@.*/, "");
  return user || null;
};

const normalizeDisplayName = (value?: string | null) => {
  const trimmed = (value || "").trim();
  return trimmed || null;
};

const isLidJid = (jid?: string | null) =>
  typeof jid === "string" && jid.toLowerCase().endsWith("@lid");

const ensurePhoneJid = (value: string) => {
  const trimmed = value.trim();
  if (!trimmed) return trimmed;
  if (trimmed.includes("@")) return trimmed;
  return `${trimmed}@s.whatsapp.net`;
};

const LID_CACHE_TTL_MS = 5 * 60 * 1000;
const lidCache = new Map<string, { value: string; expiresAt: number }>();

const getCachedLid = (lid: string) => {
  const cached = lidCache.get(lid);
  if (!cached) return null;
  if (cached.expiresAt <= Date.now()) {
    lidCache.delete(lid);
    return null;
  }
  return cached.value;
};

const setCachedLid = (lid: string, value: string) => {
  lidCache.set(lid, { value, expiresAt: Date.now() + LID_CACHE_TTL_MS });
};

const resolveLidFromAuthState = async (sock: any, lidJid: string) => {
  if (!sock) return null;
  const decoded = jidDecode(lidJid);
  const lidUser = decoded?.user || sanitizeJidUser(lidJid);
  if (!lidUser) return null;
  const keys = sock?.authState?.keys;
  if (!keys || typeof keys.get !== "function") return null;

  try {
    const stored = await keys.get("lid-mapping", [`${lidUser}_reverse`]);
    const pnUser = stored?.[`${lidUser}_reverse`];
    if (typeof pnUser === "string" && pnUser.trim()) {
      return ensurePhoneJid(pnUser);
    }
  } catch (err) {
    console.error("Failed to resolve lid from auth state", err);
  }

  return null;
};

/**
 * Baileys v7 delivers the sender's phone-number JID in `key.participantAlt` (group)
 * or `key.remoteJidAlt` (personal chat) when the primary id is a LID.
 */
const getMessageAltJid = (message: MessageReceived): string | null => {
  const key: any = message.key;
  const alt = key?.participantAlt || key?.remoteJidAlt || null;
  return typeof alt === "string" && alt.trim() ? alt.trim() : null;
};

const resolveLidFromSignalRepository = async (sock: any, lidJid: string) => {
  try {
    const pn = await sock?.signalRepository?.lidMapping?.getPNForLID?.(lidJid);
    return typeof pn === "string" && pn.trim() ? ensurePhoneJid(pn) : null;
  } catch (err) {
    console.error("Failed to resolve lid via signal repository", err);
    return null;
  }
};

/**
 * Extra fields for the bot: stable message id, mentions, quoted participant and the bot's own ids.
 * Only adds fields; existing payload keys are untouched.
 */
const getBotContext = async (message: MessageReceived) => {
  const content: any = message.message || {};
  const ctx =
    content.extendedTextMessage?.contextInfo ||
    content.imageMessage?.contextInfo ||
    content.videoMessage?.contextInfo ||
    content.documentMessage?.contextInfo ||
    content.audioMessage?.contextInfo ||
    null;

  let botJid: string | null = null;
  let botLid: string | null = null;
  try {
    const session = await whatsapp.getSessionById(message.sessionId);
    const user: any = (session as any)?.sock?.user;
    botJid = typeof user?.id === "string" ? user.id : null;
    botLid = typeof user?.lid === "string" ? user.lid : null;
  } catch {
    // bot ids are optional
  }

  return {
    messageId: message.key.id ?? null,
    mentions: Array.isArray(ctx?.mentionedJid) ? ctx.mentionedJid : [],
    quotedParticipant:
      typeof ctx?.participant === "string" ? ctx.participant : null,
    botJid,
    botLid,
  };
};

const resolveLidFromGroups = async (sessionId: string, lidJid: string) => {
  const cached = getCachedLid(lidJid);
  if (cached) return cached;

  const session = await whatsapp.getSessionById(sessionId);
  const sock: any = (session as any)?.sock;
  if (typeof sock?.groupFetchAllParticipating !== "function") {
    return null;
  }

  try {
    const groups = await sock.groupFetchAllParticipating();
    const metas = Object.values(groups || {});
    for (const meta of metas) {
      const participants = Array.isArray(meta?.participants)
        ? meta.participants
        : [];
      for (const participant of participants) {
        const lid =
          (typeof participant?.lid === "string" && participant.lid.trim()) ||
          (typeof participant?.id === "string" && isLidJid(participant.id)
            ? participant.id
            : null);
        const pn =
          (typeof participant?.phoneNumber === "string" &&
            participant.phoneNumber.trim()) ||
          (typeof participant?.id === "string" && !isLidJid(participant.id)
            ? participant.id
            : null);
        if (!lid || !pn) continue;
        const normalizedPn = ensurePhoneJid(pn);
        if (normalizedPn && !isLidJid(normalizedPn)) {
          setCachedLid(lid, normalizedPn);
        }
      }
    }
  } catch (err) {
    console.error("Failed to resolve lid via group list", err);
    return null;
  }

  return getCachedLid(lidJid);
};

const normalizeSenderJid = (
  candidateJid: string | null,
  participant: string | null
) => {
  const participantUser = sanitizeJidUser(participant);
  const candidateUser = sanitizeJidUser(candidateJid);

  // If already full @s.whatsapp.net, keep it.
  if (candidateJid && candidateJid.endsWith("@s.whatsapp.net")) {
    return candidateJid;
  }

  // Prefer participant phone as full JID when available.
  if (participantUser) {
    return `${participantUser}@s.whatsapp.net`;
  }

  if (candidateUser) {
    return `${candidateUser}@s.whatsapp.net`;
  }

  return candidateJid;
};

const resolveSenderInfo = async (message: MessageReceived) => {
  const remoteJid = message.key.remoteJid;
  const rawSender = (message.key as any)?.participant ?? remoteJid ?? null;
  const isGroup = Boolean(remoteJid && remoteJid.includes("@g.us"));
  const isLid = isLidJid(rawSender);
  const pushName = normalizeDisplayName((message as any)?.pushName);

  if (!rawSender) {
    return {
      senderJid: null,
      participant: null,
      displayName: pushName,
      phoneResolved: false,
    };
  }

  if (!isLid) {
    const participant = sanitizeJidUser(rawSender);
    const senderJid = normalizeSenderJid(rawSender, participant);
    return {
      senderJid,
      participant,
      displayName: pushName,
      phoneResolved: true,
    };
  }

  // Preferred: phone-number JID delivered together with the message (participantAlt/remoteJidAlt).
  const altJid = getMessageAltJid(message);
  if (altJid && !isLidJid(altJid)) {
    setCachedLid(rawSender, altJid);
    const participant = sanitizeJidUser(altJid);
    const senderJid = normalizeSenderJid(altJid, participant);
    return {
      senderJid,
      participant,
      displayName: pushName,
      phoneResolved: true,
    };
  }

  // Attempt to resolve @lid sender using auth state mapping (no group needed).
  try {
    const session = await whatsapp.getSessionById(message.sessionId);
    const sock: any = (session as any)?.sock;
    const resolvedFromAuth =
      (await resolveLidFromAuthState(sock, rawSender)) ||
      (await resolveLidFromSignalRepository(sock, rawSender));
    if (resolvedFromAuth) {
      setCachedLid(rawSender, resolvedFromAuth);
      const participant = sanitizeJidUser(resolvedFromAuth);
      const senderJid = normalizeSenderJid(resolvedFromAuth, participant);
      return {
        senderJid,
        participant,
        displayName: pushName,
        phoneResolved: true,
      };
    }

    // Attempt to resolve @lid sender to actual participant JID using group metadata.
    if (isGroup && sock?.groupMetadata) {
      const meta = await sock.groupMetadata(remoteJid);
      const match =
        meta?.participants?.find(
          (p: any) => p?.id === rawSender || p?.lid === rawSender
        ) || null;
      const displayName =
        normalizeDisplayName(match?.notify || match?.name || match?.verifiedName) ||
        pushName;

      const resolvedJid =
        (match?.id as string | undefined) ||
        (typeof match?.phoneNumber === "string"
          ? `${match.phoneNumber}@s.whatsapp.net`
          : null) ||
        rawSender;

      if (rawSender && resolvedJid && !isLidJid(resolvedJid)) {
        setCachedLid(rawSender, resolvedJid);
      }

      const participant =
        sanitizeJidUser(
          typeof match?.phoneNumber === "string"
            ? `${match.phoneNumber}@s.whatsapp.net`
            : resolvedJid
        ) ?? sanitizeJidUser(rawSender);

      const senderJid = normalizeSenderJid(resolvedJid, participant);
      return {
        senderJid,
        participant,
        displayName,
        phoneResolved: Boolean(resolvedJid && !isLidJid(resolvedJid)),
      };
    }
  } catch (err) {
    console.error("Failed to resolve participant for lid sender", err);
  }

  const resolved = await resolveLidFromGroups(message.sessionId, rawSender);
  if (resolved) {
    const participant = sanitizeJidUser(resolved) ?? sanitizeJidUser(rawSender);
    const senderJid = normalizeSenderJid(resolved, participant);
    return {
      senderJid,
      participant,
      displayName: pushName,
      phoneResolved: true,
    };
  }

  const participant = sanitizeJidUser(rawSender);
  const senderJid = normalizeSenderJid(rawSender, participant);

  return {
    senderJid,
    participant,
    displayName: pushName,
    phoneResolved: false,
  };
};

const normalizeBaseUrl = (baseUrl?: string) => {
  const trimmed = (baseUrl || "").trim().replace(/\/+$/, "");
  if (!trimmed) return "";
  return trimmed.replace(/\/(message|auto-reply|status|session)$/i, "");
};

const getIncomingPreview = (message: MessageReceived) => {
  const content = message.message;
  if (!content) return { preview: null, category: "unknown" };

  if (content.imageMessage) {
    return {
      preview: content.imageMessage.caption || "[image]",
      category: "image",
    };
  }

  if (content.videoMessage) {
    return {
      preview: content.videoMessage.caption || "[video]",
      category: "video",
    };
  }

  if (content.documentMessage) {
    return {
      preview:
        content.documentMessage.caption ||
        content.documentMessage.fileName ||
        "[document]",
      category: "document",
    };
  }

  if (content.audioMessage) {
    return { preview: "[audio]", category: "audio" };
  }

  if (content.conversation) {
    return { preview: content.conversation, category: "text" };
  }

  if (content.extendedTextMessage?.text) {
    return { preview: content.extendedTextMessage.text, category: "text" };
  }

  if (content.contactMessage?.displayName) {
    return { preview: content.contactMessage.displayName, category: "contact" };
  }

  if (content.locationMessage?.comment) {
    return { preview: content.locationMessage.comment, category: "location" };
  }

  if (content.liveLocationMessage?.caption) {
    return {
      preview: content.liveLocationMessage.caption,
      category: "location",
    };
  }

  return { preview: null, category: "unknown" };
};

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
    .catch((error) => {
      const code = axios.isAxiosError(error)
        ? error.code || error.response?.status
        : "unknown";
      console.warn(
        `[${sessionId}] status webhook failed status=${status} code=${code}`
      );
    });
}

async function buildIncomingPayload(
  message: MessageReceived,
  senderInfo?: {
    senderJid: string | null;
    participant: string | null;
    displayName?: string | null;
    phoneResolved?: boolean;
  }
) {
  const image = await handleWebhookImageMessage(message);
  const video = await handleWebhookVideoMessage(message);
  const document = await handleWebhookDocumentMessage(message);
  const audio = await handleWebhookAudioMessage(message);

  const fromJid = message.key.remoteJid ?? null;
  const isGroup = Boolean(fromJid && fromJid.includes("@g.us"));
  const { senderJid, participant, phoneResolved } =
    senderInfo || (await resolveSenderInfo(message));
  const rawSender: string | null =
    (message.key as any)?.participant ?? message.key.remoteJid ?? null;
  const senderLid = rawSender && isLidJid(rawSender) ? rawSender : null;
  const botContext = await getBotContext(message);
  const resolvedFrom =
    !isGroup && fromJid && isLidJid(fromJid) && senderJid ? senderJid : fromJid;

  return {
    session: message.sessionId,
    from: resolvedFrom,
    sender: senderJid,
    participant,
    senderLid,
    senderPn: senderLid && phoneResolved ? senderJid : null,
    ...botContext,
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

  const senderInfo = await resolveSenderInfo(message);
  const { preview, category } = getIncomingPreview(message);
  const device = await getDeviceBySessionId(message.sessionId);
  recordIncomingMessage({
    session: message.sessionId,
    id: message.key.id,
    from:
      senderInfo.senderJid ||
      senderInfo.participant ||
      (message.key as any)?.participant ||
      message.key.remoteJid ||
      undefined,
    to: message.key.remoteJid || undefined,
    preview: preview || undefined,
    category,
  });

  if (device?.token && senderInfo.phoneResolved) {
    const phone = sanitizeJidUser(senderInfo.senderJid);
    if (phone && senderInfo.displayName) {
      upsertContactName(device.token, phone, senderInfo.displayName).catch(
        (err) => {
          console.error("Failed to store contact name", err);
        }
      );
    }
  }

  const payload = await buildIncomingPayload(message, senderInfo);
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

const RECONNECT_BASE_DELAY_MS = 1000;
const RECONNECT_MAX_DELAY_MS = 60 * 1000;
const RECONNECT_JITTER_MS = 500;
const RECONNECT_SUPPRESS_TTL_MS = 30 * 1000;
const CONNECTING_STALE_AFTER_MS = 90 * 1000;
const WATCHDOG_INTERVAL_MS = 30 * 1000;

type ReconnectState = {
  attempts: number;
  timer?: NodeJS.Timeout;
};

const reconnectState = new Map<string, ReconnectState>();
const reconnectSuppressedUntil = new Map<string, number>();
const connectingSince = new Map<string, number>();

const normalizeSessionId = (sessionId: string) => String(sessionId || "").trim();

const clearReconnectSuppression = (sessionId: string) => {
  const id = normalizeSessionId(sessionId);
  if (!id) return;
  reconnectSuppressedUntil.delete(id);
};

const isReconnectSuppressed = (sessionId: string) => {
  const id = normalizeSessionId(sessionId);
  if (!id) return false;

  const expiresAt = reconnectSuppressedUntil.get(id);
  if (!expiresAt) return false;
  if (expiresAt <= Date.now()) {
    reconnectSuppressedUntil.delete(id);
    return false;
  }
  return true;
};

export const suppressReconnect = (
  sessionId: string,
  ttlMs = RECONNECT_SUPPRESS_TTL_MS
) => {
  const id = normalizeSessionId(sessionId);
  if (!id) return;

  reconnectSuppressedUntil.set(id, Date.now() + ttlMs);
  resetReconnectState(id);
};

export const deleteSessionWithReconnectSuppressed = async (
  sessionId: string
) => {
  suppressReconnect(sessionId);
  await whatsapp.deleteSession(sessionId);
};

const discardRuntimeSession = async (sessionId: string) => {
  const runtime = (whatsapp as any).sessions as
    | Map<string, { sock?: { end?: (error?: Error) => void } }>
    | undefined;
  const retryCount = (whatsapp as any).retryCount as
    | Map<string, number>
    | undefined;
  const session = runtime?.get(sessionId);

  retryCount?.set(sessionId, 10);
  try {
    session?.sock?.end?.(new Error("Session recovery"));
  } catch {}

  for (let attempt = 0; attempt < 50 && runtime?.has(sessionId); attempt++) {
    await new Promise((resolve) => setTimeout(resolve, 100));
  }

  runtime?.delete(sessionId);
};

export const recoverSession = async (
  sessionId: string,
  source = "automatic"
) => {
  const id = normalizeSessionId(sessionId);
  if (!id || isReconnectSuppressed(id)) return false;

  const stored = await listStoredSessions().catch(() => []);
  if (!stored.includes(id)) return false;

  await discardRuntimeSession(id);
  connectingSince.delete(id);
  console.log(`[${id}] recovery starting source=${source}`);
  await whatsapp.startSession(id, { printQR: false });
  return true;
};

const getReconnectDelayMs = (attempt: number) => {
  const exp = RECONNECT_BASE_DELAY_MS * 2 ** Math.max(attempt - 1, 0);
  const capped = Math.min(exp, RECONNECT_MAX_DELAY_MS);
  const jitter = Math.floor(Math.random() * RECONNECT_JITTER_MS);
  return capped + jitter;
};

const clearReconnectTimer = (sessionId: string) => {
  const state = reconnectState.get(sessionId);
  if (!state?.timer) return;
  clearTimeout(state.timer);
  state.timer = undefined;
};

const resetReconnectState = (sessionId: string) => {
  const state = reconnectState.get(sessionId);
  if (!state) return;
  if (state.timer) clearTimeout(state.timer);
  reconnectState.delete(sessionId);
};

const scheduleReconnect = async (sessionId: string) => {
  if (isReconnectSuppressed(sessionId)) {
    resetReconnectState(sessionId);
    return;
  }

  const stored = await listStoredSessions().catch(() => []);
  if (!stored.includes(sessionId)) {
    resetReconnectState(sessionId);
    return;
  }

  const state = reconnectState.get(sessionId) || { attempts: 0 };
  if (state.timer) return;

  const attempt = state.attempts + 1;
  state.attempts = attempt;

  const delay = getReconnectDelayMs(attempt);

  state.timer = setTimeout(async () => {
    state.timer = undefined;
    try {
      const latestStored = await listStoredSessions().catch(() => []);
      if (
        isReconnectSuppressed(sessionId) ||
        !latestStored.includes(sessionId)
      ) {
        resetReconnectState(sessionId);
        return;
      }

      const existing = await whatsapp.getSessionById(sessionId);
      const status = (existing as any)?.status;
      if (status === "connected" || status === "connecting") {
        return;
      }
      await recoverSession(sessionId, `retry-${attempt}`);
    } catch (err) {
      console.error(`[${sessionId}] reconnect attempt failed`, err);
    } finally {
      const latestStored = await listStoredSessions().catch(() => []);
      const existing = await whatsapp.getSessionById(sessionId);
      const status = (existing as any)?.status;
      if (
        !isReconnectSuppressed(sessionId) &&
        latestStored.includes(sessionId) &&
        status !== "connected" &&
        status !== "connecting"
      ) {
        scheduleReconnect(sessionId).catch((err) =>
          console.error(`[${sessionId}] reconnect reschedule failed`, err)
        );
      }
    }
  }, delay);

  reconnectState.set(sessionId, state);
  console.warn(
    `[${sessionId}] reconnect scheduled in ${Math.round(
      delay / 1000
    )}s (attempt ${attempt})`
  );
};

export const whatsapp = new Whatsapp({
  adapter: new SQLiteAdapter(),

  onConnecting(sessionId) {
    console.log(`[${sessionId}] connecting`);
    if (!connectingSince.has(sessionId)) {
      connectingSince.set(sessionId, Date.now());
    }
    sendDeviceStatus(sessionId, "connecting");
    clearReconnectTimer(sessionId);
  },
  onConnected(sessionId) {
    console.log(`[${sessionId}] connected`);
    connectingSince.delete(sessionId);
    sendDeviceStatus(sessionId, "connected");
    clearReconnectSuppression(sessionId);
    resetReconnectState(sessionId);
  },
  onDisconnected(
    sessionId,
    details?: { code?: number; reason?: string; message?: string }
  ) {
    connectingSince.delete(sessionId);
    console.warn(
      `[${sessionId}] disconnected code=${details?.code ?? "unknown"} reason=${details?.reason ?? "unknown"} message=${details?.message ?? "unknown"}`
    );
    sendDeviceStatus(sessionId, "disconnected");
    if (details?.code === 401) {
      removeStoredSession(sessionId).catch(() => {});
      resetReconnectState(sessionId);
      return;
    }
    if (isReconnectSuppressed(sessionId)) {
      resetReconnectState(sessionId);
      return;
    }
    scheduleReconnect(sessionId).catch((err) =>
      console.error(`[${sessionId}] reconnect schedule failed`, err)
    );
  },

  onMessageReceived: onIncomingMessage,
  onMessageUpdated,
});

const watchdog = setInterval(async () => {
  for (const [sessionId, startedAt] of connectingSince.entries()) {
    if (Date.now() - startedAt < CONNECTING_STALE_AFTER_MS) continue;

    console.warn(`[${sessionId}] connecting watchdog expired`);
    try {
      await recoverSession(sessionId, "connecting-watchdog");
    } catch (error) {
      const message = error instanceof Error ? error.message : String(error);
      console.warn(`[${sessionId}] watchdog recovery failed message=${message}`);
      connectingSince.set(sessionId, Date.now());
    }
  }
}, WATCHDOG_INTERVAL_MS);

watchdog.unref();

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
      await deleteSessionWithReconnectSuppressed(sessionId);
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
        await deleteSessionWithReconnectSuppressed(sessionId).catch(() => {});
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
