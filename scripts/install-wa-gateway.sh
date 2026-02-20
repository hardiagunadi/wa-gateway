#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APT_UPDATED=0
EXPECTED_USER="${EXPECTED_USER:-deploy}"
PHP_VERSION="${PHP_VERSION:-8.4}"
COMPOSER_NO_DEV="${COMPOSER_NO_DEV:-1}"
RUN_NPM_AUDIT_FIX="${RUN_NPM_AUDIT_FIX:-0}"
WEB_GROUP="${WEB_GROUP:-www-data}"
WA_CREDENTIALS_DIR="${WA_CREDENTIALS_DIR:-${REPO_DIR}/wa_credentials}"

log() { printf "\033[1;32m[+]\033[0m %s\n" "$*"; }
warn() { printf "\033[1;33m[!]\033[0m %s\n" "$*"; }
err() { printf "\033[1;31m[-]\033[0m %s\n" "$*"; }
has_cmd() { command -v "$1" >/dev/null 2>&1; }

need_cmd() {
  if ! has_cmd "$1"; then
    err "Command tidak ditemukan: $1"
    exit 1
  fi
}

as_root() {
  sudo env DEBIAN_FRONTEND=noninteractive NEEDRESTART_MODE=a "$@"
}

apt_update_force() {
  APT_UPDATED=0
  apt_update_once
}

require_deploy_user() {
  if [[ ${EUID} -eq 0 ]]; then
    err "Jangan jalankan installer sebagai root."
    err "Login sebagai user '${EXPECTED_USER}' lalu jalankan: bash scripts/install-wa-gateway.sh"
    exit 1
  fi

  if [[ "$(id -un)" != "${EXPECTED_USER}" ]]; then
    err "Installer ini dioptimalkan untuk user '${EXPECTED_USER}'. Kamu sedang login sebagai '$(id -un)'."
    err "Solusi: login sebagai '${EXPECTED_USER}' atau set env EXPECTED_USER sesuai user yang dipakai."
    exit 1
  fi

  if ! has_cmd sudo; then
    err "sudo tidak tersedia. Instal sudo atau jalankan via user yang punya akses sudo."
    exit 1
  fi

  log "Meminta akses sudo (akan minta password jika belum cached)"
  sudo -v
}

require_ubuntu() {
  if [[ ! -f /etc/os-release ]]; then
    err "Tidak bisa mendeteksi OS (/etc/os-release tidak ditemukan)."
    exit 1
  fi
  # shellcheck disable=SC1091
  . /etc/os-release
  if [[ "${ID:-}" != "ubuntu" ]]; then
    err "Installer ini saat ini hanya mendukung Ubuntu (ID=${ID:-unknown})."
    err "Jika VPS kamu Debian/OS lain, perlu penyesuaian repo Docker/PHP."
    exit 1
  fi
}

apt_update_once() {
  if [[ $APT_UPDATED -eq 0 ]]; then
    log "apt-get update"
    as_root apt-get update
    APT_UPDATED=1
  fi
}

install_node() {
  if has_cmd node; then
    local v
    v=$(node -v | sed 's/^v//')
    if printf '%s\n20.0.0\n' "$v" | sort -V | head -n1 | grep -q '^20\.0\.0$'; then
      log "Node $(node -v) sudah memenuhi, skip."
      return
    fi
  fi

  log "Instal Node.js 20.x"
  apt_update_once
  curl -fsSL https://deb.nodesource.com/setup_20.x | as_root bash -
  apt_update_force
  as_root apt-get install -y nodejs
}

install_php() {
  local required_version="${PHP_VERSION}"
  if has_cmd php; then
    local v
    v=$(php -r 'echo PHP_VERSION;')
    if php -r "exit(version_compare(PHP_VERSION, '$required_version', '>=')?0:1);" >/dev/null 2>&1; then
      log "PHP ${v} sudah memenuhi, skip."
      return
    fi
  fi

  log "Instal PHP ${required_version} + ekstensi"
  apt_update_once
  as_root apt-get install -y software-properties-common curl
  as_root add-apt-repository -y ppa:ondrej/php
  apt_update_force
  as_root apt-get install -y \
    php${required_version} php${required_version}-cli php${required_version}-common php${required_version}-sqlite3 \
    php${required_version}-mbstring php${required_version}-xml php${required_version}-curl php${required_version}-zip \
    php${required_version}-gd php${required_version}-bcmath php${required_version}-intl
}

install_composer() {
  if has_cmd composer; then
    log "Composer sudah ada, skip."
    return
  fi

  log "Instal Composer"
  apt_update_once
  as_root apt-get install -y composer
}

