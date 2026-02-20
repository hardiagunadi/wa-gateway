import "dotenv/config";
import { z } from "zod";

const schema = z
  .object({
    NODE_ENV: z.enum(["development", "production"]).default("development"),
    KEY: z.string().default(""),
    PORT: z
      .string()
      .default("5001")
      .transform((e) => Number(e)),
    WEBHOOK_BASE_URL: z.string().optional(),
  })
  .superRefine((value, ctx) => {
    if (value.NODE_ENV === "production" && !value.KEY) {
      ctx.addIssue({
        code: z.ZodIssueCode.custom,
        message: "KEY is required in production.",
      });
    }
  });

export const env = schema.parse(process.env);
