#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
PORT="7500"
HOST="127.0.0.1"
USER1="1"
USER2="2"
START_DAEMON="1"
KEEP_DAEMON="0"
TIMEOUT_SEC="6"
BASE_URL=""
COOKIE_FILE=""
COOKIE_HEADER=""
SKIP_HTTP="0"

usage() {
  cat <<EOF
Usage:
  $(basename "$0") [options]

Options:
  --port <n>               WebSocket port (default: 7500)
  --host <ip|host>         WebSocket host (default: 127.0.0.1)
  --user1 <id>             First websocket user id (default: 1)
  --user2 <id>             Second websocket user id (default: 2)
  --no-start-daemon        Do not start yii websocket daemon
  --keep-daemon            Keep daemon running after test
  --timeout <sec>          WebSocket test timeout (default: 6)

  HTTP checks (optional):
  --base-url <url>         Example: http://127.0.0.1:8080
  --cookie-file <path>     Netscape cookie jar for authenticated session
  --cookie-header <str>    Raw Cookie header alternative
  --skip-http              Skip HTTP checks

Examples:
  $(basename "$0") --port 7500
  $(basename "$0") --base-url http://127.0.0.1:8080 --cookie-file /tmp/poawallet.cookies
EOF
}

while [[ $# -gt 0 ]]; do
  case "$1" in
    --port) PORT="$2"; shift 2 ;;
    --host) HOST="$2"; shift 2 ;;
    --user1) USER1="$2"; shift 2 ;;
    --user2) USER2="$2"; shift 2 ;;
    --no-start-daemon) START_DAEMON="0"; shift ;;
    --keep-daemon) KEEP_DAEMON="1"; shift ;;
    --timeout) TIMEOUT_SEC="$2"; shift 2 ;;
    --base-url) BASE_URL="$2"; shift 2 ;;
    --cookie-file) COOKIE_FILE="$2"; shift 2 ;;
    --cookie-header) COOKIE_HEADER="$2"; shift 2 ;;
    --skip-http) SKIP_HTTP="1"; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1"; usage; exit 1 ;;
  esac
done

log() { echo "[smoke-step13] $*"; }
fail() { echo "[smoke-step13][FAIL] $*"; exit 1; }

require_cmd() {
  command -v "$1" >/dev/null 2>&1 || fail "Missing command: $1"
}

require_cmd php
require_cmd node
require_cmd curl
require_cmd mktemp

cd "$APP_DIR"

if ! php yii help websocket >/dev/null 2>&1; then
  fail "yii websocket command not available"
fi

WS_PID=""
WS_LOG="$(mktemp /tmp/poawallet-ws-daemon.XXXX.log)"
cleanup() {
  if [[ -n "$WS_PID" ]] && kill -0 "$WS_PID" >/dev/null 2>&1; then
    if [[ "$KEEP_DAEMON" == "1" ]]; then
      log "Keep daemon requested. pid=$WS_PID log=$WS_LOG"
    else
      kill "$WS_PID" >/dev/null 2>&1 || true
      sleep 0.2
      kill -9 "$WS_PID" >/dev/null 2>&1 || true
      log "Daemon stopped."
    fi
  fi
}
trap cleanup EXIT

if [[ "$START_DAEMON" == "1" ]]; then
  log "Start websocket daemon on ${HOST}:${PORT}"
  php yii websocket/index "$PORT" >"$WS_LOG" 2>&1 &
  WS_PID="$!"
  sleep 1
  if ! kill -0 "$WS_PID" >/dev/null 2>&1; then
    log "Daemon log:"
    sed -n '1,120p' "$WS_LOG" || true
    fail "Daemon not running"
  fi
  log "Daemon pid=$WS_PID"
else
  log "Skip daemon start (external daemon expected)"
fi

NODE_SCRIPT="$(mktemp /tmp/poawallet-ws-client.XXXX.mjs)"
cat > "$NODE_SCRIPT" <<'NODE'
const url = process.env.WS_URL;
const user1 = process.env.WS_USER1;
const user2 = process.env.WS_USER2;
const timeoutMs = Number(process.env.WS_TIMEOUT_MS || "6000");

if (typeof WebSocket === "undefined") {
  console.error("[FAIL] Node WebSocket API not available. Use Node >= 20.");
  process.exit(2);
}

const events = { c1: [], c2: [] };

function connect(label, userId) {
  return new Promise((resolve, reject) => {
    const ws = new WebSocket(url);
    const timer = setTimeout(() => reject(new Error(`${label}: open timeout`)), timeoutMs);

    ws.onopen = () => {
      clearTimeout(timer);
      ws.send(JSON.stringify({ action: "subscribe", user_id: userId }));
      resolve(ws);
    };

    ws.onmessage = (msg) => {
      try {
        const data = JSON.parse(msg.data);
        events[label].push(data);
      } catch {
        events[label].push({ type: "invalid_json", raw: String(msg.data) });
      }
    };

    ws.onerror = () => {};
  });
}

function hasType(list, type) {
  return list.some((x) => x && x.type === type);
}

