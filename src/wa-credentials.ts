import fs from "fs";
import path from "path";

const resolveWaCredentialsDir = () => {
  const fromEnv = process.env.WA_CREDENTIALS_DIR
    ? path.resolve(process.env.WA_CREDENTIALS_DIR)
    : null;
  const fromCwd = path.resolve(process.cwd(), "wa_credentials");
  const fromRepo = path.resolve(__dirname, "..", "wa_credentials");

  const candidates = [fromEnv, fromCwd, fromRepo].filter(
    (dir): dir is string => typeof dir === "string"
  );

  const existing = candidates.find((dir) => {
    try {
      return fs.statSync(dir).isDirectory();
    } catch {
      return false;
    }
  });

  return existing || fromEnv || fromCwd || fromRepo;
};

export const waCredentialsDir = resolveWaCredentialsDir();

export const ensureWaCredentialsDir = async () => {
  await fs.promises.mkdir(waCredentialsDir, { recursive: true });
};
