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
