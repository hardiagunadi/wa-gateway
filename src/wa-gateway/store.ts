import fs from "fs/promises";
import path from "path";
import crypto from "crypto";

export type WablasContact = {
  phone: string;
  name?: string;
  email?: string;
  address?: string;
  birth_day?: string;
  createdAt: string;
  updatedAt: string;
};

export type WablasAutoreplyRule = {
  id: string;
  keyword: string;
  response: string;
  createdAt: string;
  updatedAt: string;
};

export type WablasScheduleCategory =
  | "text"
  | "image"
  | "video"
  | "audio"
  | "document"
  | "location"
  | "link"
  | "list"
  | "template"
  | "button";

export type WablasScheduleRecord = {
  id: string;
  token: string;
  sessionId: string;
  phone: string;
  isGroup?: boolean;
  category: WablasScheduleCategory;
  scheduledAt: string;
  scheduledAtMs: number;
  payload: Record<string, any>;
  status: "pending" | "sent" | "failed" | "canceled";
  messageId?: string | null;
  error?: string | null;
  createdAt: string;
  updatedAt: string;
};

const rootDir = path.resolve(process.cwd(), "wa_credentials");
const contactsPath = path.join(rootDir, "wablas-contacts.json");
const autoreplyPath = path.join(rootDir, "wablas-autoreply.json");
const schedulesPath = path.join(rootDir, "wablas-schedules.json");

async function ensureDir() {
  await fs.mkdir(rootDir, { recursive: true });
}

async function readJson<T>(file: string, fallback: T): Promise<T> {
  try {
    const raw = await fs.readFile(file, "utf-8");
    return (JSON.parse(raw) as T) ?? fallback;
  } catch {
    return fallback;
  }
}

async function writeJson<T>(file: string, value: T) {
  await ensureDir();
  await fs.writeFile(file, JSON.stringify(value, null, 2));
}

const nowIso = () => new Date().toISOString();

export const listContacts = async (token: string): Promise<WablasContact[]> => {
  const all = await readJson<Record<string, WablasContact[]>>(contactsPath, {});
  return all[token] ?? [];
};

export const upsertContacts = async (
  token: string,
  contacts: Array<Omit<WablasContact, "createdAt" | "updatedAt">>
) => {
  const all = await readJson<Record<string, WablasContact[]>>(contactsPath, {});
  const existing = all[token] ?? [];
  const byPhone = new Map(existing.map((c) => [c.phone, c]));

  for (const input of contacts) {
    const phone = (input.phone || "").trim();
    if (!phone) continue;
    const prev = byPhone.get(phone);
    const createdAt = prev?.createdAt ?? nowIso();
    byPhone.set(phone, {
      phone,
      name: input.name,
      email: input.email,
      address: input.address,
      birth_day: input.birth_day,
      createdAt,
      updatedAt: nowIso(),
    });
  }

  all[token] = Array.from(byPhone.values());
  await writeJson(contactsPath, all);
  return all[token];
};

export const getContactByPhone = async (token: string, phone: string) => {
  const contacts = await listContacts(token);
  return contacts.find((c) => c.phone === phone) ?? null;
};

export const listAutoreplyRules = async (
  token: string
): Promise<WablasAutoreplyRule[]> => {
  const all = await readJson<Record<string, WablasAutoreplyRule[]>>(
    autoreplyPath,
    {}
  );
  return all[token] ?? [];
};

export const addAutoreplyRule = async (
  token: string,
  input: { keyword: string; response: string }
) => {
  const all = await readJson<Record<string, WablasAutoreplyRule[]>>(
    autoreplyPath,
    {}
  );
  const list = all[token] ?? [];
  const rule: WablasAutoreplyRule = {
    id: crypto.randomUUID(),
    keyword: input.keyword,
    response: input.response,
    createdAt: nowIso(),
    updatedAt: nowIso(),
  };
  list.push(rule);
  all[token] = list;
  await writeJson(autoreplyPath, all);
  return rule;
};

export const updateAutoreplyRule = async (
  token: string,
  id: string,
  input: Partial<{ keyword: string; response: string }>
) => {
  const all = await readJson<Record<string, WablasAutoreplyRule[]>>(
    autoreplyPath,
    {}
  );
  const list = all[token] ?? [];
  const idx = list.findIndex((r) => r.id === id);
  if (idx < 0) return null;
  list[idx] = {
    ...list[idx],
    keyword: input.keyword ?? list[idx].keyword,
    response: input.response ?? list[idx].response,
    updatedAt: nowIso(),
  };
  all[token] = list;
  await writeJson(autoreplyPath, all);
  return list[idx];
};

export const deleteAutoreplyRule = async (token: string, id: string) => {
  const all = await readJson<Record<string, WablasAutoreplyRule[]>>(
    autoreplyPath,
    {}
  );
  const list = all[token] ?? [];
  const next = list.filter((r) => r.id !== id);
  all[token] = next;
  await writeJson(autoreplyPath, all);
  return next.length !== list.length;
};

export const findAutoreplyByKeyword = async (token: string, keyword: string) => {
  const rules = await listAutoreplyRules(token);
  const needle = keyword.trim().toLowerCase();
  return rules.filter((r) => r.keyword.trim().toLowerCase() === needle);
};

export const listSchedules = async (): Promise<WablasScheduleRecord[]> => {
  const all = await readJson<WablasScheduleRecord[]>(schedulesPath, []);
  return Array.isArray(all) ? all : [];
};

export const addSchedules = async (records: WablasScheduleRecord[]) => {
  const all = await listSchedules();
  all.push(...records);
  await writeJson(schedulesPath, all);
  return records;
};

export const updateSchedule = async (
  token: string,
  id: string,
  patch: Partial<Omit<WablasScheduleRecord, "id" | "token" | "createdAt">>
) => {
  const all = await listSchedules();
  const idx = all.findIndex((s) => s.id === id && s.token === token);
  if (idx < 0) return null;
  all[idx] = {
    ...all[idx],
    ...patch,
    updatedAt: nowIso(),
  };
  await writeJson(schedulesPath, all);
  return all[idx];
};

export const cancelSchedules = async (token: string, ids: string[]) => {
  const all = await listSchedules();
  const idSet = new Set(ids);
  let touched = 0;
  const next = all.map((s) => {
    if (s.token !== token) return s;
    if (!idSet.has(s.id)) return s;
    touched += 1;
    return { ...s, status: "canceled", updatedAt: nowIso() };
  });
  await writeJson(schedulesPath, next);
  return touched;
};

