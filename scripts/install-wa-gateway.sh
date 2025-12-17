#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APT_UPDATED=0
EXPECTED_USER="${EXPECTED_USER:-deploy}"
PHP_VERSION="${PHP_VERSION:-8.3}"
COMPOSER_NO_DEV="${COMPOSER_NO_DEV:-1}"
RUN_NPM_AUDIT_FIX="${RUN_NPM_AUDIT_FIX:-0}"

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

install_docker() {
  if has_cmd docker && docker compose version >/dev/null 2>&1; then
    log "Docker + compose sudah tersedia, skip."
    return
  fi

  log "Instal Docker Engine + Compose plugin"
  apt_update_once
  as_root apt-get install -y ca-certificates curl gnupg lsb-release
  as_root install -m 0755 -d /etc/apt/keyrings
  if [[ ! -f /etc/apt/keyrings/docker.gpg ]]; then
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | as_root gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    as_root chmod a+r /etc/apt/keyrings/docker.gpg
  fi
  echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
    | as_root tee /etc/apt/sources.list.d/docker.list >/dev/null
  apt_update_force
  as_root apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  as_root systemctl enable --now docker || true

  if ! groups "${EXPECTED_USER}" | tr ' ' '\n' | grep -qx docker; then
    log "Menambahkan user '${EXPECTED_USER}' ke group docker"
    as_root usermod -aG docker "${EXPECTED_USER}"
    warn "Agar bisa menjalankan docker tanpa sudo, logout/login ulang user '${EXPECTED_USER}'."
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

prepare_panel_env() {
  cd "$REPO_DIR/panel"
  if [[ ! -f .env ]]; then
    log "Salin .env panel"
    cp .env.example .env
  fi

  local key_gateway
  key_gateway=$(cd "$REPO_DIR" && grep -E '^KEY=' .env | head -n1 | cut -d'=' -f2- || true)
  if [[ -n "$key_gateway" ]]; then
    log "Sinkron KEY ke panel (.env)"
    if grep -qE '^WA_GATEWAY_KEY=' .env; then
      perl -pi -e "s|^WA_GATEWAY_KEY=.*|WA_GATEWAY_KEY=$key_gateway|" .env
    else
      printf "\nWA_GATEWAY_KEY=%s\n" "$key_gateway" >> .env
    fi
  fi
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
  install_docker
  install_node
  install_php
  install_composer
  prepare_gateway_env
  install_gateway_deps
  prepare_panel_env
  setup_panel

  log "Installer selesai. Jalankan panel: php artisan serve --host 0.0.0.0 --port 8000 (di folder panel)"
  log "Jalankan gateway: node --import node_modules/tsx/dist/loader.mjs src/index.ts (di root repo) atau gunakan tombol Start di panel."
}

main "$@"
