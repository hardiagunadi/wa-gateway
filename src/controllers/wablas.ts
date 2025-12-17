import { Hono } from "hono";
import { HTTPException } from "hono/http-exception";
import { z } from "zod";
import crypto from "crypto";
import fs from "fs/promises";
import path from "path";
import { requestValidator } from "../middlewares/validation.middleware";
import { createKeyMiddleware } from "../middlewares/key.middleware";
import { whatsapp } from "../whatsapp";
import { getWablasToken } from "../wablas/auth";
import {
  deleteDeviceBySessionId,
  deleteDeviceByToken,
  generateToken,
  getDeviceByToken,
  upsertDevice,
} from "../wablas/registry";
import { getSessionWebhookConfig } from "../session-config";
import { addStoredSession, removeStoredSession } from "../session-store";
import {
  normalizeGroupId,
  sendV2Audio,
  sendV2Document,
  sendV2Image,
  sendV2Link,
  sendV2List,
  sendV2Location,
  sendV2Text,
  sendV2Video,
  truthy,
} from "../wablas/sender";
import {
  addAutoreplyRule,
  addSchedules,
  cancelSchedules,
  deleteAutoreplyRule,
  findAutoreplyByKeyword,
  getContactByPhone,
  listContacts,
  upsertContacts,
  updateAutoreplyRule,
  updateSchedule,
  WablasScheduleCategory,
  type WablasScheduleRecord,
} from "../wablas/store";

const requireToken = (token: string | null) => {
  if (!token) {
    throw new HTTPException(400, {
      message:
        "Missing token. Provide token via query `token` or header `Authorization`.",
    });
  }
  return token;
};

const resolveSessionIdFromToken = async (token: string) => {
  const device = await getDeviceByToken(token);
  if (!device) {
    throw new HTTPException(404, { message: "Device token not found" });
  }
  return device.sessionId;
};

const parseScheduledAtMs = (value: string) => {
  const raw = (value || "").trim();
  if (!raw) return NaN;
  const m = raw.match(
    /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})$/
  );
  if (m) {
    const [, y, mo, d, h, mi, s] = m;
    return new Date(
      Number(y),
      Number(mo) - 1,
      Number(d),
      Number(h),
      Number(mi),
      Number(s)
    ).getTime();
  }
  return new Date(raw).getTime();
};

const createWablasDeviceRouter = () => {
  const app = new Hono();

  app.post(
    "/device/create",
    requestValidator(
      "form",
      z.object({
        name: z.string().optional(),
        phone: z.string().optional(),
        product: z.string().optional(),
        bank: z.string().optional(),
        periode: z.string().optional(),
      })
    ),
    async (c) => {
      const payload = c.req.valid("form");
      const token = generateToken();
      const sessionId = (payload.phone || `device-${token.slice(0, 6)}`).trim();

      await upsertDevice({
        token,
        sessionId,
        name: payload.name,
        phone: payload.phone,
        createdAt: new Date().toISOString(),
      });

      await addStoredSession(sessionId);

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

      return c.json({
        status: true,
        message: "create device successfully",
        data: {
          device_name: payload.name || sessionId,
          device: sessionId,
          token,
          qr,
        },
      });
    }
  );

  app.post("/device/delete", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    await whatsapp.deleteSession(sessionId);
    await deleteDeviceByToken(token);
    await deleteDeviceBySessionId(sessionId);
    await removeStoredSession(sessionId);
    return c.json({ status: true, message: "device deleted" });
  });

  app.post("/device/disconnect", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    await whatsapp.deleteSession(sessionId);
    return c.json({ status: true, message: "device disconnected" });
  });

  app.get("/device/info", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const session = await whatsapp.getSessionById(sessionId);
    const status = (session as any)?.status || "disconnected";
    const userId = (session as any)?.sock?.user?.id || null;
    const config = await getSessionWebhookConfig(sessionId);

    return c.json({
      status: "success",
      message: "Success",
      data: [
        {
          phone: userId,
          device: sessionId,
          status: status === "connected" ? "online" : "offline",
          webhook_url: config.webhookBaseUrl || null,
        },
      ],
    });
  });

  app.get("/device/scan", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);

    const existing = await whatsapp.getSessionById(sessionId);
    if (existing) {
      throw new HTTPException(400, { message: "Session already exist" });
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

    return c.json({
      status: true,
      message: "Success",
      data: {
        device: sessionId,
        qr,
      },
    });
  });

  return app;
};

