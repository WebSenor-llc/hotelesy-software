#!/usr/bin/env bash
# Miraj Hotel Suite — local boot script
# Run from project root: bash start.sh

set -e

cd "$(dirname "$0")"

GREEN='\033[0;32m'; YELLOW='\033[1;33m'; RED='\033[0;31m'; CYAN='\033[0;36m'; NC='\033[0m'
say()  { echo -e "${CYAN}==>${NC} $*"; }
ok()   { echo -e "${GREEN}✓${NC} $*"; }
warn() { echo -e "${YELLOW}!${NC} $*"; }
die()  { echo -e "${RED}✗${NC} $*" >&2; exit 1; }

# ---- Prereq checks ----
say "Checking prerequisites…"
command -v php      >/dev/null || die "php not found. Install with: brew install php"
command -v composer >/dev/null || die "composer not found. Install with: brew install composer"
command -v mysql    >/dev/null || warn "mysql client not found — DB autocreate will be skipped"

PHP_VER=$(php -r 'echo PHP_VERSION;')
ok "PHP $PHP_VER"
ok "Composer $(composer --version | awk '{print $3}')"

# ---- .env ----
if [ ! -f .env ]; then
  say "Creating .env from .env.example"
  cp .env.example .env
fi

# Force APP_URL to localhost:8000 for `php artisan serve`
if grep -q '^APP_URL=' .env; then
  sed -i '' -e 's|^APP_URL=.*|APP_URL=http://localhost:8000|' .env
fi

# ---- vendor/ ----
if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
  say "Installing composer dependencies…"
  composer install --no-interaction --prefer-dist
fi

# ---- Livewire (referenced by routes but not installed) ----
if [ ! -d vendor/livewire/livewire ]; then
  say "Installing livewire/livewire (referenced by routes/web.php)…"
  composer require livewire/livewire --no-interaction || warn "livewire install failed — will boot anyway, some routes may 500"
fi

# ---- Refresh autoloader (picks up newly-added classes like AppServiceProvider) ----
say "Dumping composer autoload"
composer dump-autoload --quiet || warn "dump-autoload failed"

# ---- APP_KEY ----
if grep -q '^APP_KEY=$' .env || ! grep -q '^APP_KEY=base64:' .env; then
  say "Generating APP_KEY"
  php artisan key:generate --force
fi

# ---- Database ----
DB_NAME=$(grep '^DB_DATABASE=' .env | cut -d= -f2)
DB_USER=$(grep '^DB_USERNAME=' .env | cut -d= -f2)
DB_PASS=$(grep '^DB_PASSWORD=' .env | cut -d= -f2)
DB_HOST=$(grep '^DB_HOST=' .env | cut -d= -f2)
DB_PORT=$(grep '^DB_PORT=' .env | cut -d= -f2)

if command -v mysql >/dev/null; then
  say "Ensuring database '${DB_NAME}' exists on ${DB_HOST}:${DB_PORT}"
  if [ -z "$DB_PASS" ]; then
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" \
      -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
      || die "Could not connect to MySQL. Is it running? Try: brew services start mysql"
  else
    mysql -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" -p"$DB_PASS" \
      -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
      || die "Could not connect to MySQL with provided creds."
  fi
  ok "Database ready"
fi

# ---- Migrate + seed ----
# Use migrate:fresh by default so we recover from any partial state during scaffolding.
# Once the schema is stable, switch FRESH=0 to keep data across runs.
FRESH="${FRESH:-1}"
if [ "$FRESH" = "1" ]; then
  say "Running migrations (fresh — drops + recreates all tables)"
  php artisan migrate:fresh --force
else
  say "Running migrations"
  php artisan migrate --force
fi

say "Seeding demo data (skip with NO_SEED=1)"
if [ -z "$NO_SEED" ]; then
  php artisan db:seed --force || warn "Some seeders failed — continuing anyway"
fi

# ---- Caches ----
php artisan config:clear  >/dev/null 2>&1 || true
php artisan route:clear   >/dev/null 2>&1 || true
php artisan view:clear    >/dev/null 2>&1 || true

# ---- Pick a free port (defaults to 8000, then walks up if taken) ----
pick_port() {
  local p="${PORT:-8000}"
  for _ in $(seq 0 30); do
    if ! lsof -i ":${p}" -sTCP:LISTEN -t >/dev/null 2>&1; then
      echo "$p"; return
    fi
    p=$((p+1))
  done
  echo "$p"  # give up and try anyway
}

PORT=$(pick_port)
say "Starting Laravel dev server on http://localhost:${PORT}"
echo
echo -e "${GREEN}┌────────────────────────────────────────────────┐${NC}"
echo -e "${GREEN}│ Miraj Hotel Suite is starting…                 │${NC}"
echo -e "${GREEN}│ Open: http://localhost:${PORT}                    │${NC}"
echo -e "${GREEN}│ Stop with Ctrl+C                               │${NC}"
echo -e "${GREEN}└────────────────────────────────────────────────┘${NC}"
echo

# Open browser shortly after server starts
( sleep 2; open "http://localhost:${PORT}" >/dev/null 2>&1 ) &

exec php artisan serve --host=127.0.0.1 --port="${PORT}"
