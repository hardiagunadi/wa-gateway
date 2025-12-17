import fs from "fs/promises";
import path from "path";

const rootDir = path.resolve(__dirname, "..");
const storePath = path.join(rootDir, "wa_credentials", "sessions.json");

async function readAll(): Promise<string[]> {
  try {
    const raw = await fs.readFile(storePath, "utf-8");
    const parsed = JSON.parse(raw);
    if (Array.isArray(parsed)) {
      return parsed.map(String).filter(Boolean);
    }
    return [];
  } catch {
    return [];
  }
}

async function writeAll(ids: string[]) {
  await fs.mkdir(path.dirname(storePath), { recursive: true });
  const unique = Array.from(new Set(ids.map((s) => String(s).trim()))).filter(
    Boolean
  );
  await fs.writeFile(storePath, JSON.stringify(unique, null, 2));
}

export const listStoredSessions = async (): Promise<string[]> => {
  return await readAll();
};

export const addStoredSession = async (sessionId: string) => {
  const id = String(sessionId || "").trim();
  if (!id) return;
  const all = await readAll();
  if (all.includes(id)) return;
  all.push(id);
  await writeAll(all);
};

export const removeStoredSession = async (sessionId: string) => {
  const id = String(sessionId || "").trim();
  if (!id) return;
  const all = await readAll();
  await writeAll(all.filter((s) => s !== id));
};

