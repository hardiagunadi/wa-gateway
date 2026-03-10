#!/usr/bin/env bash
set -euo pipefail

# ==============================
# DETECT ROOT REPO PROPERLY
# ==============================
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
PANEL_DIR="${REPO_DIR}/panel"

WEB_GROUP="www-data"
RUN_USER="$(id -un)"
APT_UPDATED=0

log(){ echo -e "\033[1;32m[+]\033[0m $*"; }
err(){ echo -e "\033[1;31m[-]\033[0m $*"; exit 1; }

# ==============================
# BASIC VALIDATION
# ==============================
require_non_root(){
  [[ $EUID -eq 0 ]] && err "Jangan jalankan sebagai root."
  sudo -v >/dev/null 2>&1 || err "User tidak memiliki akses sudo."
}

require_ubuntu(){
  . /etc/os-release
  [[ "${ID:-}" != "ubuntu" ]] && err "Script ini hanya untuk Ubuntu."
}

validate_repo(){
  [[ -f "${REPO_DIR}/ecosystem.config.js" ]] || err "ecosystem.config.js tidak ditemukan di ${REPO_DIR}"
  [[ -d "${PANEL_DIR}" ]] || err "Folder panel tidak ditemukan di ${PANEL_DIR}"
}

apt_update(){
  if [[ $APT_UPDATED -eq 0 ]]; then
    sudo apt-get update
    APT_UPDATED=1
  fi
}

# ==============================
# INSTALL DEPENDENCIES
# ==============================
install_node(){
  if ! command -v node >/dev/null; then
    log "Install Node 20"
    apt_update
    curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
    sudo apt-get install -y nodejs
  else
    log "Node sudah terinstall: $(node -v)"
  fi
}

install_pm2(){
  if ! command -v pm2 >/dev/null; then
    log "Install PM2"
    sudo npm install -g pm2
  else
    log "PM2 sudah terinstall: $(pm2 -v)"
  fi
}

install_composer(){
  if ! command -v composer >/dev/null; then
    log "Install Composer"
    php -r "copy('https://getcomposer.org/installer','composer-setup.php');"
    php composer-setup.php
    sudo mv composer.phar /usr/local/bin/composer
    rm -f composer-setup.php
  else
    log "Composer sudah terinstall: $(composer --version | head -n1)"
  fi
}

# ==============================
# INSTALL LARAVEL PANEL
# ==============================
install_laravel_panel(){

  log "Install dependency Laravel di /panel"

  cd "$PANEL_DIR"

  composer install --no-dev --optimize-autoloader

  if [[ ! -f ".env" ]]; then
    cp .env.example .env || true
  fi

  php artisan key:generate

  sudo chown -R ${RUN_USER}:${WEB_GROUP} "$PANEL_DIR"
  sudo chmod -R 775 storage bootstrap/cache

  read -p "Jalankan migrate database sekarang? (y/n): " MIGRATE
  if [[ "$MIGRATE" == "y" ]]; then
    php artisan migrate --force
  fi

  php artisan optimize

  log "Laravel panel siap."
}

# ==============================
# SETUP SUDOERS (SCOPED)
# ==============================
setup_sudoers(){
  local pm2_bin
  pm2_bin="$(readlink -f "$(command -v pm2)")"
  local sudoers_file="/etc/sudoers.d/wa-gateway-single"

  log "Setup sudoers scoped"

  sudo tee "$sudoers_file" > /dev/null <<EOF
${WEB_GROUP} ALL=(${RUN_USER}) NOPASSWD: ${pm2_bin} start wa-gateway
${WEB_GROUP} ALL=(${RUN_USER}) NOPASSWD: ${pm2_bin} stop wa-gateway
${WEB_GROUP} ALL=(${RUN_USER}) NOPASSWD: ${pm2_bin} restart wa-gateway
${WEB_GROUP} ALL=(${RUN_USER}) NOPASSWD: ${pm2_bin} list
EOF

  sudo chmod 0440 "$sudoers_file"

  sudo visudo -cf "$sudoers_file" || {
    sudo rm -f "$sudoers_file"
    err "File sudoers invalid."
  }
}

# ==============================
# SETUP PM2
# ==============================
setup_pm2(){
  cd "$REPO_DIR"

  log "Start PM2 gateway"
  pm2 start ecosystem.config.js --name wa-gateway || true

  HOME_DIR="$(getent passwd "${RUN_USER}" | cut -d: -f6)"
  STARTUP_CMD="$(pm2 startup systemd -u "${RUN_USER}" --hp "${HOME_DIR}" | grep sudo | head -n1 || true)"

  [[ -n "$STARTUP_CMD" ]] && eval "$STARTUP_CMD"

  pm2 save
}

# ==============================
# MAIN
# ==============================
main(){
  require_non_root
  require_ubuntu
  validate_repo
  install_node
  install_pm2
  install_composer
  install_laravel_panel
  setup_sudoers
  setup_pm2

  log "===================================="
  log "INSTALL SELESAI"
  log "User PM2 : ${RUN_USER}"
  log "Repo     : ${REPO_DIR}"
  log "Panel    : ${PANEL_DIR}"
  log "===================================="
}

main "$@"
