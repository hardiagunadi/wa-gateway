import { HTTPException } from "hono/http-exception";
import { whatsapp } from "../whatsapp";
import { recordOutgoingMessage } from "../status-store";

export const truthy = (value: unknown) => {
  if (typeof value === "boolean") return value;
  if (typeof value === "number") return value !== 0;
  if (typeof value === "string") return value === "true" || value === "1";
  return false;
};

export const normalizeGroupId = (id: string) => {
  const v = (id || "").trim();
  if (!v) return v;
  if (v.includes("@")) return v;
  if (v.includes("-")) return `${v}@g.us`;
  return v;
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
  const res = await whatsapp.sendText({
    sessionId: props.sessionId,
    to: normalizeGroupId(props.phone),
    text: props.message,
    isGroup: truthy(props.isGroup),
  });
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: props.phone,
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
  const res = await whatsapp.sendImage({
    sessionId: props.sessionId,
    to: normalizeGroupId(props.phone),
    media: props.image,
    text: props.caption,
    isGroup: truthy(props.isGroup),
  });
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: props.phone,
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
  const res = await whatsapp.sendVideo({
    sessionId: props.sessionId,
    to: normalizeGroupId(props.phone),
    media: props.video,
    text: props.caption,
    isGroup: truthy(props.isGroup),
  });
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: props.phone,
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
  const res = await whatsapp.sendDocument({
    sessionId: props.sessionId,
    to: normalizeGroupId(props.phone),
    media: props.document,
    filename: props.filename || "document.pdf",
    text: props.caption,
    isGroup: truthy(props.isGroup),
  });
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: props.phone,
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
  const res = await whatsapp.sendAudio({
    sessionId: props.sessionId,
    to: normalizeGroupId(props.phone),
    media: props.audio,
    asVoiceNote: truthy(props.ptt),
    isGroup: truthy(props.isGroup),
  } as any);
  recordOutgoingMessage({
    session: props.sessionId,
    id: (res as any)?.key?.id,
    to: props.phone,
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
  const jid = normalizeGroupId(props.phone);
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
    to: props.phone,
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
  const jid = normalizeGroupId(props.phone);
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
    to: props.phone,
    preview: props.message.description || "[list]",
    category: "list",
  });
  return res;
};
