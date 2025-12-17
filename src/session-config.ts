import fs from "fs/promises";
import path from "path";

export type SessionWebhookConfig = {
  webhookBaseUrl?: string;
  apiKey?: string;
  incomingEnabled?: boolean;
  autoReplyEnabled?: boolean;
  trackingEnabled?: boolean;
  deviceStatusEnabled?: boolean;
};

const configPath = path.resolve(
  process.cwd(),
  "wa_credentials/session-config.json"
);

const readAll = async (): Promise<Record<string, SessionWebhookConfig>> => {
  try {
    const raw = await fs.readFile(configPath, "utf-8");
    const parsed = JSON.parse(raw);
    if (parsed && typeof parsed === "object") {
      return parsed as Record<string, SessionWebhookConfig>;
    }
    return {};
  } catch {
    return {};
  }
};

export const getSessionWebhookConfig = async (
  sessionId: string
): Promise<SessionWebhookConfig> => {
  const all = await readAll();
  const cfg = all[sessionId] ?? {};

  return {
    webhookBaseUrl: cfg.webhookBaseUrl,
    apiKey: cfg.apiKey,
    incomingEnabled: cfg.incomingEnabled ?? true,
    autoReplyEnabled: cfg.autoReplyEnabled ?? false,
    trackingEnabled: cfg.trackingEnabled ?? true,
    deviceStatusEnabled: cfg.deviceStatusEnabled ?? true,
  };
};

