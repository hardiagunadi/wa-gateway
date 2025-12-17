import { Context } from "hono";

export const getWaGatewayToken = (c: Context): string | null => {
  const fromQuery = c.req.query("token");
  if (fromQuery) return fromQuery;

  const auth = c.req.header("authorization") || c.req.header("Authorization");
  if (!auth) return null;

  // Accept:
  // - "Bearer <token>"
  // - "<token>"
  // - "<token>.<secret_key>" (secret part ignored)
  const trimmed = auth.trim();
  const bearerMatch = trimmed.match(/^Bearer\s+(.+)$/i);
  const raw = bearerMatch ? bearerMatch[1].trim() : trimmed;
  const tokenOnly = raw.split(".")[0]?.trim();
  return tokenOnly || null;
};
