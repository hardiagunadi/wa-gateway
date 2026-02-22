#!/usr/bin/env bash
set -euo pipefail

if [[ $# -lt 1 ]]; then
  echo "Usage: $0 <project-user>"
  exit 1
fi

PROJECT_USER="$1"
WEB_GROUP="www-data"
REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PANEL_DIR="${REPO_DIR}/panel"
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

install_composer(){
  if ! command -v composer >/dev/null; then
    log "Install Composer"
    php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
    php composer-setup.php
    sudo mv composer.phar /usr/local/bin/composer
    rm -f composer-setup.php
  fi
}

install_laravel_panel(){

  [[ -d "$PANEL_DIR" ]] || err "Folder panel tidak ditemukan di ${PANEL_DIR}"

  log "Install Laravel dependency di /panel"

  sudo -u "${PROJECT_USER}" bash -c "
    cd ${PANEL_DIR}
    composer install --no-dev --optimize-autoloader
  "

  if [[ ! -f "${PANEL_DIR}/.env" ]]; then
    sudo -u "${PROJECT_USER}" cp ${PANEL_DIR}/.env.example ${PANEL_DIR}/.env || true
  fi

  sudo -u "${PROJECT_USER}" bash -c "
    cd ${PANEL_DIR}
    php artisan key:generate
  "

  sudo chown -R ${PROJECT_USER}:${WEB_GROUP} ${PANEL_DIR}
  sudo chmod -R 775 ${PANEL_DIR}/storage ${PANEL_DIR}/bootstrap/cache

  read -p "Jalankan migrate database sekarang? (y/n): " MIGRATE
  if [[ "$MIGRATE" == "y" ]]; then
    sudo -u "${PROJECT_USER}" bash -c "
      cd ${PANEL_DIR}
      php artisan migrate --force
    "
  fi

  sudo -u "${PROJECT_USER}" bash -c "
    cd ${PANEL_DIR}
    php artisan optimize
  "

  log "Laravel panel siap."
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
  install_composer
  install_laravel_panel
  setup_sudoers_scoped
  setup_pm2_multi

  log "===================================="
  log "Multi-user install selesai."
  log "Laravel panel terinstall di /panel."
  log "PM2 milik user: ${PROJECT_USER}"
  log "===================================="
}

main "$@"
