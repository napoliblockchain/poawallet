#!/bin/sh
set -eu

APP_DIR="/app"
LOCK_FILE="$APP_DIR/package-lock.json"
HASH_FILE="$APP_DIR/node_modules/.lock-hash"

cd "$APP_DIR"

if [ ! -f "$APP_DIR/package.json" ]; then
  echo "[ws-notify] package.json not found in $APP_DIR"
  exit 1
fi

mkdir -p "$APP_DIR/node_modules"
if [ ! -w "$APP_DIR/node_modules" ]; then
  echo "[ws-notify] /app/node_modules is not writable by uid=$(id -u) gid=$(id -g)"
  echo "[ws-notify] Fix volume permissions, then restart ws-notify."
  exit 1
fi

if [ -f "$LOCK_FILE" ]; then
  CURRENT_HASH="$(sha1sum "$LOCK_FILE" | awk '{print $1}')"
else
  CURRENT_HASH="$(sha1sum "$APP_DIR/package.json" | awk '{print $1}')"
fi

STORED_HASH=""
if [ -f "$HASH_FILE" ]; then
  STORED_HASH="$(cat "$HASH_FILE" || true)"
fi

if [ ! -d "$APP_DIR/node_modules" ] || [ "$CURRENT_HASH" != "$STORED_HASH" ]; then
  echo "[ws-notify] Installing npm dependencies..."
  if [ -f "$LOCK_FILE" ]; then
    npm ci --no-audit --no-fund
  else
    npm install --no-audit --no-fund
  fi
  mkdir -p "$APP_DIR/node_modules"
  echo "$CURRENT_HASH" > "$HASH_FILE"
else
  echo "[ws-notify] npm dependencies already up to date."
fi

exec npm run start
