#!/usr/bin/env bash
# Wrapper script for pm2 that ensures correct PATH for node/pm2
# Used by the panel (www-data user) to control PM2 via sudo -u deploy

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
NODE_BIN_DIR="$(dirname "$(readlink -f "$(command -v node 2>/dev/null || echo /usr/bin/node)")")"

# Source deploy user's fnm environment if available
if [[ -f "$HOME/.bashrc" ]]; then
    source "$HOME/.bashrc" 2>/dev/null
fi

# Ensure node is in PATH
export PATH="${NODE_BIN_DIR}:${PATH}"

# Set PM2_HOME explicitly for the deploy user
export PM2_HOME="${HOME}/.pm2"

exec pm2 "$@"
