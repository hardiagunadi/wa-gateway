import { HTTPException } from "hono/http-exception";
import { whatsapp } from "../whatsapp";
import { recordOutgoingMessage } from "../status-store";

export const truthy = (value: unknown) => {
  if (typeof value === "boolean") return value;
  if (typeof value === "number") return value !== 0;
  if (typeof value === "string") return value === "true" || value === "1";
  return false;
};

const isLidJid = (value: string) => value.toLowerCase().endsWith("@lid");
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

const ensurePhoneJid = (value: string) => {
  const trimmed = value.trim();
  if (!trimmed) return trimmed;
  if (trimmed.includes("@")) return trimmed;
  return `${trimmed}@s.whatsapp.net`;
};

const extractLidUser = (lidJid: string) => {
  const raw = (lidJid || "").trim();
  if (!raw) return "";
  const user = raw.split("@")[0] || "";
  return user.split(":")[0] || "";
};

const resolveLidFromAuthState = async (sessionId: string, lidJid: string) => {
  const lidUser = extractLidUser(lidJid);
  if (!lidUser) return null;

  const sock = await getSockReadyOrThrow(sessionId);
  const keys = (sock as any)?.authState?.keys;
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

export const normalizeGroupId = (id: string) => {
  const v = (id || "").trim();
  if (!v) return v;
  if (isLidJid(v)) {
    throw new HTTPException(400, {
      message: "Gunakan nomor WA asli, bukan format @lid.",
    });
  }
  if (v.includes("@")) return v;
  if (v.includes("-")) return `${v}@g.us`;
  return v;
};

const normalizeLogTarget = (jid: string) => {
  if (!jid) return jid;
  if (jid.endsWith("@s.whatsapp.net")) {
    return jid.replace("@s.whatsapp.net", "");
  }
  return jid;
};

const resolveLidToPn = async (sessionId: string, lidJid: string) => {
  const cached = getCachedLid(lidJid);
  if (cached) return cached;

  const authResolved = await resolveLidFromAuthState(sessionId, lidJid);
  if (authResolved) {
    setCachedLid(lidJid, authResolved);
    return authResolved;
  }

  const sock = await getSockReadyOrThrow(sessionId);
  if (typeof sock.groupFetchAllParticipating !== "function") {
    throw new HTTPException(400, {
      message: "Tidak bisa resolve @lid karena daftar grup tidak tersedia.",
    });
  }

  let groups: Record<string, any> | null = null;
  try {
    groups = await sock.groupFetchAllParticipating();
  } catch (err) {
    throw new HTTPException(400, {
      message: "Gagal mengambil metadata grup untuk resolve @lid.",
    });
  }

  const metas = Object.values(groups || {});
  for (const meta of metas) {
    const participants = Array.isArray(meta?.participants) ? meta.participants : [];
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

  const resolved = getCachedLid(lidJid);
  if (resolved) return resolved;

  throw new HTTPException(400, {
    message:
      "Tidak bisa resolve @lid ke nomor WA. Pastikan kontak ada di grup yang terhubung.",
  });
};

export const resolveLidToPhone = async (sessionId: string, lidJid: string) => {
  return resolveLidToPn(sessionId, lidJid);
};

const resolveRecipient = async (
  sessionId: string,
  rawTo: string,
  isGroup: boolean
) => {
  const trimmed = (rawTo || "").trim();
  if (!trimmed) return trimmed;
  if (isGroup) {
    return normalizeGroupId(trimmed);
  }
  if (isLidJid(trimmed)) {
    return resolveLidToPn(sessionId, trimmed);
  }
  return trimmed;
};

export const getSockReadyOrThrow = async (sessionId: string) => {
  const session = await whatsapp.getSessionById(sessionId);
  if (!session) {
    throw new HTTPException(404, { message: "Session not found" });
  }
  const status = (session as any)?.status;
  if (status !== "connected") {
    throw new HTTPException(409, { message: "Session Not Ready" });
  }
  const sock = (session as any)?.sock;
  if (!sock) {
    throw new HTTPException(500, { message: "Session socket unavailable" });
  }
  return sock as any;
};

export const sendV2Text = async (props: {
  sessionId: string;
  phone: string;
  message: string;
  isGroup?: unknown;
}) => {
  const isGroup = truthy(props.isGroup);
  const to = await resolveRecipient(props.sessionId, props.phone, isGroup);
  const res = await whatsapp.sendText({
    sessionId: props.sessionId,
    to,
    text: props.message,
    isGroup,
  });
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: normalizeLogTarget(to),
    preview: props.message,
    category: "text",
  });
  return res;
};

