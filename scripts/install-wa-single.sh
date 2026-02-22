#!/usr/bin/env bash
set -euo pipefail

# =========================
# DETEKSI ROOT REPO
# =========================
if [[ -f "ecosystem.config.js" ]]; then
  REPO_DIR="$(pwd)"
else
  REPO_DIR="$(cd "$(dirname "$0")/.." && pwd)"
fi

PANEL_DIR="${REPO_DIR}/panel"
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
  [[ "${ID:-}" != "ubuntu" ]] && err "Hanya untuk Ubuntu."
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

install_composer(){
  if ! command -v composer >/dev/null; then
    log "Install Composer"
    php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
    php composer-setup.php
    sudo mv composer.phar /usr/local/bin/composer
    rm -f composer-setup.php
  fi
}

install_laravel(){
  [[ -d "$PANEL_DIR" ]] || err "Folder panel tidak ditemukan di ${PANEL_DIR}"

  cd "$PANEL_DIR"

  log "Composer install"
  composer install --no-dev --optimize-autoloader

  [[ -f ".env" ]] || cp .env.example .env

  php artisan key:generate

  sudo chown -R ${RUN_USER}:${WEB_GROUP} "$PANEL_DIR"
  sudo chmod -R 775 storage bootstrap/cache

  php artisan optimize

  log "Laravel panel siap."
}

setup_pm2(){
  cd "$REPO_DIR"

  pm2 start ecosystem.config.js --name wa-gateway || true

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
  install_composer
  install_laravel
  setup_pm2

  log "===================================="
  log "Install selesai."
  log "User PM2: ${RUN_USER}"
  log "Repo dir: ${REPO_DIR}"
  log "===================================="
}

main "$@"
