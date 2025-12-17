export type MessageStatusRecord = {
  session: string;
  id: string;
  status: string;
  updatedAt: string;
  key?: unknown;
  update?: unknown;
};

const statusStore = new Map<string, MessageStatusRecord>();

const makeKey = (session: string, id: string) => `${session}:${id}`;

export const setMessageStatus = (record: MessageStatusRecord) => {
  statusStore.set(makeKey(record.session, record.id), record);
};

export const getMessageStatus = (session: string, id: string) => {
  return statusStore.get(makeKey(session, id)) ?? null;
};

