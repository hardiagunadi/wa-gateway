import {
  listSchedules,
  updateSchedule,
  type WaGatewayScheduleRecord,
} from "./store";
import {
  sendV2Audio,
  sendV2Document,
  sendV2Image,
  sendV2Link,
  sendV2Location,
  sendV2Text,
  sendV2Video,
  sendV2List,
} from "./sender";

let started = false;

async function processDueSchedules() {
  const all = await listSchedules();
  const now = Date.now();
  const due = all
    .filter((s) => s.status === "pending" && s.scheduledAtMs <= now)
    .slice(0, 50);
  for (const record of due) {
    await trySendSchedule(record);
  }
}

async function trySendSchedule(record: WaGatewayScheduleRecord) {
  try {
    let res: any = null;
    const to = record.phone;
    const isGroup = record.isGroup;

    if (record.category === "text") {
      res = await sendV2Text({
        sessionId: record.sessionId,
        phone: to,
        message: String(record.payload.text ?? record.payload.message ?? ""),
        isGroup,
      });
    } else if (record.category === "image") {
      res = await sendV2Image({
        sessionId: record.sessionId,
        phone: to,
        image: String(record.payload.url ?? record.payload.image ?? ""),
        caption: record.payload.text ?? record.payload.caption,
        isGroup,
      });
    } else if (record.category === "video") {
      res = await sendV2Video({
        sessionId: record.sessionId,
        phone: to,
        video: String(record.payload.url ?? record.payload.video ?? ""),
        caption: record.payload.text ?? record.payload.caption,
        isGroup,
      });
    } else if (record.category === "audio") {
      res = await sendV2Audio({
        sessionId: record.sessionId,
        phone: to,
        audio: String(record.payload.url ?? record.payload.audio ?? ""),
        ptt: record.payload.ptt,
        isGroup,
      });
    } else if (record.category === "document") {
      res = await sendV2Document({
        sessionId: record.sessionId,
        phone: to,
        document: String(record.payload.url ?? record.payload.document ?? ""),
        filename: record.payload.filename ?? record.payload.document_name,
        caption: record.payload.text ?? record.payload.caption,
        isGroup,
      });
    } else if (record.category === "location") {
      res = await sendV2Location({
        sessionId: record.sessionId,
        phone: to,
        message: {
          name: record.payload.name,
          address: record.payload.address,
          latitude: Number(record.payload.latitude),
          longitude: Number(record.payload.longitude),
        },
        isGroup,
      });
    } else if (record.category === "link") {
      res = await sendV2Link({
        sessionId: record.sessionId,
        phone: to,
        text: record.payload.text,
        link: record.payload.link,
        isGroup,
      });
    } else if (record.category === "list") {
      res = await sendV2List({
        sessionId: record.sessionId,
        phone: to,
        message: record.payload.message,
        isGroup,
      });
    } else {
      await updateSchedule(record.token, record.id, {
        status: "failed",
        error: `Unsupported category: ${record.category}`,
      });
      return;
    }

    await updateSchedule(record.token, record.id, {
      status: "sent",
      messageId: res?.key?.id ?? res?.key?.id ?? null,
      error: null,
    });
  } catch (err: any) {
    await updateSchedule(record.token, record.id, {
      status: "failed",
      error: err?.message ? String(err.message) : "Failed to send schedule",
    });
  }
}

export const startWaGatewayScheduler = (intervalMs = 1000) => {
  if (started) return;
  started = true;
  setInterval(() => {
    processDueSchedules().catch((err) =>
      console.error("wa-gateway scheduler error", err)
    );
  }, intervalMs);
};
