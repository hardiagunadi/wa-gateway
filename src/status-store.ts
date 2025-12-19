export type MessageStatusRecord = {
  session: string;
  id: string;
  status: string;
  updatedAt: string;
  createdAt?: string;
  to?: string;
  preview?: string;
  category?: string;
  key?: unknown;
  update?: unknown;
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
    status: record.status || existing?.status || "pending",
    updatedAt: timestamp,
    createdAt:
      existing?.createdAt || record.createdAt || record.updatedAt || timestamp,
    to: record.to ?? existing?.to,
    preview: record.preview ?? existing?.preview,
    category: record.category ?? existing?.category,
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
