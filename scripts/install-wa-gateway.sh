#!/usr/bin/env bash
set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
APT_UPDATED=0

log() { printf "\033[1;32m[+]\033[0m %s\n" "$*"; }
warn() { printf "\033[1;33m[!]\033[0m %s\n" "$*"; }
err() { printf "\033[1;31m[-]\033[0m %s\n" "$*"; }
has_cmd() { command -v "$1" >/dev/null 2>&1; }

require_root() {
  if [[ ${EUID} -ne 0 ]]; then
    err "Jalankan sebagai root (sudo)."
    exit 1
  fi
}

apt_update_once() {
  if [[ $APT_UPDATED -eq 0 ]]; then
    log "apt-get update"
    apt-get update -y
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
  install -m 0755 -d /etc/apt/keyrings
  if [[ ! -f /etc/apt/keyrings/docker.gpg ]]; then
    curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg
    chmod a+r /etc/apt/keyrings/docker.gpg
  fi
  echo \
    "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu $(lsb_release -cs) stable" \
    > /etc/apt/sources.list.d/docker.list
  apt_update_once
  apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
  systemctl enable --now docker || true
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
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt-get install -y nodejs
}

install_php() {
  local required_version="8.4"
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
  apt-get install -y software-properties-common curl
  add-apt-repository -y ppa:ondrej/php
  apt_update_once
  apt-get install -y \
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
  apt-get install -y composer
}

prepare_gateway_env() {
  cd "$REPO_DIR"
  if [[ ! -f .env ]]; then
    log "Buat .env gateway"
    local key
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
  log "npm install (gateway)"
  npm install
  npm audit fix
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
    perl -pi -e "s|^WA_GATEWAY_KEY=.*|WA_GATEWAY_KEY=$key_gateway|" .env
  fi
}

setup_panel() {
  cd "$REPO_DIR/panel"
  log "Composer install (panel)"
  composer install --no-interaction --prefer-dist

  log "Generate APP_KEY jika belum ada"
  php -r "file_exists('.env') && strpos(file_get_contents('.env'),'APP_KEY=')!==false ?: exit(0);" \
    || php artisan key:generate

  log "Siapkan database SQLite"
  mkdir -p database
  touch database/database.sqlite

  log "Migrasi database"
  php artisan migrate --force

  log "Linkan Storage"
  php artisan storage:link

  log "npm install + build (panel)"
  npm install
  npm run build
}

main() {
  require_root
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