const createWablasLegacyRouter = () => {
  const app = new Hono().use(createKeyMiddleware());
  app.route("/", createWablasDeviceRouter());

  /**
   * Messaging (legacy /api/*)
   * Supports GET params or POST body.
   */
  const sendTextSchema = z.object({
    phone: z.string(),
    message: z.string(),
    isGroup: z.union([z.string(), z.boolean()]).optional(),
    ref_id: z.string().optional(),
  });

  app.all("/send-message", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);

    const params =
      c.req.method === "GET"
        ? c.req.query()
        : ((await c.req.parseBody()) as Record<string, any>);

    const parsed = sendTextSchema.safeParse(params);
    if (!parsed.success) {
      throw new HTTPException(400, { message: "Invalid payload" });
    }

    const response = await whatsapp.sendText({
      sessionId,
      to: parsed.data.phone,
      text: parsed.data.message,
      isGroup: truthy(parsed.data.isGroup),
    });

    return c.json({
      status: true,
      message: "Success",
      data: {
        phone: parsed.data.phone,
        message: parsed.data.message,
        ref_id: parsed.data.ref_id ?? null,
        id: (response as any)?.key?.id ?? null,
      },
    });
  });

  const sendMediaSchema = z.object({
    phone: z.string(),
    caption: z.string().optional(),
    isGroup: z.union([z.string(), z.boolean()]).optional(),
  });

  app.post("/send-image", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = (await c.req.parseBody()) as Record<string, any>;
    const parsed = sendMediaSchema.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });
    const image = body.image || body.url;
    if (!image) throw new HTTPException(400, { message: "image is required" });
    const res = await whatsapp.sendImage({
      sessionId,
      to: parsed.data.phone,
      text: parsed.data.caption,
      media: image,
      isGroup: truthy(parsed.data.isGroup),
    });
    return c.json({
      status: true,
      message: "Success",
      data: { id: (res as any)?.key?.id ?? null },
    });
  });

  app.post("/send-video", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = (await c.req.parseBody()) as Record<string, any>;
    const phone = body.phone;
    const video = body.video || body.url;
    if (!phone || !video)
      throw new HTTPException(400, { message: "phone and video required" });
    const res = await whatsapp.sendVideo({
      sessionId,
      to: phone,
      text: body.caption || body.message,
      media: video,
      isGroup: truthy(body.isGroup),
    });
    return c.json({
      status: true,
      message: "Success",
      data: { id: (res as any)?.key?.id ?? null },
    });
  });

  app.post("/send-document", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = (await c.req.parseBody()) as Record<string, any>;
    const phone = body.phone;
    const document = body.document || body.url;
    const filename = body.filename || body.document_name || "document.pdf";
    if (!phone || !document)
      throw new HTTPException(400, { message: "phone and document required" });
    const res = await whatsapp.sendDocument({
      sessionId,
      to: phone,
      text: body.caption || body.message,
      media: document,
      filename,
      isGroup: truthy(body.isGroup),
    });
    return c.json({
      status: true,
      message: "Success",
      data: { id: (res as any)?.key?.id ?? null },
    });
  });

  app.post("/send-audio", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = (await c.req.parseBody()) as Record<string, any>;
    const phone = body.phone;
    const audio = body.audio || body.url;
    if (!phone || !audio)
      throw new HTTPException(400, { message: "phone and audio required" });
    const res = await whatsapp.sendAudio({
      sessionId,
      to: phone,
      media: audio,
      asVoiceNote: truthy(body.ptt || body.asVoiceNote),
      isGroup: truthy(body.isGroup),
    } as any);
    return c.json({
      status: true,
      message: "Success",
      data: { id: (res as any)?.key?.id ?? null },
    });
  });

  const notImplemented = (name: string) => (c: any) =>
    c.json(
      {
        status: false,
        message: `Not implemented in wa-gateway: ${name}`,
      },
      501
    );

  app.all("/report/message", notImplemented("report/message"));
  app.all("/report-realtime", notImplemented("report-realtime"));
  app.all("/resend-message", notImplemented("resend-message"));
  app.all("/cancel-message", notImplemented("cancel-message"));
  app.all("/cancel-all-message", notImplemented("cancel-all-message"));
  app.all("/revoke-message", notImplemented("revoke-message"));
  app.all("/upload/:type", notImplemented("upload"));
  app.all("/reminder", notImplemented("reminder"));
  app.all("/reminder/:id", notImplemented("reminder/:id"));
  app.all("/reminder/info/:id", notImplemented("reminder/info/:id"));
  app.all("/group/add", notImplemented("group/add"));
  app.all("/group/delete-phone", notImplemented("group/delete-phone"));
  app.all("/blacklist", notImplemented("blacklist"));
  app.all("/blacklist/cancel", notImplemented("blacklist/cancel"));
  app.all("/create-agent", notImplemented("create-agent"));
  app.all("/assign-agent", notImplemented("assign-agent"));
  app.all("/schedule", notImplemented("schedule"));
  app.all("/schedule/:id", notImplemented("schedule/:id"));
  app.all("/schedule-cancel/:id", notImplemented("schedule-cancel/:id"));
  app.all("/delete-schedule", notImplemented("delete-schedule"));
  app.all("/contact", notImplemented("contact"));
  app.all("/contact/update", notImplemented("contact/update"));
  app.all("/channel/:channel", notImplemented("channel"));

  return app;
};

