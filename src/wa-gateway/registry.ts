import fs from "fs/promises";
import path from "path";
import crypto from "crypto";
import { ensureWaCredentialsDir, waCredentialsDir } from "../wa-credentials";
import { getSessionWebhookConfig } from "../session-config";
import { listStoredSessions } from "../session-store";

export type DeviceRecord = {
  token: string;
  sessionId: string;
  name?: string;
  phone?: string;
  webhookUrl?: string;
  trackingBaseUrl?: string;
  createdAt: string;
};

const devicesPath = path.join(waCredentialsDir, "device-registry.json");

async function readJson<T>(file: string, fallback: T): Promise<T> {
  try {
    const raw = await fs.readFile(file, "utf-8");
    return (JSON.parse(raw) as T) ?? fallback;
  } catch {
    return fallback;
  }
}

async function writeJson<T>(file: string, value: T) {
  await ensureWaCredentialsDir();
  await fs.writeFile(file, JSON.stringify(value, null, 2));
}

export const listDevices = async (): Promise<DeviceRecord[]> => {
  const data = await readJson<DeviceRecord[]>(devicesPath, []);
  return Array.isArray(data) ? data : [];
};

export const getDeviceByToken = async (
  token: string
): Promise<DeviceRecord | null> => {
  const devices = await listDevices();
  return devices.find((d) => d.token === token) || null;
};

export const getDeviceBySessionId = async (
  sessionId: string
): Promise<DeviceRecord | null> => {
  const devices = await listDevices();
  return devices.find((d) => d.sessionId === sessionId) || null;
};

export const upsertDevice = async (device: DeviceRecord) => {
  const devices = await listDevices();
  const idx = devices.findIndex((d) => d.token === device.token);
  if (idx >= 0) devices[idx] = device;
  else devices.push(device);
  await writeJson(devicesPath, devices);
};

export const deleteDeviceByToken = async (token: string) => {
  const devices = await listDevices();
  await writeJson(
    devicesPath,
    devices.filter((d) => d.token !== token)
  );
};

export const deleteDeviceBySessionId = async (sessionId: string) => {
  const devices = await listDevices();
  await writeJson(
    devicesPath,
    devices.filter((d) => d.sessionId !== sessionId)
  );
};

const clean = (value?: string | null) => {
  const trimmed = (value || "").trim();
  return trimmed || undefined;
};

export const ensureDeviceRegistryForSession = async (
  sessionId: string
): Promise<DeviceRecord | null> => {
  const session = clean(sessionId);
  if (!session) return null;

  const existing = await getDeviceBySessionId(session);
  const config = await getSessionWebhookConfig(session);

  const record: DeviceRecord = existing || {
    token: clean(config.apiKey) || session,
    sessionId: session,
    createdAt: new Date().toISOString(),
  };

  const name = clean((config as any).deviceName);
  const webhookUrl = clean(config.webhookBaseUrl);
  const trackingBaseUrl = clean(config.trackingWebhookBaseUrl);

  const next: DeviceRecord = {
    ...record,
    name: name ?? record.name,
    webhookUrl: webhookUrl ?? record.webhookUrl,
    trackingBaseUrl: trackingBaseUrl ?? record.trackingBaseUrl,
  };

  const shouldUpdate =
    !existing ||
    existing.name !== next.name ||
    existing.webhookUrl !== next.webhookUrl ||
    existing.trackingBaseUrl !== next.trackingBaseUrl;

  if (shouldUpdate) {
    await upsertDevice(next);
  }

  return next;
};

export const syncDeviceRegistryWithStoredSessions = async () => {
  const sessions = await listStoredSessions().catch(() => []);
  for (const sessionId of sessions) {
    await ensureDeviceRegistryForSession(sessionId);
  }
};

export const generateToken = () => {
  return crypto.randomBytes(16).toString("hex");
};
