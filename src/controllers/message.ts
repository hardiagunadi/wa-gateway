import { Hono } from "hono";
import { createKeyMiddleware } from "../middlewares/key.middleware";
import { requestValidator } from "../middlewares/validation.middleware";
import { z } from "zod";
import { HTTPException } from "hono/http-exception";
import { whatsapp } from "../whatsapp";
import {
  getMessageStatus,
  listMessageStatuses,
  recordOutgoingMessage,
} from "../status-store";
import { applyAntiSpam } from "../anti-spam";

export const createMessageController = () => {
  const sendMessageSchema = z.object({
    session: z.string(),
    to: z.string(),
    text: z.string(),
    is_group: z.boolean().optional(),
  });

  const app = new Hono()
    .basePath("/message")
    /**
     *
     * GET /message/status
     *
     * Return last known message status for tracking.
     */
    .get(
      "/status",
      createKeyMiddleware(),
      requestValidator(
        "query",
        z.object({
          session: z.string(),
          id: z.string(),
        })
      ),
      async (c) => {
        const payload = c.req.valid("query");
        const record = getMessageStatus(payload.session, payload.id);
        if (!record) {
          throw new HTTPException(404, {
            message: "Status not found",
          });
        }
        return c.json({ data: record });
      }
    )
    /**
     *
     * GET /message/log
     *
     * List message status records for a session.
     */
    .get(
      "/log",
      createKeyMiddleware(),
      requestValidator(
        "query",
        z.object({
          session: z.string(),
        })
      ),
      async (c) => {
        const payload = c.req.valid("query");
        const data = listMessageStatuses(payload.session);
        return c.json({ data });
      }
    )
    /**
     *
     * POST /message/send-text
     *
     */
    .post(
      "/send-text",
      createKeyMiddleware(),
      requestValidator("json", sendMessageSchema),
      async (c) => {
        const payload = c.req.valid("json");
        const isExist = await whatsapp.getSessionById(payload.session);
        if (!isExist) {
          throw new HTTPException(400, {
            message: "Session does not exist",
          });
        }

        await applyAntiSpam(payload.session, payload.to);

        await whatsapp.sendTypingIndicator({
          sessionId: payload.session,
          to: payload.to,
          duration: Math.min(5000, payload.text.length * 100),
          isGroup: payload.is_group,
        });

        const response = await whatsapp.sendText({
          sessionId: payload.session,
          to: payload.to,
          text: payload.text,
          isGroup: payload.is_group,
        });
        recordOutgoingMessage({
          session: payload.session,
          id: (response as any)?.key?.id,
          to: payload.to,
          preview: payload.text,
          category: "text",
        });

        return c.json({
          data: response,
        });
      }
    )
    /**
     *
     * POST /message/send-text
     *
     * @deprecated
     * This endpoint is deprecated, use POST /send-text instead
     */
    .get(
      "/send-text",
      createKeyMiddleware(),
      requestValidator("query", sendMessageSchema),
      async (c) => {
        const payload = c.req.valid("query");
        const isExist = await whatsapp.getSessionById(payload.session);
        if (!isExist) {
          throw new HTTPException(400, {
            message: "Session does not exist",
          });
        }

        const response = await whatsapp.sendText({
          sessionId: payload.session,
          to: payload.to,
          text: payload.text,
        });
        recordOutgoingMessage({
          session: payload.session,
          id: (response as any)?.key?.id,
          to: payload.to,
          preview: payload.text,
          category: "text",
        });

        return c.json({
          data: response,
        });
      }
    )
    /**
     *
     * POST /message/send-image
     *
     */
    .post(
      "/send-image",
      createKeyMiddleware(),
      requestValidator(
        "json",
        sendMessageSchema.merge(
          z.object({
            image_url: z.string(),
          })
        )
      ),
      async (c) => {
        const payload = c.req.valid("json");
        const isExist = await whatsapp.getSessionById(payload.session);
        if (!isExist) {
          throw new HTTPException(400, {
            message: "Session does not exist",
          });
        }

        await applyAntiSpam(payload.session, payload.to);

        await whatsapp.sendTypingIndicator({
          sessionId: payload.session,
          to: payload.to,
          duration: Math.min(5000, payload.text.length * 100),
          isGroup: payload.is_group,
        });

        const response = await whatsapp.sendImage({
          sessionId: payload.session,
          to: payload.to,
          text: payload.text,
          media: payload.image_url,
          isGroup: payload.is_group,
        });
        recordOutgoingMessage({
          session: payload.session,
          id: (response as any)?.key?.id,
          to: payload.to,
          preview: payload.text || "[image]",
          category: "image",
        });

        return c.json({
          data: response,
        });
      }
    )
    /**
     *
     * POST /message/send-document
     *
     */
    .post(
      "/send-document",
      createKeyMiddleware(),
      requestValidator(
        "json",
        sendMessageSchema.merge(
          z.object({
            document_url: z.string(),
            document_name: z.string(),
          })
        )
      ),
      async (c) => {
        const payload = c.req.valid("json");
        const isExist = await whatsapp.getSessionById(payload.session);
        if (!isExist) {
          throw new HTTPException(400, {
            message: "Session does not exist",
          });
        }

        await applyAntiSpam(payload.session, payload.to);

        await whatsapp.sendTypingIndicator({
          sessionId: payload.session,
          to: payload.to,
          duration: Math.min(5000, payload.text.length * 100),
          isGroup: payload.is_group,
        });

        const response = await whatsapp.sendDocument({
          sessionId: payload.session,
          to: payload.to,
          text: payload.text,
          media: payload.document_url,
          filename: payload.document_name,
          isGroup: payload.is_group,
        });
        recordOutgoingMessage({
          session: payload.session,
          id: (response as any)?.key?.id,
          to: payload.to,
          preview: payload.text || payload.document_name || "[document]",
          category: "document",
        });

        return c.json({
          data: response,
        });
      }
    )
    /**
     *
     * POST /message/send-video
     *
     */
    .post(
      "/send-video",
      createKeyMiddleware(),
      requestValidator(
        "json",
        z.object({
          session: z.string(),
          to: z.string(),
          text: z.string().optional(),
          video_url: z.string(),
          is_group: z.boolean().optional(),
        })
      ),
      async (c) => {
        const payload = c.req.valid("json");
        const isExist = await whatsapp.getSessionById(payload.session);
        if (!isExist) {
          throw new HTTPException(400, {
            message: "Session does not exist",
          });
        }

        await applyAntiSpam(payload.session, payload.to);

        await whatsapp.sendTypingIndicator({
          sessionId: payload.session,
          to: payload.to,
          duration: Math.min(5000, (payload.text || "").length * 100),
          isGroup: payload.is_group,
        });

        const response = await whatsapp.sendVideo({
          sessionId: payload.session,
          to: payload.to,
          text: payload.text || "",
          media: payload.video_url,
          isGroup: payload.is_group,
        });
        recordOutgoingMessage({
          session: payload.session,
          id: (response as any)?.key?.id,
          to: payload.to,
          preview: payload.text || "[video]",
          category: "video",
        });

        return c.json({
          data: response,
        });
      }
    )
    /**
     *
     * POST /message/send-sticker
     *
     */
    .post(
      "/send-sticker",
      createKeyMiddleware(),
      requestValidator(
        "json",
        sendMessageSchema.merge(
          z.object({
            image_url: z.string(),
          })
        )
      ),
      async (c) => {
        const payload = c.req.valid("json");
        const isExist = await whatsapp.getSessionById(payload.session);
        if (!isExist) {
          throw new HTTPException(400, {
            message: "Session does not exist",
          });
        }

        await applyAntiSpam(payload.session, payload.to);

        const response = await whatsapp.sendSticker({
          sessionId: payload.session,
          to: payload.to,
          media: payload.image_url,
          isGroup: payload.is_group,
        });
        recordOutgoingMessage({
          session: payload.session,
          id: (response as any)?.key?.id,
          to: payload.to,
          preview: "[sticker]",
          category: "sticker",
        });

        return c.json({
          data: response,
        });
      }
    );

  return app;
};
