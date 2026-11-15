#!/bin/sh
# Esegue tests/chain/erc20-integration.php e tests/ui/layout.php su MySQL e chain Besu usa-e-getta (rete interna, nessuna porta host).
# Immagine wallet costruita da questo checkout (target dev); mai la sandbox o la chain condivise.
# Richiede le immagini locali napay/chain-tools:dev e hyperledger/besu:26.9.0.
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
docker image inspect napay/chain-tools:dev hyperledger/besu:26.9.0 >/dev/null
umask 077
work=$(mktemp -d)
project="poawallet-chain-test-$(basename "$work" | tr '[:upper:].' '[:lower:]-')"
image="$project-wallet:test"
compose() { docker compose -p "$project" -f "$work/compose.yml" "$@"; }
cleanup() {
    code=$?
    trap - EXIT
    compose down -v --timeout 10 >/dev/null 2>&1 || true
    docker image rm "$image" >/dev/null 2>&1 || true
    rm -rf "$work"
    exit "$code"
}
trap cleanup EXIT
trap 'exit 1' HUP INT TERM
docker build -q --target dev -t "$image" "$root" >/dev/null
db_password=$(openssl rand -hex 24)
cat > "$work/wallet.env" <<ENV
DB_DSN=mysql:host=mysql;dbname=wallet_test
DB_USERNAME=wallet_test
DB_PASSWORD=$db_password
COOKIE_VALIDATION_KEY=$(openssl rand -hex 32)
ENCRYPT_SECRET_KEY=$(openssl rand -hex 32)
ENCRYPT_SECRET_IV=$(openssl rand -hex 16)
ENCRYPT_METHOD=AES-256-CBC
CRYPTO_ACCEPT_LEGACY=true
MAIL_USE_FILE_TRANSPORT=true
TRUSTED_PROXIES=
APP_ADMIN_NAME=chain-test
APP_WEBSITE=https://wallet.example.invalid
MAIL_ADMIN=admin@example.invalid
MAIL_SUPPORT=support@example.invalid
MAIL_FROM_NAME=chain-test
MAIL_USERNAME=fixture-only
MAIL_PASSWORD=fixture-only
MAIL_HOST=mail.example.invalid
MAIL_PORT=587
MAIL_ENCRYPTION=tls
NOTIFY_CHANNEL=poawallet:notify-test
VAPID_PUBLIC_KEY=fixture-only
VAPID_PRIVATE_KEY=fixture-only
VAPID_SUBJECT=mailto:admin@example.invalid
CHAIN_DATA_DIR=/chain
CHAIN_RPC_URL=http://besu:8545
ENV
cat > "$work/compose.yml" <<YAML
services:
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_RANDOM_ROOT_PASSWORD: "yes"
      MYSQL_DATABASE: wallet_test
      MYSQL_USER: wallet_test
      MYSQL_PASSWORD: "$db_password"
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "--silent"]
      interval: 3s
      retries: 60
    networks: [internal]
  chain-init:
    image: napay/chain-tools:dev
    command: ["node", "init.mjs"]
    environment: { CHAIN_ID: "2118" }
    volumes: [chain:/data]
    networks: [internal]
  besu:
    image: hyperledger/besu:26.9.0
    user: "1000:1000"
    command: ["--data-path=/data/db", "--genesis-file=/data/genesis.json", "--node-private-key-file=/data/key", "--rpc-http-enabled",
      "--rpc-http-host=0.0.0.0", "--rpc-http-api=ETH,NET,WEB3", "--host-allowlist=*", "--min-gas-price=0",
      "--discovery-enabled=false", "--sync-mode=FULL", "--sync-min-peers=0"]
    volumes: [chain:/data]
    networks: [internal]
    healthcheck:
      test: ["CMD", "bash", "-c", "exec 3<>/dev/tcp/127.0.0.1/8545"]
      interval: 3s
      retries: 40
  chain-deploy:
    image: napay/chain-tools:dev
    command: ["node", "deploy.mjs"]
    environment: { CHAIN_RPC_URL: "http://besu:8545", CHAIN_ID: "2118" }
    volumes: [chain:/data]
    networks: [internal]
  wallet:
    image: $image
    env_file: [$work/wallet.env]
    volumes: ["chain:/chain:ro", "$root/tests:/var/www/tests:ro"]
    tmpfs: [/var/www/runtime]
    networks: [internal]
volumes:
  chain:
networks:
  internal:
    internal: true
YAML
compose run --rm chain-init >/dev/null
compose up -d --wait --wait-timeout 180 mysql besu
compose run --rm chain-deploy >/dev/null
compose run --rm --no-deps wallet php yii migrate --interactive=0 >/dev/null
compose run --rm --no-deps wallet php tests/chain/erc20-integration.php
compose run --rm --no-deps wallet php tests/ui/layout.php