(async () => {
  let c1, c2;
  try {
    c1 = await connect("c1", user1);
    c2 = await connect("c2", user2);

    await new Promise((r) => setTimeout(r, 300));
    c1.send(JSON.stringify({ action: "getNotifications" }));
    await new Promise((r) => setTimeout(r, 300));
    c1.send(JSON.stringify({ action: "newMessage" }));

    await new Promise((r) => setTimeout(r, 1200));

    const checks = [
      ["c1 subscribed_response", hasType(events.c1, "subscribed_response")],
      ["c2 subscribed_response", hasType(events.c2, "subscribed_response")],
      ["c2 newmessage_call", hasType(events.c2, "newmessage_call")],
      ["c1 notifications response", events.c1.some((x) => x && (x.type === "notifications_response" || x.type === "endnotifications_response"))],
    ];

    let failed = 0;
    for (const [name, ok] of checks) {
      console.log(`${ok ? "OK" : "FAIL"}   ${name}`);
      if (!ok) failed++;
    }

    console.log("WS events c1:", JSON.stringify(events.c1));
    console.log("WS events c2:", JSON.stringify(events.c2));

    c1.close();
    c2.close();

    process.exit(failed === 0 ? 0 : 1);
  } catch (e) {
    console.error("[FAIL] websocket client error:", e.message);
    console.log("WS events c1:", JSON.stringify(events.c1));
    console.log("WS events c2:", JSON.stringify(events.c2));
    process.exit(1);
  }
})();
NODE

log "Run websocket protocol smoke"
if ! WS_URL="ws://${HOST}:${PORT}" WS_USER1="$USER1" WS_USER2="$USER2" WS_TIMEOUT_MS="$(( TIMEOUT_SEC * 1000 ))" node "$NODE_SCRIPT"; then
  log "Daemon log (tail):"
  tail -n 80 "$WS_LOG" || true
  fail "WebSocket smoke failed"
fi

if [[ "$SKIP_HTTP" == "1" ]]; then
  log "Skip HTTP checks requested"
  log "ALL GOOD"
  exit 0
fi

if [[ -z "$BASE_URL" ]]; then
  log "No --base-url provided. HTTP checks skipped."
  log "ALL GOOD (websocket only)"
  exit 0
fi

if [[ -z "$COOKIE_FILE" && -z "$COOKIE_HEADER" ]]; then
  fail "HTTP checks need --cookie-file or --cookie-header"
fi

BASE_URL="${BASE_URL%/}"
CURL_AUTH=()
if [[ -n "$COOKIE_FILE" ]]; then
  [[ -f "$COOKIE_FILE" ]] || fail "Cookie file not found: $COOKIE_FILE"
  CURL_AUTH+=( -b "$COOKIE_FILE" )
fi
if [[ -n "$COOKIE_HEADER" ]]; then
  CURL_AUTH+=( -H "Cookie: $COOKIE_HEADER" )
fi

json_key() {
  local json="$1"
  local key="$2"
  php -r '$j=json_decode($argv[1],true); if(!is_array($j)){exit(2);} if(!array_key_exists($argv[2],$j)){exit(3);} $v=$j[$argv[2]]; if(is_bool($v)){echo $v?"true":"false";} elseif(is_array($v)){echo json_encode($v);} else {echo (string)$v;}' "$json" "$key" 2>/dev/null
}

log "HTTP check: backend/notify"
NOTIFY_JSON="$(curl -sS -L "${CURL_AUTH[@]}" "${BASE_URL}/index.php?r=backend/notify")"
COUNT_UNREAD="$(json_key "$NOTIFY_JSON" countedUnread || true)"
[[ -n "$COUNT_UNREAD" ]] || fail "backend/notify invalid JSON or missing countedUnread"
log "backend/notify countedUnread=$COUNT_UNREAD"

FIRST_ID="$(printf '%s' "$NOTIFY_JSON" | grep -oE 'news_[0-9]+' | head -1 | cut -d_ -f2 || true)"
if [[ -n "$FIRST_ID" ]]; then
  log "HTTP check: backend/update-single-news id=$FIRST_ID"
  SINGLE_JSON="$(curl -sS -L "${CURL_AUTH[@]}" -X POST --data "id_notification=${FIRST_ID}" "${BASE_URL}/index.php?r=backend/update-single-news")"
  SINGLE_OK="$(json_key "$SINGLE_JSON" success || true)"
  [[ "$SINGLE_OK" == "true" ]] || fail "update-single-news failed: $SINGLE_JSON"
else
  log "No news_* id in notify payload. Skip update-single-news."
fi

log "HTTP check: backend/update-all-news"
ALL_JSON="$(curl -sS -L "${CURL_AUTH[@]}" -X POST "${BASE_URL}/index.php?r=backend/update-all-news")"
ALL_OK="$(json_key "$ALL_JSON" success || true)"
[[ "$ALL_OK" == "true" ]] || fail "update-all-news failed: $ALL_JSON"

log "ALL GOOD"
