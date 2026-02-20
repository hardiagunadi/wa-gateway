export type MessageStatusRecord = {
  session: string;
  id: string;
  status: string;
  updatedAt: string;
  createdAt?: string;
  to?: string;
  from?: string;
  preview?: string;
  category?: string;
  direction?: "incoming" | "outgoing";
  key?: unknown;
  update?: unknown;
};

/**
 * Normalisasi status pesan dari berbagai format WhatsApp/Baileys ke string standar.
 * - Numeric proto value: 0=error, 1=pending, 2=sent, 3=delivered, 4=read, 5=played
 * - Uppercase Baileys: SERVER_ACK, DELIVERY_ACK, READ, PLAYED, ERROR, PENDING
 * - Lowercase friendly: pending, sent, delivered, read, played, error, received, failed
 */
export const normalizeMessageStatus = (raw: unknown): string => {
  if (raw === undefined || raw === null || raw === "") return "pending";

  const num = typeof raw === "number" ? raw : parseInt(String(raw), 10);
  if (!isNaN(num) && String(raw).trim() !== "") {
    switch (num) {
      case 0: return "error";
      case 1: return "pending";
      case 2: return "sent";
      case 3: return "delivered";
      case 4: return "read";
      case 5: return "played";
    }
  }

  const lower = String(raw).toLowerCase().trim();
  switch (lower) {
    case "server_ack":     return "sent";
    case "delivery_ack":   return "delivered";
    case "read":           return "read";
    case "played":         return "played";
    case "error":
    case "failed":         return "failed";
    case "pending":        return "pending";
    case "sent":           return "sent";
    case "delivered":      return "delivered";
    case "received":       return "received";
    default:               return lower || "pending";
  }
};

const statusStore = new Map<string, MessageStatusRecord>();

const makeKey = (session: string, id: string) => `${session}:${id}`;

const nowIso = () => new Date().toISOString();

export const upsertMessageStatus = (
  record: Partial<MessageStatusRecord> & { session: string; id: string }
) => {
  const existing = statusStore.get(makeKey(record.session, record.id));
  const timestamp = record.updatedAt || nowIso();
  const merged: MessageStatusRecord = {
    session: record.session,
    id: record.id,
    status: record.status
      ? normalizeMessageStatus(record.status)
      : (existing?.status || "pending"),
    updatedAt: timestamp,
    createdAt:
      existing?.createdAt || record.createdAt || record.updatedAt || timestamp,
    to: record.to ?? existing?.to,
    from: record.from ?? existing?.from,
    preview: record.preview ?? existing?.preview,
    category: record.category ?? existing?.category,
    direction: record.direction ?? existing?.direction,
    key: record.key ?? existing?.key,
    update: record.update ?? existing?.update,
  };

  statusStore.set(makeKey(record.session, record.id), merged);
  return merged;
};

// Backward-compatible alias
export const setMessageStatus = upsertMessageStatus;

export const recordOutgoingMessage = (record: {
  session: string;
  id: string | null | undefined;
  to?: string;
  preview?: string;
  category?: string;
  status?: string;
}) => {
  if (!record.id) return null;
  return upsertMessageStatus({
    session: record.session,
    id: record.id,
    to: record.to,
    preview: record.preview,
    category: record.category,
    status: record.status || "pending",
    direction: "outgoing",
    createdAt: nowIso(),
  });
};

export const recordIncomingMessage = (record: {
  session: string;
  id: string | null | undefined;
  from?: string;
  to?: string;
  preview?: string;
  category?: string;
  status?: string;
}) => {
  if (!record.id) return null;
  return upsertMessageStatus({
    session: record.session,
    id: record.id,
    from: record.from,
    to: record.to,
    preview: record.preview,
    category: record.category,
    status: record.status || "received",
    direction: "incoming",
    createdAt: nowIso(),
  });
};

export const getMessageStatus = (session: string, id: string) => {
  return statusStore.get(makeKey(session, id)) ?? null;
};

export const listMessageStatuses = (session: string) => {
  const all = Array.from(statusStore.values()).filter(
    (r) => r.session === session
  );
  return all.sort(
    (a, b) => new Date(b.updatedAt).getTime() - new Date(a.updatedAt).getTime()
  );
};
