#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
WEB_GROUP="www-data"
RUN_USER="$(id -un)"
APT_UPDATED=0

log(){ echo -e "\033[1;32m[+]\033[0m $*"; }
err(){ echo -e "\033[1;31m[-]\033[0m $*"; exit 1; }

require_non_root(){
  [[ $EUID -eq 0 ]] && err "Jangan jalankan sebagai root."
  sudo -v
}

require_ubuntu(){
  . /etc/os-release
  [[ "${ID:-}" != "ubuntu" ]] && err "Script ini hanya untuk Ubuntu."
}

apt_update(){
  if [[ $APT_UPDATED -eq 0 ]]; then
    sudo apt-get update
    APT_UPDATED=1
  fi
}

install_node(){
  if ! command -v node >/dev/null; then
    log "Install Node 20"
    apt_update
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt-get install -y nodejs
  fi
}

install_pm2(){
  if ! command -v pm2 >/dev/null; then
    log "Install PM2"
    sudo npm install -g pm2
  fi
}

setup_sudoers(){
  local pm2_bin
  pm2_bin="$(readlink -f "$(command -v pm2)")"
  local sudoers_file="/etc/sudoers.d/wa-gateway-single"

  log "Setup sudoers (www-data → ${RUN_USER})"

  sudo tee "$sudoers_file" > /dev/null <<EOF
${WEB_GROUP} ALL=(${RUN_USER}) NOPASSWD: ${pm2_bin} *
EOF

  sudo chmod 0440 "$sudoers_file"
  sudo visudo -cf "$sudoers_file" || {
    sudo rm -f "$sudoers_file"
    err "Sudoers invalid."
  }
}

setup_pm2(){
  cd "$REPO_DIR"

  log "Start PM2 gateway"
  pm2 start ecosystem.config.js --name wa-gateway || true

  log "Setup auto-start"
  HOME_DIR="$(getent passwd "${RUN_USER}" | cut -d: -f6)"
  STARTUP_CMD="$(pm2 startup systemd -u "${RUN_USER}" --hp "${HOME_DIR}" | grep sudo | head -n1 || true)"

  [[ -n "$STARTUP_CMD" ]] && eval "$STARTUP_CMD"

  pm2 save
}

main(){
  require_non_root
  require_ubuntu
  install_node
  install_pm2
  setup_sudoers
  setup_pm2

  log "===================================="
  log "Single-user install selesai."
  log "User PM2: ${RUN_USER}"
  log "===================================="
}

main "$@"