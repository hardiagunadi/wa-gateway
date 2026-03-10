#!/usr/bin/env bash
set -euo pipefail

PM2_BIN="${PM2_BIN:-/usr/bin/pm2}"
APP_NAME="${APP_NAME:-wa-gateway}"

TS="$(date -u '+%Y-%m-%dT%H:%M:%SZ')"
echo "[$TS] Starting weekly PM2 log flush for ${APP_NAME}"

if "$PM2_BIN" flush "$APP_NAME"; then
  echo "[$TS] Flush successful for ${APP_NAME}"
else
  echo "[$TS] Flush by app name failed, fallback to global flush"
  "$PM2_BIN" flush
  echo "[$TS] Global flush successful"
fi
