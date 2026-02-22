#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <project-user>"
  exit 1
fi

PROJECT_USER="$1"
WEB_GROUP="www-data"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APT_UPDATED=0

log(){ echo -e "\033[1;32m[+]\033[0m $*"; }
err(){ echo -e "\033[1;31m[-]\033[0m $*"; exit 1; }

require_non_root(){
  [[ $EUID -eq 0 ]] && err "Jangan jalankan sebagai root."
  sudo -v
}

require_user_exists(){
  id "$PROJECT_USER" >/dev/null 2>&1 || err "User ${PROJECT_USER} tidak ditemukan."
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

setup_sudoers_scoped(){
  local pm2_bin
  pm2_bin="$(readlink -f "$(command -v pm2)")"
  local sudoers_file="/etc/sudoers.d/wa-gateway-${PROJECT_USER}"

  log "Setup sudoers scoped untuk ${PROJECT_USER}"

  sudo tee "$sudoers_file" > /dev/null <<EOF
${WEB_GROUP} ALL=(${PROJECT_USER}) NOPASSWD: ${pm2_bin} start wa-gateway-${PROJECT_USER}
${WEB_GROUP} ALL=(${PROJECT_USER}) NOPASSWD: ${pm2_bin} stop wa-gateway-${PROJECT_USER}
${WEB_GROUP} ALL=(${PROJECT_USER}) NOPASSWD: ${pm2_bin} restart wa-gateway-${PROJECT_USER}
${WEB_GROUP} ALL=(${PROJECT_USER}) NOPASSWD: ${pm2_bin} list
EOF

  sudo chmod 0440 "$sudoers_file"
  sudo visudo -cf "$sudoers_file" || {
    sudo rm -f "$sudoers_file"
    err "Sudoers invalid."
  }
}

setup_pm2_multi(){
  HOME_DIR="$(getent passwd "${PROJECT_USER}" | cut -d: -f6)"

  log "Start PM2 sebagai ${PROJECT_USER}"

  sudo -u "${PROJECT_USER}" bash -c "
    cd ${REPO_DIR}
    pm2 start ecosystem.config.js --name wa-gateway-${PROJECT_USER}
    pm2 save
  "

  STARTUP_CMD="$(sudo -u "${PROJECT_USER}" pm2 startup systemd -u "${PROJECT_USER}" --hp "${HOME_DIR}" | grep sudo | head -n1 || true)"
  [[ -n "$STARTUP_CMD" ]] && eval "$STARTUP_CMD"
}

main(){
  require_non_root
  require_user_exists
  install_node
  install_pm2
  setup_sudoers_scoped
  setup_pm2_multi

  log "===================================="
  log "Multi-user install selesai."
  log "PM2 milik user: ${PROJECT_USER}"
  log "===================================="
}

main "$@"