export const sendV2Image = async (props: {
  sessionId: string;
  phone: string;
  image: string;
  caption?: string;
  isGroup?: unknown;
}) => {
  const isGroup = truthy(props.isGroup);
  const to = await resolveRecipient(props.sessionId, props.phone, isGroup);
  const res = await whatsapp.sendImage({
    sessionId: props.sessionId,
    to,
    media: props.image,
    text: props.caption,
    isGroup,
  });
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: normalizeLogTarget(to),
    preview: props.caption || "[image]",
    category: "image",
  });
  return res;
};

export const sendV2Video = async (props: {
  sessionId: string;
  phone: string;
  video: string;
  caption?: string;
  isGroup?: unknown;
}) => {
  const isGroup = truthy(props.isGroup);
  const to = await resolveRecipient(props.sessionId, props.phone, isGroup);
  const res = await whatsapp.sendVideo({
    sessionId: props.sessionId,
    to,
    media: props.video,
    text: props.caption,
    isGroup,
  });
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: normalizeLogTarget(to),
    preview: props.caption || "[video]",
    category: "video",
  });
  return res;
};

export const sendV2Document = async (props: {
  sessionId: string;
  phone: string;
  document: string;
  filename?: string;
  caption?: string;
  isGroup?: unknown;
}) => {
  const isGroup = truthy(props.isGroup);
  const to = await resolveRecipient(props.sessionId, props.phone, isGroup);
  const res = await whatsapp.sendDocument({
    sessionId: props.sessionId,
    to,
    media: props.document,
    filename: props.filename || "document.pdf",
    text: props.caption,
    isGroup,
  });
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: normalizeLogTarget(to),
    preview: props.caption || props.document || "[document]",
    category: "document",
  });
  return res;
};

export const sendV2Audio = async (props: {
  sessionId: string;
  phone: string;
  audio: string;
  ptt?: unknown;
  isGroup?: unknown;
}) => {
  const isGroup = truthy(props.isGroup);
  const to = await resolveRecipient(props.sessionId, props.phone, isGroup);
  const res = await whatsapp.sendAudio({
    sessionId: props.sessionId,
    to,
    media: props.audio,
    asVoiceNote: truthy(props.ptt),
    isGroup,
  } as any);
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: normalizeLogTarget(to),
    preview: "[audio]",
    category: "audio",
  });
  return res;
};

export const sendV2Link = async (props: {
  sessionId: string;
  phone: string;
  text?: string;
  link: string;
  isGroup?: unknown;
}) => {
  const parts = [];
  if (props.text?.trim()) parts.push(props.text.trim());
  parts.push(props.link.trim());
  return sendV2Text({
    sessionId: props.sessionId,
    phone: props.phone,
    message: parts.join("\n"),
    isGroup: props.isGroup,
  });
};

export const sendV2Location = async (props: {
  sessionId: string;
  phone: string;
  message: {
    name?: string;
    address?: string;
    latitude: number;
    longitude: number;
  };
  isGroup?: unknown;
}) => {
  const sock = await getSockReadyOrThrow(props.sessionId);
  const jid = await resolveRecipient(
    props.sessionId,
    props.phone,
    truthy(props.isGroup)
  );
  const res = await sock.sendMessage(jid, {
    location: {
      degreesLatitude: Number(props.message.latitude),
      degreesLongitude: Number(props.message.longitude),
      name: props.message.name,
      address: props.message.address,
    },
  });
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: normalizeLogTarget(jid),
    preview:
      props.message.name ||
      props.message.address ||
      `[location ${props.message.latitude},${props.message.longitude}]`,
    category: "location",
  });
  return res;
};

export const sendV2List = async (props: {
  sessionId: string;
  phone: string;
  message: {
    title?: string;
    description?: string;
    buttonText?: string;
    footer?: string;
    lists: Array<{ title: string; description?: string; id?: string }>;
  };
  isGroup?: unknown;
}) => {
  const sock = await getSockReadyOrThrow(props.sessionId);
  const jid = await resolveRecipient(
    props.sessionId,
    props.phone,
    truthy(props.isGroup)
  );
  const rows = (props.message.lists || []).map((row, idx) => ({
    title: row.title,
    description: row.description,
    rowId: row.id || `row-${idx + 1}`,
  }));
  const sections = [
    {
      title: props.message.title || "Menu",
      rows,
    },
  ];

  const res = await sock.sendMessage(jid, {
    text: props.message.description || "",
    footer: props.message.footer || "",
    title: props.message.title || "",
    buttonText: props.message.buttonText || "Select",
    sections,
  });
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: normalizeLogTarget(jid),
    preview: props.message.description || "[list]",
    category: "list",
  });
  return res;
};
