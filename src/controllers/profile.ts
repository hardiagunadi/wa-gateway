import { Hono } from "hono";
import { requestValidator } from "../middlewares/validation.middleware";
import { z } from "zod";
import { createKeyMiddleware } from "../middlewares/key.middleware";
import { HTTPException } from "hono/http-exception";
import { whatsapp } from "../whatsapp";
import { getDeviceBySessionId } from "../wa-gateway/registry";
import { getContactByPhone } from "../wa-gateway/store";

export const createProfileController = () => {
  const getProfileSchema = z.object({
    session: z.string(),
    target: z
      .string()
      .refine((v) => v.includes("@s.whatsapp.net") || v.includes("@g.us"), {
        message: "target must contain '@s.whatsapp.net' or '@g.us'",
      }),
  });

  const app = new Hono()
    .basePath("/profile")

    /**
     *
     * POST /profile
     *
     */
    .post(
      "/",
      createKeyMiddleware(),
      requestValidator("json", getProfileSchema),
      async (c) => {
        const payload = c.req.valid("json");
        const isExist = await whatsapp.getSessionById(payload.session);
        if (!isExist) {
          throw new HTTPException(400, {
            message: "Session does not exist",
          });
        }

        const isRegistered = await whatsapp.isExist({
          sessionId: payload.session,
          to: payload.target,
          isGroup: payload.target.includes("@g.us"),
        });

        if (!isRegistered) {
          throw new HTTPException(400, {
            message: "Target is not registered",
          });
        }

        let name: string | null = null;
        if (payload.target.includes("@s.whatsapp.net")) {
          const device = await getDeviceBySessionId(payload.session);
          if (device?.token) {
            const phone = payload.target.replace(/@.*/, "");
            const contact = await getContactByPhone(device.token, phone);
            name = contact?.name ?? null;
          }
        }

        return c.json({
          data: {
            ...(await whatsapp.getProfile({
              sessionId: payload.session,
              target: payload.target,
            })),
            name,
          },
        });
      }
    );

  return app;
};
