import fs from "fs/promises";
import path from "path";
import { waCredentialsDir } from "./wa-credentials";

export type SessionWebhookConfig = {
  deviceName?: string;
  webhookBaseUrl?: string;
  trackingWebhookBaseUrl?: string;
  deviceStatusWebhookBaseUrl?: string;
  apiKey?: string;
  incomingEnabled?: boolean;
  autoReplyEnabled?: boolean;
  trackingEnabled?: boolean;
  deviceStatusEnabled?: boolean;
  /**
   * Optional lintasku/topsetting compatibility webhook.
   * When set, wa-gateway will POST a lintasku-style payload in addition to the normal payload.
   */
  lintaskuCompatWebhookUrl?: string;
  /** Anti-spam: aktifkan pembatasan rate pesan */
  antiSpamEnabled?: boolean;
  /** Anti-spam: maks pesan yang boleh dikirim per menit (default: 20) */
  antiSpamMaxPerMinute?: number;
  /** Anti-spam: jeda minimum antar pesan dalam milidetik (default: 1000) */
  antiSpamDelayMs?: number;
  /** Anti-spam: interval dalam detik sebelum pesan ke penerima yang sama diizinkan lagi (0 = nonaktif) */
  antiSpamIntervalSeconds?: number;
};

export const sessionConfigPath = path.join(
  waCredentialsDir,
  "session-config.json"
);

const readAll = async (): Promise<Record<string, SessionWebhookConfig>> => {
  try {
    const raw = await fs.readFile(sessionConfigPath, "utf-8");
    const parsed = JSON.parse(raw);
    if (parsed && typeof parsed === "object") {
      return parsed as Record<string, SessionWebhookConfig>;
    }
    return {};
  } catch {
    return {};
  }
};

const normalizeConfig = (cfg: any): SessionWebhookConfig => ({
  deviceName: typeof cfg?.deviceName === "string" ? cfg.deviceName : undefined,
  webhookBaseUrl: cfg?.webhookBaseUrl,
  trackingWebhookBaseUrl: cfg?.trackingWebhookBaseUrl,
  deviceStatusWebhookBaseUrl: cfg?.deviceStatusWebhookBaseUrl,
  apiKey: cfg?.apiKey,
  incomingEnabled: cfg?.incomingEnabled ?? true,
  autoReplyEnabled: cfg?.autoReplyEnabled ?? false,
  trackingEnabled: cfg?.trackingEnabled ?? true,
  deviceStatusEnabled: cfg?.deviceStatusEnabled ?? true,
  lintaskuCompatWebhookUrl: cfg?.lintaskuCompatWebhookUrl,
  antiSpamEnabled: cfg?.antiSpamEnabled ?? false,
  antiSpamMaxPerMinute: typeof cfg?.antiSpamMaxPerMinute === "number" ? Math.max(1, cfg.antiSpamMaxPerMinute) : 20,
  antiSpamDelayMs: typeof cfg?.antiSpamDelayMs === "number" ? Math.max(0, cfg.antiSpamDelayMs) : 1000,
  antiSpamIntervalSeconds: typeof cfg?.antiSpamIntervalSeconds === "number" ? Math.max(0, cfg.antiSpamIntervalSeconds) : 0,
});

export const getSessionWebhookConfig = async (
  sessionId: string
): Promise<SessionWebhookConfig> => {
  const all = await readAll();
  const cfg = all[sessionId] ?? {};

  return normalizeConfig(cfg);
};

export const listSessionWebhookConfigs = async (): Promise<
  Record<string, SessionWebhookConfig>
> => {
  const all = await readAll();
  const normalized: Record<string, SessionWebhookConfig> = {};

  for (const [sessionId, cfg] of Object.entries(all)) {
    const id = String(sessionId || "").trim();
    if (!id) continue;
    normalized[id] = normalizeConfig(cfg);
  }

  return normalized;
};
