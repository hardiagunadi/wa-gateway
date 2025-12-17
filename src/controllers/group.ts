import { Hono } from "hono";
import { HTTPException } from "hono/http-exception";
import { z } from "zod";
import { createKeyMiddleware } from "../middlewares/key.middleware";
import { requestValidator } from "../middlewares/validation.middleware";
import { whatsapp } from "../whatsapp";

const normalizePhoneInput = (value: string) => {
  const digits = (value || "").replace(/\D/g, "");
  if (!digits) return "";
  if (digits.startsWith("0")) return `62${digits.slice(1)}`;
  return digits;
};

export const createGroupController = () => {
  const querySchema = z.object({
    session: z.string(),
    q: z.string().optional(),
    phone: z.string().optional(),
    include_members: z.string().optional(),
  });

  const app = new Hono()
    .basePath("/group")
    .get(
      "/list",
      createKeyMiddleware(),
      requestValidator("query", querySchema),
      async (c) => {
        const q = c.req.valid("query");
        const sessionId = q.session;
        const needle = (q.q || "").trim().toLowerCase();
        const phoneNeedle = normalizePhoneInput(q.phone || "");
        const includeMembers = q.include_members === "1" || q.include_members === "true";

        const session = await whatsapp.getSessionById(sessionId);
        if (!session) throw new HTTPException(404, { message: "Session not found" });
        if ((session as any)?.status !== "connected") {
          throw new HTTPException(409, { message: "Session Not Ready" });
        }

        const sock = (session as any)?.sock;
        if (!sock?.groupFetchAllParticipating) {
          throw new HTTPException(500, { message: "Session socket does not support group listing" });
        }

        const all = await sock.groupFetchAllParticipating();
        const groups = Object.values(all || {});

        const filtered = groups.filter((g: any) => {
          const id = String(g?.id || "");
          const subject = String(g?.subject || "");
          if (needle) {
            const hit =
              id.toLowerCase().includes(needle) || subject.toLowerCase().includes(needle);
            if (!hit) return false;
          }

          if (phoneNeedle) {
            const participants = Array.isArray(g?.participants) ? g.participants : [];
            const has =
              participants.findIndex((p: any) => {
                const jid = String(p?.id || p?.jid || "");
                return jid.includes(phoneNeedle);
              }) >= 0;
            if (!has) return false;
          }

          return true;
        });

        const data = filtered.map((g: any) => {
          const participants = Array.isArray(g?.participants) ? g.participants : [];
          const row: any = {
            id: g?.id ?? null,
            subject: g?.subject ?? null,
            size: participants.length,
          };
          if (includeMembers) {
            row.members = participants.map((p: any) => p?.id ?? p?.jid ?? null).filter(Boolean);
          }
          return row;
        });

        return c.json({ data });
      }
    );

  return app;
};