prepare_gateway_env() {
  cd "$REPO_DIR"
  if [[ ! -f .env ]]; then
    log "Buat .env gateway"
    local key
    if ! has_cmd openssl; then
      apt_update_once
      as_root apt-get install -y openssl
    fi
    key=$(openssl rand -hex 16)
    cat > .env <<EOF
KEY=$key
PORT=5001
WEBHOOK_BASE_URL=
EOF
  else
    log ".env gateway sudah ada, tidak diubah."
  fi
}

install_pm2() {
  if has_cmd pm2; then
    log "PM2 sudah terinstal, skip."
    return
  fi

  log "Instal PM2 secara global"
  as_root npm install -g pm2
}

install_gateway_deps() {
  cd "$REPO_DIR"
  if [[ -f package-lock.json ]]; then
    log "npm ci (gateway)"
    npm ci || { warn "npm ci gagal, fallback ke npm install"; npm install; }
  else
    log "npm install (gateway)"
    npm install
  fi

  if [[ "${RUN_NPM_AUDIT_FIX}" == "1" ]]; then
    warn "Menjalankan npm audit fix (opsional) bisa mengubah dependency/lockfile."
    npm audit fix || true
  fi
}

setup_gateway_logs() {
  local logs_dir="${REPO_DIR}/logs"
  log "Siapkan folder logs gateway"
  mkdir -p "$logs_dir"
  touch "$logs_dir/.gitkeep"
  as_root chown -R "${EXPECTED_USER}:${WEB_GROUP}" "$logs_dir"
  as_root chmod -R 2775 "$logs_dir"
}

set_env_value() {
  # set_env_value <file> <KEY> <value>  – upsert satu baris KEY=value
  local file="$1" key="$2" value="$3"
  if grep -qE "^${key}=" "$file"; then
    perl -pi -e "s|^${key}=.*|${key}=${value}|" "$file"
  else
    printf "\n%s=%s\n" "$key" "$value" >> "$file"
  fi
}

prepare_panel_env() {
  cd "$REPO_DIR/panel"
  if [[ ! -f .env ]]; then
    log "Salin .env panel"
    cp .env.example .env
  fi

  # Sinkron API key gateway → panel
  local key_gateway
  key_gateway=$(cd "$REPO_DIR" && grep -E '^KEY=' .env | head -n1 | cut -d'=' -f2- || true)
  if [[ -n "$key_gateway" ]]; then
    log "Sinkron KEY ke panel (.env)"
    set_env_value .env "WA_GATEWAY_KEY" "$key_gateway"
  fi

  # Tulis PM2 paths absolut agar panel bisa mengelola proses PM2
  log "Set PM2 paths di panel (.env)"
  set_env_value .env "PM2_APP_NAME"    "wa-gateway"
  set_env_value .env "PM2_CONFIG_FILE" "${REPO_DIR}/ecosystem.config.js"
  set_env_value .env "PM2_WORKDIR"     "${REPO_DIR}"
}

setup_panel() {
  cd "$REPO_DIR/panel"
  log "Composer install (panel)"
  if [[ "${COMPOSER_NO_DEV}" == "1" ]]; then
    composer install --prefer-dist --no-interaction --no-dev --optimize-autoloader
  else
    composer install --prefer-dist --no-interaction
  fi

  log "Generate APP_KEY jika belum ada"
  local app_key
  app_key="$(grep -E '^APP_KEY=' .env | head -n1 | cut -d'=' -f2- | tr -d '\"' | tr -d "'" || true)"
  if [[ -z "${app_key}" ]]; then
    php artisan key:generate
  else
    log "APP_KEY sudah ada, skip."
  fi

  log "Siapkan database SQLite"
  mkdir -p database
  touch database/database.sqlite

  log "Migrasi database"
  php artisan migrate --force

  log "Linkan Storage"
  php artisan storage:link || true

  log "npm install + build (panel)"
  if [[ -f package-lock.json ]]; then
    npm ci || { warn "npm ci gagal, fallback ke npm install"; npm install; }
  else
    npm install
  fi
  npm run build
}

