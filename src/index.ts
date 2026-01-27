import { Hono } from "hono";
import { logger } from "hono/logger";
import { cors } from "hono/cors";
import moment from "moment";
import { globalErrorMiddleware } from "./middlewares/error.middleware";
import { notFoundMiddleware } from "./middlewares/notfound.middleware";
import { serve } from "@hono/node-server";
import { env } from "./env";
import { createSessionController } from "./controllers/session";
import { createMessageController } from "./controllers/message";
import { createProfileController } from "./controllers/profile";
import { serveStatic } from "@hono/node-server/serve-static";
import { createHealthController } from "./controllers/health";
import { createGroupController } from "./controllers/group";
import {
  createWaGatewayCompatController,
  createWaGatewayCompatV2Controller,
} from "./controllers/wa-gateway";
import { startWaGatewayScheduler, stopWaGatewayScheduler } from "./wa-gateway/scheduler";

const app = new Hono();

app.use(
  logger((...params) => {
    params.map((e) => console.log(`${moment().toISOString()} | ${e}`));
  })
);
app.use(cors());

app.onError(globalErrorMiddleware);
app.notFound(notFoundMiddleware);

/**
 * serve media message static files
 */
app.use(
  "/media/*",
  serveStatic({
    root: "./",
  })
);

/**
 * session routes
 */
app.route("/", createSessionController());
/**
 * message routes
 */
app.route("/", createMessageController());
/**
 * profile routes
 */
app.route("/", createProfileController());

/**
 * health routes
 */
app.route("/", createHealthController());

/**
 * group routes
 */
app.route("/", createGroupController());

/**
 * wa-gateway-compatible API (v1 + v2)
 */
app.route("/", createWaGatewayCompatController());
app.route("/", createWaGatewayCompatV2Controller());

const port = env.PORT;

startWaGatewayScheduler();

const server = serve(
  {
    fetch: app.fetch,
    port,
  },
  (info) => {
    console.log(`Server is running on http://localhost:${info.port}`);
  }
);

// Graceful shutdown handling
let isShuttingDown = false;

const gracefulShutdown = (signal: string) => {
  if (isShuttingDown) return;
  isShuttingDown = true;

  console.log(`\n${signal} received. Starting graceful shutdown...`);

  // Stop the scheduler first
  stopWaGatewayScheduler();

  // Close the server
  server.close((err) => {
    if (err) {
      console.error("Error during server close:", err);
      process.exit(1);
    }
    console.log("Server closed successfully");
    process.exit(0);
  });

  // Force exit after 10 seconds if graceful shutdown fails
  setTimeout(() => {
    console.error("Forced shutdown after timeout");
    process.exit(1);
  }, 10000);
};

process.on("SIGTERM", () => gracefulShutdown("SIGTERM"));
process.on("SIGINT", () => gracefulShutdown("SIGINT"));

// Handle uncaught exceptions and unhandled rejections
process.on("uncaughtException", (err) => {
  console.error("Uncaught Exception:", err);
  gracefulShutdown("uncaughtException");
});

process.on("unhandledRejection", (reason, promise) => {
  console.error("Unhandled Rejection at:", promise, "reason:", reason);
});