const createWablasV2Router = () => {
  const app = new Hono().use(createKeyMiddleware());
  app.route("/", createWablasDeviceRouter());

  const bulkBase = z.object({
    data: z.array(z.any()).min(1),
  });

  const respondBulk = (
    c: any,
    message: string,
    messages: Array<Record<string, any>>
  ) =>
    c.json({
      status: true,
      message,
      data: { messages },
    });

  app.post("/send-message", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const itemSchema = z.object({
      phone: z.string(),
      message: z.string(),
      isGroup: z.any().optional(),
      ref_id: z.string().optional(),
    });

    const results: any[] = [];
    for (const raw of parsed.data.data) {
      const item = itemSchema.safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }
      try {
        const res = await sendV2Text({
          sessionId,
          phone: item.data.phone,
          message: item.data.message,
          isGroup: item.data.isGroup,
        });
        results.push({
          id: (res as any)?.key?.id ?? null,
          phone: item.data.phone,
          message: item.data.message,
          status: "sent",
          ref_id: item.data.ref_id ?? null,
        });
      } catch (err: any) {
        results.push({
          id: null,
          phone: item.data.phone,
          message: item.data.message,
          status: "failed",
          error: err?.message ?? "Failed to send",
          ref_id: item.data.ref_id ?? null,
        });
      }
    }

    return respondBulk(c, "Message processed", results);
  });

  const mediaItemSchema = z.object({
    phone: z.string(),
    caption: z.string().optional(),
    isGroup: z.any().optional(),
    ref_id: z.string().optional(),
    url: z.string().optional(),
  });

  app.post("/send-image", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const results: any[] = [];
    for (const raw of parsed.data.data) {
      const item = mediaItemSchema.extend({ image: z.string().optional() }).safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }
      const image = (raw as any)?.image || item.data.url;
      if (!image) {
        results.push({ status: "failed", phone: item.data.phone, error: "image is required" });
        continue;
      }
      try {
        const res = await sendV2Image({
          sessionId,
          phone: item.data.phone,
          image,
          caption: item.data.caption,
          isGroup: item.data.isGroup,
        });
        results.push({
          id: (res as any)?.key?.id ?? null,
          phone: item.data.phone,
          caption: item.data.caption ?? null,
          image,
          status: "sent",
          ref_id: item.data.ref_id ?? null,
        });
      } catch (err: any) {
        results.push({
          id: null,
          phone: item.data.phone,
          status: "failed",
          error: err?.message ?? "Failed to send",
          ref_id: item.data.ref_id ?? null,
        });
      }
    }
    return respondBulk(c, "Message processed", results);
  });

  app.post("/send-video", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const results: any[] = [];
    for (const raw of parsed.data.data) {
      const item = mediaItemSchema.extend({ video: z.string().optional() }).safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }
      const video = (raw as any)?.video || item.data.url;
      if (!video) {
        results.push({ status: "failed", phone: item.data.phone, error: "video is required" });
        continue;
      }
      try {
        const res = await sendV2Video({
          sessionId,
          phone: item.data.phone,
          video,
          caption: item.data.caption,
          isGroup: item.data.isGroup,
        });
        results.push({
          id: (res as any)?.key?.id ?? null,
          phone: item.data.phone,
          caption: item.data.caption ?? null,
          video,
          status: "sent",
          ref_id: item.data.ref_id ?? null,
        });
      } catch (err: any) {
        results.push({
          id: null,
          phone: item.data.phone,
          status: "failed",
          error: err?.message ?? "Failed to send",
          ref_id: item.data.ref_id ?? null,
        });
      }
    }
    return respondBulk(c, "Message processed", results);
  });

  app.post("/send-document", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const results: any[] = [];
    const schema = mediaItemSchema.extend({
      document: z.string().optional(),
      filename: z.string().optional(),
      document_name: z.string().optional(),
    });
    for (const raw of parsed.data.data) {
      const item = schema.safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }
      const document = (raw as any)?.document || item.data.url;
      if (!document) {
        results.push({
          status: "failed",
          phone: item.data.phone,
          error: "document is required",
        });
        continue;
      }
      try {
        const res = await sendV2Document({
          sessionId,
          phone: item.data.phone,
          document,
          filename: item.data.filename || item.data.document_name,
          caption: item.data.caption,
          isGroup: item.data.isGroup,
        });
        results.push({
          id: (res as any)?.key?.id ?? null,
          phone: item.data.phone,
          caption: item.data.caption ?? null,
          document,
          status: "sent",
          ref_id: item.data.ref_id ?? null,
        });
      } catch (err: any) {
        results.push({
          id: null,
          phone: item.data.phone,
          status: "failed",
          error: err?.message ?? "Failed to send",
          ref_id: item.data.ref_id ?? null,
        });
      }
    }
    return respondBulk(c, "Message processed", results);
  });

  app.post("/send-audio", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const results: any[] = [];
    const schema = mediaItemSchema.extend({ audio: z.string().optional(), ptt: z.any().optional() });
    for (const raw of parsed.data.data) {
      const item = schema.safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }
      const audio = (raw as any)?.audio || item.data.url;
      if (!audio) {
        results.push({ status: "failed", phone: item.data.phone, error: "audio is required" });
        continue;
      }
      try {
        const res = await sendV2Audio({
          sessionId,
          phone: item.data.phone,
          audio,
          ptt: (raw as any)?.ptt,
          isGroup: item.data.isGroup,
        });
        results.push({
          id: (res as any)?.key?.id ?? null,
          phone: item.data.phone,
          audio,
          status: "sent",
          ref_id: item.data.ref_id ?? null,
        });
      } catch (err: any) {
        results.push({
          id: null,
          phone: item.data.phone,
          status: "failed",
          error: err?.message ?? "Failed to send",
          ref_id: item.data.ref_id ?? null,
        });
      }
    }
    return respondBulk(c, "Message processed", results);
  });

  app.post("/send-link", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const itemSchema = z.object({
      phone: z.string(),
      message: z.object({ text: z.string().optional(), link: z.string() }),
      isGroup: z.any().optional(),
      ref_id: z.string().optional(),
    });

    const results: any[] = [];
    for (const raw of parsed.data.data) {
      const item = itemSchema.safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }
      try {
        const res = await sendV2Link({
          sessionId,
          phone: item.data.phone,
          text: item.data.message.text,
          link: item.data.message.link,
          isGroup: item.data.isGroup,
        });
        results.push({
          id: (res as any)?.key?.id ?? null,
          phone: item.data.phone,
          status: "sent",
          ref_id: item.data.ref_id ?? null,
        });
      } catch (err: any) {
        results.push({
          id: null,
          phone: item.data.phone,
          status: "failed",
          error: err?.message ?? "Failed to send",
          ref_id: item.data.ref_id ?? null,
        });
      }
    }
    return respondBulk(c, "Message processed", results);
  });

  app.post("/send-list", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const itemSchema = z.object({
      phone: z.string(),
      message: z.object({
        title: z.string().optional(),
        description: z.string().optional(),
        buttonText: z.string().optional(),
        footer: z.string().optional(),
        lists: z
          .array(z.object({ title: z.string(), description: z.string().optional() }))
          .min(1),
      }),
      isGroup: z.any().optional(),
      ref_id: z.string().optional(),
    });

    const results: any[] = [];
    for (const raw of parsed.data.data) {
      const item = itemSchema.safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }
      try {
        const res = await sendV2List({
          sessionId,
          phone: item.data.phone,
          message: item.data.message as any,
          isGroup: item.data.isGroup,
        });
        results.push({
          id: (res as any)?.key?.id ?? null,
          phone: item.data.phone,
          status: "sent",
          ref_id: item.data.ref_id ?? null,
        });
      } catch (err: any) {
        results.push({
          id: null,
          phone: item.data.phone,
          status: "failed",
          error: err?.message ?? "Failed to send",
          ref_id: item.data.ref_id ?? null,
        });
      }
    }
    return respondBulk(c, "Message processed", results);
  });

  app.post("/send-location", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const itemSchema = z.object({
      phone: z.string(),
      message: z.object({
        name: z.string().optional(),
        address: z.string().optional(),
        latitude: z.coerce.number(),
        longitude: z.coerce.number(),
      }),
      isGroup: z.any().optional(),
      ref_id: z.string().optional(),
    });

    const results: any[] = [];
    for (const raw of parsed.data.data) {
      const item = itemSchema.safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }
      try {
        const res = await sendV2Location({
          sessionId,
          phone: item.data.phone,
          message: item.data.message,
          isGroup: item.data.isGroup,
        });
        results.push({
          id: (res as any)?.key?.id ?? null,
          phone: item.data.phone,
          status: "sent",
          ref_id: item.data.ref_id ?? null,
        });
      } catch (err: any) {
        results.push({
          id: null,
          phone: item.data.phone,
          status: "failed",
          error: err?.message ?? "Failed to send",
          ref_id: item.data.ref_id ?? null,
        });
      }
    }
    return respondBulk(c, "Message processed", results);
  });

  /**
   * GROUP (best-effort mapping)
   * Wablas "group" is not WhatsApp group; here `group_id` is treated as WA group id/JID.
   */
  app.get("/group/text", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const groupId = c.req.query("group_id") || c.req.query("phone") || "";
    const message = c.req.query("message") || "";
    if (!groupId || !message) {
      throw new HTTPException(400, { message: "group_id and message required" });
    }
    const res = await sendV2Text({
      sessionId,
      phone: normalizeGroupId(groupId),
      message,
      isGroup: true,
    });
    return c.json({
      status: true,
      message: "Success",
      data: { id: (res as any)?.key?.id ?? null },
    });
  });

  app.post("/group/text", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const itemSchema = z.object({
      group_id: z.string(),
      message: z.string(),
      ref_id: z.string().optional(),
    });
    const results: any[] = [];
    for (const raw of parsed.data.data) {
      const item = itemSchema.safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }
      try {
        const res = await sendV2Text({
          sessionId,
          phone: normalizeGroupId(item.data.group_id),
          message: item.data.message,
          isGroup: true,
        });
        results.push({
          id: (res as any)?.key?.id ?? null,
          group_id: item.data.group_id,
          status: "sent",
          ref_id: item.data.ref_id ?? null,
        });
      } catch (err: any) {
        results.push({
          id: null,
          group_id: item.data.group_id,
          status: "failed",
          error: err?.message ?? "Failed to send",
          ref_id: item.data.ref_id ?? null,
        });
      }
    }

    return respondBulk(c, "Message processed", results);
  });

  const groupMedia = async (
    c: any,
    kind: "image" | "video" | "audio" | "document"
  ) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const itemSchema = z.object({
      group_id: z.string(),
      caption: z.string().optional(),
      url: z.string().optional(),
      image: z.string().optional(),
      video: z.string().optional(),
      audio: z.string().optional(),
      document: z.string().optional(),
      filename: z.string().optional(),
      ref_id: z.string().optional(),
    });

    const results: any[] = [];
    for (const raw of parsed.data.data) {
      const item = itemSchema.safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }

      const to = normalizeGroupId(item.data.group_id);
      try {
        let res: any = null;
        if (kind === "image") {
          const image = item.data.image || item.data.url;
          if (!image) throw new Error("image is required");
          res = await sendV2Image({
            sessionId,
            phone: to,
            image,
            caption: item.data.caption,
            isGroup: true,
          });
        } else if (kind === "video") {
          const video = item.data.video || item.data.url;
          if (!video) throw new Error("video is required");
          res = await sendV2Video({
            sessionId,
            phone: to,
            video,
            caption: item.data.caption,
            isGroup: true,
          });
        } else if (kind === "audio") {
          const audio = item.data.audio || item.data.url;
          if (!audio) throw new Error("audio is required");
          res = await sendV2Audio({
            sessionId,
            phone: to,
            audio,
            isGroup: true,
          });
        } else if (kind === "document") {
          const document = item.data.document || item.data.url;
          if (!document) throw new Error("document is required");
          res = await sendV2Document({
            sessionId,
            phone: to,
            document,
            filename: item.data.filename,
            caption: item.data.caption,
            isGroup: true,
          });
        }

        results.push({
          id: res?.key?.id ?? null,
          group_id: item.data.group_id,
          status: "sent",
          ref_id: item.data.ref_id ?? null,
        });
      } catch (err: any) {
        results.push({
          id: null,
          group_id: item.data.group_id,
          status: "failed",
          error: err?.message ?? "Failed to send",
          ref_id: item.data.ref_id ?? null,
        });
      }
    }

    return respondBulk(c, "Message processed", results);
  };

  app.post("/group/image", (c) => groupMedia(c, "image"));
  app.post("/group/video", (c) => groupMedia(c, "video"));
  app.post("/group/audio", (c) => groupMedia(c, "audio"));
  app.post("/group/document", (c) => groupMedia(c, "document"));

  /**
   * SCHEDULE
   */
  app.post("/schedule", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);

    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const itemSchema = z.object({
      category: z.string(),
      phone: z.string(),
      scheduled_at: z.string(),
      text: z.any().optional(),
      url: z.any().optional(),
      file: z.any().optional(),
      isGroup: z.any().optional(),
    });

    const results: any[] = [];
    const records: WablasScheduleRecord[] = [];

    for (const raw of parsed.data.data) {
      const item = itemSchema.safeParse(raw);
      if (!item.success) {
        results.push({ status: "failed", error: "Invalid item", raw });
        continue;
      }

      const scheduledAtMs = parseScheduledAtMs(item.data.scheduled_at);
      if (!Number.isFinite(scheduledAtMs)) {
        results.push({
          status: "failed",
          phone: item.data.phone,
          error: "Invalid scheduled_at",
        });
        continue;
      }

      const category = item.data.category as WablasScheduleCategory;
      const id = crypto.randomUUID();
      const nowIso = new Date().toISOString();

      const payload =
        category === "location" && typeof item.data.text === "object"
          ? item.data.text
          : {
              text:
                typeof item.data.text === "string"
                  ? item.data.text
                  : item.data.text,
              url: item.data.url ?? item.data.file,
            };

      records.push({
        id,
        token,
        sessionId,
        phone: item.data.phone,
        isGroup: truthy(item.data.isGroup),
        category,
        scheduledAt: item.data.scheduled_at,
        scheduledAtMs,
        payload,
        status: "pending",
        messageId: null,
        error: null,
        createdAt: nowIso,
        updatedAt: nowIso,
      });

      results.push({
        id,
        phone: item.data.phone,
        messages:
          typeof item.data.text === "string"
            ? item.data.text
            : JSON.stringify(item.data.text ?? null),
        file: item.data.url ?? item.data.file ?? null,
        timezone: "Asia/Jakarta",
        schedule_at: item.data.scheduled_at,
      });
    }

    if (records.length) await addSchedules(records);

    return c.json({
      status: true,
      message: "Message is pending and waiting to be processed",
      data: { messages: results },
    });
  });

  app.put("/schedule/:schedule_id", async (c) => {
    const token = requireToken(getWablasToken(c));
    const scheduleId = c.req.param("schedule_id");
    const body = await c.req.json().catch(() => null);
    const parsed = bulkBase.safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const first = parsed.data.data?.[0];
    const itemSchema = z.object({
      category: z.string(),
      phone: z.string(),
      scheduled_at: z.string(),
      text: z.any().optional(),
      url: z.any().optional(),
      file: z.any().optional(),
      isGroup: z.any().optional(),
    });
    const item = itemSchema.safeParse(first);
    if (!item.success) throw new HTTPException(400, { message: "Invalid item" });

    const scheduledAtMs = parseScheduledAtMs(item.data.scheduled_at);
    if (!Number.isFinite(scheduledAtMs)) {
      throw new HTTPException(400, { message: "Invalid scheduled_at" });
    }

    const payload =
      item.data.category === "location" && typeof item.data.text === "object"
        ? item.data.text
        : {
            text: item.data.text,
            url: item.data.url ?? item.data.file,
          };

    const updated = await updateSchedule(token, scheduleId, {
      phone: item.data.phone,
      isGroup: truthy(item.data.isGroup),
      category: item.data.category as WablasScheduleCategory,
      scheduledAt: item.data.scheduled_at,
      scheduledAtMs,
      payload,
      status: "pending",
      error: null,
    });

    if (!updated) throw new HTTPException(404, { message: "Schedule not found" });

    return c.json({
      status: true,
      id: updated.id,
      category: updated.category,
      message: "Scheduled Messages is succesfully Updated",
      timezone: "Asia/Jakarta",
      schedule_at: updated.scheduledAt,
      phone: updated.phone,
      messages:
        typeof (updated as any).payload?.text === "string"
          ? (updated as any).payload.text
          : JSON.stringify((updated as any).payload ?? null),
      file: (updated as any).payload?.url ?? null,
    });
  });

  app.delete("/delete-schedule", async (c) => {
    const token = requireToken(getWablasToken(c));
    const idsRaw = c.req.query("id") || "";
    const ids = idsRaw
      .split(",")
      .map((v) => v.trim())
      .filter(Boolean);
    if (!ids.length) throw new HTTPException(400, { message: "id is required" });
    await cancelSchedules(token, ids);
    return c.json({ status: true, message: "schedules deleted successfully" });
  });

  /**
   * AUTOREPLY
   */
  app.post("/autoreply", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const body = await c.req.json().catch(() => null);
    const parsed = z
      .object({ keyword: z.string(), response: z.string() })
      .safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });

    const rule = await addAutoreplyRule(token, parsed.data);
    return c.json({
      status: true,
      message: "Auto reply created successfully",
      data: {
        id: rule.id,
        device: sessionId,
        keyword: rule.keyword,
        response: rule.response,
      },
    });
  });

  app.put("/autoreply/:id", async (c) => {
    const token = requireToken(getWablasToken(c));
    const sessionId = await resolveSessionIdFromToken(token);
    const id = c.req.param("id");
    const body = await c.req.json().catch(() => null);
    const parsed = z
      .object({ keyword: z.string(), response: z.string() })
      .safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });
    const updated = await updateAutoreplyRule(token, id, parsed.data);
    if (!updated) throw new HTTPException(404, { message: "Not found" });
    return c.json({
      status: true,
      message: `update autoreply keyword: ${updated.keyword} successfully`,
      data: {
        id: updated.id,
        device: sessionId,
        keyword: updated.keyword,
        response: updated.response,
      },
    });
  });

  app.delete("/autoreply/:id", async (c) => {
    const token = requireToken(getWablasToken(c));
    const id = c.req.param("id");
    const ok = await deleteAutoreplyRule(token, id);
    if (!ok) throw new HTTPException(404, { message: "Not found" });
    return c.json({ status: true, message: "remove autoreply successfully" });
  });

  app.get("/autoreply/getData", async (c) => {
    const token = requireToken(getWablasToken(c));
    const keyword = c.req.query("keyword") || "";
    if (!keyword) throw new HTTPException(400, { message: "keyword required" });
    const rules = await findAutoreplyByKeyword(token, keyword);
    return c.json({ status: true, data: rules });
  });

  /**
   * CONTACT
   */
  app.post("/contact", async (c) => {
    const token = requireToken(getWablasToken(c));
    const body = await c.req.json().catch(() => null);
    const parsed = z
      .object({
        data: z.array(
          z.object({
            phone: z.string(),
            name: z.string().optional(),
            email: z.string().optional(),
            address: z.string().optional(),
            birth_day: z.string().optional(),
          })
        ),
      })
      .safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });
    const contacts = await upsertContacts(token, parsed.data.data);
    return c.json({
      status: true,
      message: "Contacts saved successfully",
      data: contacts,
    });
  });

  app.post("/contact/update", async (c) => {
    const token = requireToken(getWablasToken(c));
    const body = await c.req.json().catch(() => null);
    const parsed = z
      .object({
        data: z.array(
          z.object({
            phone: z.string(),
            name: z.string().optional(),
            email: z.string().optional(),
            address: z.string().optional(),
            birth_day: z.string().optional(),
          })
        ),
      })
      .safeParse(body);
    if (!parsed.success) throw new HTTPException(400, { message: "Invalid" });
    const contacts = await upsertContacts(token, parsed.data.data);
    return c.json({
      status: true,
      message: "Contacts updated successfully",
      data: contacts,
    });
  });

  app.get("/contact", async (c) => {
    const token = requireToken(getWablasToken(c));
    const phone = c.req.query("phone");
    if (!phone) {
      const contacts = await listContacts(token);
      return c.json({ status: true, data: contacts });
    }
    const contact = await getContactByPhone(token, phone);
    return c.json({ status: true, data: contact ? [contact] : [] });
  });

  /**
   * MEDIA DELETE (local /media)
   */
  app.delete("/media/delete/:id", async (c) => {
    requireToken(getWablasToken(c));
    const id = c.req.param("id");
    const dir = path.resolve(process.cwd(), "media");
    const files = await fs.readdir(dir).catch(() => []);
    const targets = files.filter((f) => f.includes(id));
    await Promise.all(
      targets.map((f) => fs.unlink(path.join(dir, f)).catch(() => null))
    );
    return c.json({
      status: true,
      message: `media ${id} deleted`,
      deleted: targets,
    });
  });

  /**
   * Not implemented in wa-gateway (v2)
   */
  const notImplemented = (name: string) => (c: any) =>
    c.json(
      { status: false, message: `Not implemented in wa-gateway: ${name}` },
      501
    );

  app.all("/create-agent", notImplemented("create-agent"));

  return app;
};

export const createWablasCompatController = () => {
  const router = createWablasLegacyRouter();
  return new Hono().basePath("/api").route("/", router);
};

export const createWablasCompatV2Controller = () => {
  const router = createWablasV2Router();
  return new Hono().basePath("/api/v2").route("/", router);
};
