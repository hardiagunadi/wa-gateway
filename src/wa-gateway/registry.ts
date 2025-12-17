import fs from "fs/promises";
import path from "path";
import crypto from "crypto";

export type DeviceRecord = {
  token: string;
  sessionId: string;
  name?: string;
  phone?: string;
  webhookUrl?: string;
  trackingBaseUrl?: string;
  createdAt: string;
};

const rootDir = path.resolve(process.cwd(), "wa_credentials");
const devicesPath = path.join(rootDir, "device-registry.json");

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

export const generateToken = () => {
  return crypto.randomBytes(16).toString("hex");
};

