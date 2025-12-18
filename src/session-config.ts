import fs from "fs/promises";
import path from "path";
import { waCredentialsDir } from "./wa-credentials";

export type SessionWebhookConfig = {
  webhookBaseUrl?: string;
  trackingWebhookBaseUrl?: string;
  deviceStatusWebhookBaseUrl?: string;
  apiKey?: string;
  incomingEnabled?: boolean;
  autoReplyEnabled?: boolean;
  trackingEnabled?: boolean;
  deviceStatusEnabled?: boolean;
};

const configPath = path.join(waCredentialsDir, "session-config.json");

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
    trackingWebhookBaseUrl: cfg.trackingWebhookBaseUrl,
    deviceStatusWebhookBaseUrl: cfg.deviceStatusWebhookBaseUrl,
    apiKey: cfg.apiKey,
    incomingEnabled: cfg.incomingEnabled ?? true,
    autoReplyEnabled: cfg.autoReplyEnabled ?? false,
    trackingEnabled: cfg.trackingEnabled ?? true,
    deviceStatusEnabled: cfg.deviceStatusEnabled ?? true,
  };
};
