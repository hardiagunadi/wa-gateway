const fs = require("node:fs");
const path = require("node:path");

const target = path.join(
  __dirname,
  "..",
  "node_modules",
  "wa-multi-session",
  "dist",
  "Whatsapp",
  "Whatsapp.js"
);

if (!fs.existsSync(target)) {
  process.exit(0);
}

let source = fs.readFileSync(target, "utf8");

source = source.replace(
  "this.deleteSession(sessionId);\n                                    (_h = this.callback.get(Defaults_1.CALLBACK_KEY.ON_DISCONNECTED)) === null || _h === void 0 ? void 0 : _h(sessionId);",
  `if (code == baileys_1.DisconnectReason.loggedOut) {
                                        yield this.deleteSession(sessionId);
                                    } else {
                                        const staleSession = this.sessions.get(sessionId);
                                        staleSession === null || staleSession === void 0 ? void 0 : staleSession.sock.end(undefined);
                                        this.sessions.delete(sessionId);
                                    }
                                    const disconnectDetails = {
                                        code,
                                        reason: baileys_1.DisconnectReason[code] || "unknown",
                                        message: lastDisconnect === null || lastDisconnect === void 0 ? void 0 : lastDisconnect.error?.message,
                                    };
                                    (_h = this.callback.get(Defaults_1.CALLBACK_KEY.ON_DISCONNECTED)) === null || _h === void 0 ? void 0 : _h(sessionId, disconnectDetails);`
);

source = source.replace(
  "(_j = options.onDisconnected) === null || _j === void 0 ? void 0 : _j.call(options);",
  "(_j = options.onDisconnected) === null || _j === void 0 ? void 0 : _j.call(options, disconnectDetails);"
);

fs.writeFileSync(target, source);
