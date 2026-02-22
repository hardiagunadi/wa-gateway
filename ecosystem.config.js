const path = require("path");

const rootDir = __dirname;

module.exports = {
  apps: [
    {
      name: "wa-gateway",
      script: "src/index.ts",
      interpreter: "node",
      interpreter_args: "--import ./node_modules/tsx/dist/loader.mjs",
      cwd: rootDir,
      exec_mode: "fork",
      instances: 1,
      autorestart: true,
      watch: false,
      max_memory_restart: "500M",
      env: {
        NODE_ENV: "production",
      },
      // Restart strategies
      exp_backoff_restart_delay: 100,
      max_restarts: 10,
      min_uptime: "10s",
      // Logging
      error_file: path.join(rootDir, "logs/pm2-error.log"),
      out_file: path.join(rootDir, "logs/pm2-out.log"),
      merge_logs: true,
      log_date_format: "YYYY-MM-DD HH:mm:ss Z",
      // Graceful shutdown
      kill_timeout: 10000,
      wait_ready: false,
      listen_timeout: 10000,
    },
  ],
};
