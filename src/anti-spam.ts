import { getSessionWebhookConfig } from "./session-config";

/**
 * In-memory state untuk anti-spam per session.
 * Direset saat server restart (in-memory by design untuk performa).
 */
interface SessionAntiSpamState {
  /** Timestamps pengiriman pesan dalam satu menit terakhir */
  sentTimestamps: number[];
  /** Map: recipient -> timestamp terakhir pesan dikirim ke recipient ini */
  lastSentToRecipient: Map<string, number>;
  /** Timestamp pesan terakhir dikirim (untuk jeda antar pesan) */
  lastSentAt: number;
}

const stateMap = new Map<string, SessionAntiSpamState>();

const getState = (sessionId: string): SessionAntiSpamState => {
  if (!stateMap.has(sessionId)) {
    stateMap.set(sessionId, {
      sentTimestamps: [],
      lastSentToRecipient: new Map(),
      lastSentAt: 0,
    });
  }
  return stateMap.get(sessionId)!;
};

const sleep = (ms: number) => new Promise((resolve) => setTimeout(resolve, ms));

/**
 * Terapkan aturan anti-spam sebelum mengirim pesan.
 * Fungsi ini TIDAK pernah menolak — pesan selalu diantrekan dan menunggu
 * hingga semua batasan terpenuhi:
 *
 * 1. Interval penerima yang sama → tunggu sisa waktu cooldown
 * 2. Jeda minimum antar pesan   → tunggu sisa jeda sejak pesan terakhir
 * 3. Rate limit per menit       → tunggu hingga slot tersedia
 */
export const applyAntiSpam = async (
  sessionId: string,
  recipient: string
): Promise<void> => {
  const cfg = await getSessionWebhookConfig(sessionId);

  if (!cfg.antiSpamEnabled) {
    return;
  }

  const maxPerMinute = cfg.antiSpamMaxPerMinute ?? 20;
  const delayMs = cfg.antiSpamDelayMs ?? 1000;
  const intervalSeconds = cfg.antiSpamIntervalSeconds ?? 0;

  const state = getState(sessionId);

  // 1. Tunggu interval penerima yang sama (jika diaktifkan)
  if (intervalSeconds > 0) {
    const lastSent = state.lastSentToRecipient.get(recipient) ?? 0;
    if (lastSent > 0) {
      const elapsed = Date.now() - lastSent;
      const waitMs = intervalSeconds * 1000 - elapsed;
      if (waitMs > 0) {
        await sleep(waitMs);
      }
    }
  }

  // 2. Tunggu jeda minimum antar pesan
  if (delayMs > 0 && state.lastSentAt > 0) {
    const sinceLastSent = Date.now() - state.lastSentAt;
    if (sinceLastSent < delayMs) {
      await sleep(delayMs - sinceLastSent);
    }
  }

  // 3. Tunggu jika rate limit per menit tercapai
  // Loop karena setelah sleep mungkin masih ada antrian lain yang mengisi slot
  while (true) {
    const oneMinuteAgo = Date.now() - 60_000;
    state.sentTimestamps = state.sentTimestamps.filter((t) => t > oneMinuteAgo);

    if (state.sentTimestamps.length < maxPerMinute) {
      break;
    }

    // Tunggu hingga slot paling lama expired
    const oldest = state.sentTimestamps[0];
    const waitMs = oldest + 60_000 - Date.now() + 50;
    if (waitMs > 0) {
      await sleep(waitMs);
    }
  }

  // 4. Catat pengiriman
  const sendTime = Date.now();
  state.sentTimestamps.push(sendTime);
  state.lastSentAt = sendTime;
  state.lastSentToRecipient.set(recipient, sendTime);
};

/**
 * Reset state anti-spam untuk session tertentu.
 * Dipanggil saat session dihapus/restart.
 */
export const resetAntiSpamState = (sessionId: string): void => {
  stateMap.delete(sessionId);
};
