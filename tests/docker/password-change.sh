#!/bin/sh
# SIP-035: real wallet HTTP + MySQL, private disposable project, no host ports or shared stack.
set -eu
root=$(CDPATH= cd -- "$(dirname -- "$0")/../.." && pwd)
image=${WALLET_TEST_IMAGE:-napay/poawallet:dev}
docker image inspect "$image" >/dev/null
umask 077
work=$(mktemp -d)
project="wallet-password-test-$(basename "$work" | tr '[:upper:].' '[:lower:]-')"
compose() { docker compose -p "$project" -f "$work/compose.yml" "$@"; }
# Sorgenti in una copia privata (vendor dell'immagine): /var/www/html deve esistere come punto di mount dentro /var/www in sola lettura,
# senza creare cartelle nel repository.
mkdir -m 755 "$work/src"
tar -C "$root" --exclude=./vendor --exclude=./.git --exclude='*/node_modules' --exclude=./runtime -cf - . | tar -C "$work/src" -xpf -
mkdir -p -m 755 "$work/src/html" "$work/src/runtime"
# dipendenze dell'immagine sotto test (mai il vendor dell'host, che può essere vecchio)
vendor_box=$(docker create "$image")
docker cp -q "$vendor_box:/var/www/vendor" "$work/src/vendor"
docker rm "$vendor_box" >/dev/null
chmod 755 "$work/src/vendor"
cleanup() { code=$?; trap - EXIT; compose down -v --timeout 10 >/dev/null 2>&1 || true; rm -rf "$work"; exit "$code"; }
trap cleanup EXIT
trap 'exit 1' HUP INT TERM
pw=$(openssl rand -hex 24)
cat > "$work/test.env" <<ENV
DB_DSN=mysql:host=mysql;dbname=wallet_password_test
DB_USERNAME=wallet_test
DB_PASSWORD=$pw
COOKIE_VALIDATION_KEY=$(openssl rand -hex 32)
ENCRYPT_SECRET_KEY=$(openssl rand -hex 32)
ENCRYPT_SECRET_IV=$(openssl rand -hex 16)
ENCRYPT_METHOD=AES-256-CBC
YII_ENV=prod
YII_DEBUG=0
WALLET_PASSWORD_TEST=isolated-password-change
MAIL_USE_FILE_TRANSPORT=true
APP_ADMIN_NAME=password-test
APP_WEBSITE=https://wallet.example.invalid
MAIL_ADMIN=admin@example.invalid
MAIL_SUPPORT=support@example.invalid
MAIL_FROM_NAME=wallet-test
MAIL_USERNAME=sender@example.invalid
MAIL_PASSWORD=fixture-only
MAIL_HOST=mail.example.invalid
MAIL_PORT=587
MAIL_ENCRYPTION=tls
VAPID_PUBLIC_KEY=
VAPID_PRIVATE_KEY=
CHAIN_RPC_URL=http://unavailable.invalid
CHAIN_ID=2118
CHAIN_NAME=Password Test
CHAIN_SYMBOL=ETH
CHAIN_ZERO_GAS=1
TTS_TOKEN_ADDRESS=0x1111111111111111111111111111111111111111
TTS_TOKEN_SYMBOL=TTS
TTS_TOKEN_DECIMALS=2
ENV
cat > "$work/compose.yml" <<YAML
services:
  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: "$(openssl rand -hex 24)"
      MYSQL_DATABASE: wallet_password_test
      MYSQL_USER: wallet_test
      MYSQL_PASSWORD: "$pw"
    tmpfs: [/var/lib/mysql]
    networks: [private]
    healthcheck:
      test: ["CMD", "mysqladmin", "ping", "-h", "127.0.0.1", "--silent"]
      interval: 3s
      retries: 60
  wallet:
    image: $image
    env_file: [$work/test.env]
    read_only: true
    volumes:
      - $work/src:/var/www:ro
      - $root/web:/var/www/html:ro
    tmpfs:
      - /var/www/runtime:uid=33,gid=33,mode=0700
      - /var/www/html/assets:uid=33,gid=33,mode=0755
      - /var/www/logs:uid=33,gid=33,mode=0700
      - /tmp
      - /var/run/apache2
      - /var/lock/apache2
    networks: [private]
    depends_on:
      mysql: { condition: service_healthy }
networks:
  private: { internal: true }
YAML
unset pw
compose up -d --wait --wait-timeout 240 wallet
compose exec -T --user 33:33 wallet php /var/www/yii migrate --interactive=0 > "$work/migration.log" 2>&1
compose exec -T --user 33:33 wallet php /var/www/tests/password-change.php
