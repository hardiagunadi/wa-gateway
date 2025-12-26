import { Hono } from "hono";
import fs from "fs/promises";
import path from "path";
import { env } from "../env";

const registryPath = path.join(process.cwd(), "wa_credentials", "device-registry.json");

const parseBasicAuth = (value: string | null) => {
  if (!value?.startsWith("Basic ")) return null;
  const raw = value.slice(6).trim();
  try {
    const decoded = Buffer.from(raw, "base64").toString("utf-8");
    const [user, pass] = decoded.split(":");
    return { user, pass };
  } catch {
    return null;
  }
};

const isAuthorized = (authHeader: string | null) => {
  if (env.REGISTRY_USER || env.REGISTRY_PASS) {
    const creds = parseBasicAuth(authHeader);
    return (
      creds?.user === (env.REGISTRY_USER || "") &&
      creds?.pass === (env.REGISTRY_PASS || "")
    );
  }

  return true;
};

export const createRegistryController = () => {
  const app = new Hono().basePath("/admin");

  app.get("/device-registry", async (c) => {
    if (!isAuthorized(c.req.header("Authorization") || null)) {
      return c.json({ message: "Unauthorized" }, 401);
    }

    try {
      const raw = await fs.readFile(registryPath, "utf-8");
      const data = JSON.parse(raw);
      return c.json(data);
    } catch {
      return c.json([], 200);
    }
  });

  return app;
};
