import { Hono } from "hono";
import { requestValidator } from "../middlewares/validation.middleware";
import { z } from "zod";
import { createKeyMiddleware } from "../middlewares/key.middleware";
import { toDataURL } from "qrcode";
import { HTTPException } from "hono/http-exception";
import { whatsapp } from "../whatsapp";
import {
  addStoredSession,
  listStoredSessions,
  removeStoredSession,
} from "../session-store";
import { ensureDeviceRegistryForSession } from "../wa-gateway/registry";

export const createSessionController = () => {
  const startSessionSchema = z.object({
    session: z.string(),
  });

  const app = new Hono()
    .basePath("/session")

    /**
     *
     * GET /session
     *
     */
    .get("/", createKeyMiddleware(), async (c) => {
      let runningSessions: string[] = [];
      let persistedSessions: string[] = [];
      let storedSessions: string[] = [];

      try {
        runningSessions = await whatsapp.getSessionsIds();

        // Reload persisted sessions if nothing is currently in memory (e.g. after restart)
        if (runningSessions.length === 0) {
          await whatsapp.load();
          runningSessions = await whatsapp.getSessionsIds();
        }

        const adapter = (whatsapp as any).adapter;
        if (adapter?.listSessions) {
          persistedSessions = await adapter.listSessions();
        }

        storedSessions = await listStoredSessions();
      } catch (error) {
        console.error("Failed to list sessions", error);
      }

      const sessions = Array.from(
        new Set<string>([...persistedSessions, ...runningSessions, ...storedSessions])
      );

      return c.json({
        data: sessions,
      });
    })

    /**
     *
     * GET /session/status
     *
     * Return session statuses + basic device info.
     */
    .get("/status", createKeyMiddleware(), async (c) => {
      let runningSessions: string[] = [];
      let persistedSessions: string[] = [];
      let storedSessions: string[] = [];

      try {
        runningSessions = await whatsapp.getSessionsIds();
        if (runningSessions.length === 0) {
          await whatsapp.load();
          runningSessions = await whatsapp.getSessionsIds();
        }

        const adapter = (whatsapp as any).adapter;
        if (adapter?.listSessions) {
          persistedSessions = await adapter.listSessions();
        }

        storedSessions = await listStoredSessions();
      } catch (error) {
        console.error("Failed to list sessions", error);
      }

      const ids = Array.from(
        new Set<string>([...persistedSessions, ...runningSessions, ...storedSessions])
      );

      const data = await Promise.all(
        ids.map(async (id) => {
          const session = await whatsapp.getSessionById(id);
          const status = (session as any)?.status || "disconnected";
          const userId = (session as any)?.sock?.user?.id || null;
          const userName = (session as any)?.sock?.user?.name || null;
          return {
            id,
            status,
            user: userId
              ? {
                  id: userId,
                  name: userName,
                }
              : null,
          };
        })
      );

      return c.json({ data });
    })
    /**
     *
     * POST /session/start
     *
     */
    .post(
      "/start",
      createKeyMiddleware(),
      requestValidator("json", startSessionSchema),
      async (c) => {
        const payload = c.req.valid("json");

        await addStoredSession(payload.session);
        await ensureDeviceRegistryForSession(payload.session);

        const isExist = await whatsapp.getSessionById(payload.session);
        if (isExist) {
          throw new HTTPException(400, {
            message: "Session already exist",
          });
        }

        const qr = await new Promise<string | null>(async (r) => {
          await whatsapp.startSession(payload.session, {
            onConnected() {
              r(null);
            },
            onQRUpdated(qr) {
              r(qr);
            },
          });
        });

        if (qr) {
          const qrImage = await toDataURL(qr);
          return c.json({
            qr,
            qr_image: qrImage,
            qrImage,
          });
        }

        return c.json({
          data: {
            message: "Connected",
          },
        });
      }
    )

    /**
     *
     * GET /session/start
     *
     */
    .get(
      "/start",
      createKeyMiddleware(),
      requestValidator("query", startSessionSchema),
      async (c) => {
        const payload = c.req.valid("query");

        await addStoredSession(payload.session);
        await ensureDeviceRegistryForSession(payload.session);

        const isExist = await whatsapp.getSessionById(payload.session);
        if (isExist) {
          throw new HTTPException(400, {
            message: "Session already exist",
          });
        }

        const qr = await new Promise<string | null>(async (r) => {
          await whatsapp.startSession(payload.session, {
            onConnected() {
              r(null);
            },
            onQRUpdated(qr) {
              r(qr);
            },
          });
        });

        if (qr) {
          return c.render(`
            <div id="qrcode"></div>

            <script type="text/javascript">
                let qr = '${await toDataURL(qr)}'
                let image = new Image()
                image.src = qr
                document.body.appendChild(image)
            </script>
            `);
        }

        return c.json({
          data: {
            message: "Connected",
          },
        });
      }
    )
    /**
     *
     * ALL /session/logout
     *
     */
    .all("/logout", createKeyMiddleware(), async (c) => {
      const sessionId =
        c.req.query().session || (await c.req.json()).session || "";
      await addStoredSession(sessionId);
      await whatsapp.deleteSession(sessionId);
      return c.json({
        data: "success",
      });
    })

    /**
     *
     * ALL /session/delete
     *
     * Logout session + remove persisted auth data (hard delete).
     */
    .all("/delete", createKeyMiddleware(), async (c) => {
      const sessionId =
        c.req.query().session || (await c.req.json()).session || "";
      if (!sessionId) {
        throw new HTTPException(400, { message: "session is required" });
      }

      await whatsapp.deleteSession(sessionId);

      const adapter = (whatsapp as any).adapter;
      if (adapter?.clearData) {
        await adapter.clearData(sessionId);
      }

      await removeStoredSession(sessionId);
      try {
        const { deleteDeviceBySessionId } = await import("../wa-gateway/registry");
        await deleteDeviceBySessionId(sessionId);
      } catch (err) {
        console.error("Failed to remove registry entry", err);
      }

      return c.json({ data: "success" });
    })

    /**
     *
     * ALL /session/restart
     *
     * Restart session connection without clearing stored creds.
     * This will usually reconnect without requiring QR unless the device is logged out.
     */
    .all("/restart", createKeyMiddleware(), async (c) => {
      const sessionId =
        c.req.query().session || (await c.req.json()).session || "";
      if (!sessionId) {
        throw new HTTPException(400, { message: "session is required" });
      }

      await addStoredSession(sessionId);

      const existing = await whatsapp.getSessionById(sessionId);
      if (existing?.sock) {
        try {
          existing.sock.end(undefined);
        } catch {
          // ignore
        }
      }

      // Remove from internal session map so startSession can run again.
      const sessions = (whatsapp as any).sessions;
      if (sessions?.delete) {
        sessions.delete(sessionId);
      }

      const qr = await new Promise<string | null>(async (r) => {
        await whatsapp.startSession(sessionId, {
          onConnected() {
            r(null);
          },
          onQRUpdated(qr) {
            r(qr);
          },
          printQR: false,
        });
      });

      if (qr) {
        return c.json({ qr });
      }

      return c.json({ data: { message: "Connected" } });
    });

  return app;
};