set_permissions() {
  local panel_dir="${REPO_DIR}/panel"
  local esbuild_bin="${REPO_DIR}/node_modules/@esbuild/linux-x64/bin/esbuild"
  local panel_env="${panel_dir}/.env"

  log "Set permission storage + bootstrap/cache (panel)"
  cd "$panel_dir"
  mkdir -p storage bootstrap/cache
  as_root chown -R "${EXPECTED_USER}:${WEB_GROUP}" storage bootstrap/cache
  as_root chmod -R 775 storage bootstrap/cache
  as_root find storage bootstrap/cache -type d -exec chmod g+s {} \;

  if [[ -f "${panel_dir}/database/database.sqlite" ]]; then
    log "Set permission database.sqlite agar bisa ditulis web user"
    as_root chown "${WEB_GROUP}:${WEB_GROUP}" "${panel_dir}/database/database.sqlite"
    as_root chmod 664 "${panel_dir}/database/database.sqlite"
    as_root chown -R "${WEB_GROUP}:${WEB_GROUP}" "${panel_dir}/database"
    as_root chmod -R 775 "${panel_dir}/database"
  fi

  if [[ -f "${panel_env}" ]]; then
    log "Set permission panel/.env agar bisa ditulis web user"
    as_root chown "${WEB_GROUP}:${WEB_GROUP}" "${panel_env}"
    as_root chmod 640 "${panel_env}"
  else
    warn "File ${panel_env} belum ada; lewati set permission .env panel."
  fi

  log "Siapkan folder wa_credentials (${WA_CREDENTIALS_DIR})"
  as_root mkdir -p "${WA_CREDENTIALS_DIR}"
  as_root chown -R "${EXPECTED_USER}:${WEB_GROUP}" "${WA_CREDENTIALS_DIR}"
  as_root chmod -R 2775 "${WA_CREDENTIALS_DIR}"
  as_root find "${WA_CREDENTIALS_DIR}" -type f -exec chmod 664 {} \;

  if [[ -f "${esbuild_bin}" ]]; then
    log "Set executable bit untuk esbuild binary"
    as_root chmod +x "${esbuild_bin}"
  else
    warn "Binary esbuild tidak ditemukan di ${esbuild_bin}, skip chmod +x."
  fi
}

setup_pm2_gateway() {
  cd "$REPO_DIR"

  # Stop existing PM2 process if running
  if pm2 list 2>/dev/null | grep -q "wa-gateway"; then
    log "Stop PM2 wa-gateway yang sudah berjalan"
    pm2 stop wa-gateway || true
    pm2 delete wa-gateway || true
  fi

  log "Start gateway dengan PM2"
  pm2 start ecosystem.config.js

  log "Setup PM2 startup (auto-start saat reboot)"
  # Generate startup script
  local startup_cmd
  startup_cmd=$(pm2 startup systemd -u "${EXPECTED_USER}" --hp "/home/${EXPECTED_USER}" 2>&1 | grep -E "^sudo" | head -n1 || true)

  if [[ -n "$startup_cmd" ]]; then
    log "Jalankan: $startup_cmd"
    eval "$startup_cmd" || warn "Gagal setup PM2 startup, mungkin perlu dijalankan manual"
  fi

  log "Simpan PM2 process list"
  pm2 save

  log "PM2 gateway status:"
  pm2 list
}

main() {
  require_deploy_user
  require_ubuntu
  need_cmd curl

  if [[ ! -w "$REPO_DIR" ]]; then
    warn "Folder repo tidak writable untuk user '${EXPECTED_USER}'. Mencoba memperbaiki permission (chown)."
    as_root chown -R "${EXPECTED_USER}:${EXPECTED_USER}" "$REPO_DIR"
  fi

  if ! has_cmd perl; then
    apt_update_once
    as_root apt-get install -y perl
  fi
  install_node
  install_pm2
  install_php
  install_composer
  prepare_gateway_env
  install_gateway_deps
  setup_gateway_logs
  prepare_panel_env
  setup_panel
  set_permissions
  setup_pm2_gateway

  log ""
  log "=============================================="
  log "Installer selesai!"
  log "=============================================="
  log ""
  log "Gateway WA sudah berjalan via PM2 dan akan auto-restart jika crash."
  log ""
  log "Kontrol server dari panel (browser):"
  log "  Buka dashboard panel → tombol Start / Restart / Stop di card 'WA Gateway Server (PM2)'"
  log ""
  log "Kontrol manual via CLI:"
  log "  pm2 status             - Lihat status semua proses"
  log "  pm2 logs wa-gateway    - Lihat log output"
  log "  pm2 restart wa-gateway - Restart gateway"
  log "  pm2 stop    wa-gateway - Stop gateway"
  log "  pm2 start   wa-gateway - Start kembali setelah stop"
  log ""
  log "Lokasi log PM2:"
  log "  Output : ${REPO_DIR}/logs/pm2-out.log"
  log "  Error  : ${REPO_DIR}/logs/pm2-error.log"
  log ""
  log "Jalankan panel:"
  log "  cd ${REPO_DIR}/panel && php artisan serve --host 0.0.0.0 --port 8000"
}

main "$@"
