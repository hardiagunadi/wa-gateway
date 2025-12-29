import type { MessageReceived } from "wa-multi-session";

const baseMediaPath = "./media/";

const hasMediaKey = (media?: { mediaKey?: Uint8Array | null }) =>
  Boolean(media?.mediaKey && media.mediaKey.length > 0);

export const handleWebhookImageMessage = async (message: MessageReceived) => {
  const image = message.message?.imageMessage;
  if (image && hasMediaKey(image)) {
    const baseMediaName = `${message.key.id}`;

    const fileName = `${baseMediaName}.jpg`;
    await message.saveImage(baseMediaPath + fileName);
    return fileName;
  }
  return null;
};

export const handleWebhookVideoMessage = async (message: MessageReceived) => {
  const video = message.message?.videoMessage;
  if (video && hasMediaKey(video)) {
    const baseMediaName = `${message.key.id}`;

    const fileName = `${baseMediaName}.mp4`;
    await message.saveVideo(baseMediaPath + fileName);
    return fileName;
  }
  return null;
};

export const handleWebhookDocumentMessage = async (
  message: MessageReceived
) => {
  const document = message.message?.documentMessage;
  if (document && hasMediaKey(document)) {
    const baseMediaName = `${message.key.id}`;

    const fileName = `${baseMediaName}`;
    await message.saveDocument(baseMediaPath + fileName);
    return fileName;
  }
  return null;
};

export const handleWebhookAudioMessage = async (message: MessageReceived) => {
  const audio = message.message?.audioMessage;
  if (audio && hasMediaKey(audio)) {
    const baseMediaName = `${message.key.id}`;

    const fileName = `${baseMediaName}.mp3`;
    await message.saveAudio(baseMediaPath + fileName);
    return fileName;
  }
  return null;
};
