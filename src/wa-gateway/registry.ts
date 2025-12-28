import fs from "fs/promises";
import path from "path";
import crypto from "crypto";
import { ensureWaCredentialsDir, waCredentialsDir } from "../wa-credentials";
import { getSessionWebhookConfig } from "../session-config";

export type DeviceRecord = {
  /**
   * Internal alias: token == apiKey.
   * Kept both to avoid naming confusion in configs/logs.
   */
  token: string;
  apiKey?: string;
  masterkey?: string;
  sessionId: string;
  name?: string;
  phone?: string;
  webhookUrl?: string;
  trackingBaseUrl?: string;
  createdAt: string;
};

const devicesPath = path.join(waCredentialsDir, "device-registry.json");

const resolveToken = (row: Partial<DeviceRecord> | any) =>
  clean((row?.token as any) || (row?.apiKey as any));

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
  const data = await readJson<unknown>(devicesPath, []);
  const rows: DeviceRecord[] = Array.isArray(data)
    ? (data as DeviceRecord[])
    : data && typeof data === "object"
    ? Object.entries(data).map(([sessionId, row]) => {
        const token = resolveToken(row as any) || "";
        return {
          token,
          apiKey: token || (row as any)?.apiKey,
          masterkey: (row as any)?.masterkey,
          sessionId: (row as any)?.sessionId || sessionId,
          name: (row as any)?.name || (row as any)?.deviceName,
          phone: (row as any)?.phone,
          webhookUrl: (row as any)?.webhookUrl,
          trackingBaseUrl: (row as any)?.trackingBaseUrl,
          createdAt:
            (row as any)?.createdAt || new Date().toISOString(),
        };
      })
    : [];

  const seenSession = new Set<string>();
  const seenToken = new Set<string>();
  const unique: DeviceRecord[] = [];
  for (const row of rows) {
    const sid = (row as any)?.sessionId;
    const tok = resolveToken(row);
    if ((sid && seenSession.has(sid)) || (tok && seenToken.has(tok))) continue;
    if (sid) seenSession.add(sid);
    if (tok) seenToken.add(tok);
    unique.push({
      ...(row as DeviceRecord),
      token: tok || "",
      apiKey: tok || (row as any)?.apiKey || undefined,
    });
  }
  if (unique.length !== rows.length) {
    await writeJson(devicesPath, unique);
  }
  return unique;
};

export const getDeviceByToken = async (
  token: string
): Promise<DeviceRecord | null> => {
  const devices = await listDevices();
  return (
    devices.find((d) => resolveToken(d) === clean(token)) ||
    devices.find((d) => d.apiKey === token) ||
    null
  );
};

export const getDeviceBySessionId = async (
  sessionId: string
): Promise<DeviceRecord | null> => {
  const devices = await listDevices();
  return devices.find((d) => d.sessionId === sessionId) || null;
};

export const upsertDevice = async (device: DeviceRecord) => {
  const devices = await listDevices();
  let filtered = devices.filter(
    (d) => d.sessionId !== device.sessionId && d.token !== device.token
  );
  const tok = resolveToken(device) || "";
  filtered.push({
    ...device,
    token: tok,
    apiKey: tok,
    masterkey: device.masterkey,
  });
  await writeJson(devicesPath, filtered);
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

  const configuredToken = clean(config.apiKey);

  if (!configuredToken && !existing) return null;

  const record: DeviceRecord = {
    token: existing?.token ?? "",
    apiKey: existing?.apiKey ?? existing?.token,
    sessionId: session,
    createdAt: existing?.createdAt || new Date().toISOString(),
    name: existing?.name,
    phone: existing?.phone,
    webhookUrl: existing?.webhookUrl,
    trackingBaseUrl: existing?.trackingBaseUrl,
  };

  const name = clean((config as any).deviceName);
  const webhookUrl = clean(config.webhookBaseUrl);
  const trackingBaseUrl = clean(config.trackingWebhookBaseUrl);

  const next: DeviceRecord = {
    ...record,
    token: configuredToken ?? record.token,
    apiKey: configuredToken ?? record.token,
    name: name ?? record.name,
    webhookUrl: webhookUrl ?? record.webhookUrl,
    trackingBaseUrl: trackingBaseUrl ?? record.trackingBaseUrl,
  };

  const shouldUpdate =
    !existing ||
    existing.token !== next.token ||
    existing.name !== next.name ||
    existing.webhookUrl !== next.webhookUrl ||
    existing.trackingBaseUrl !== next.trackingBaseUrl;

  if (shouldUpdate) {
    await upsertDevice(next);
  }

  return next;
};

export const generateToken = () => {
  return crypto.randomBytes(16).toString("hex");
};
