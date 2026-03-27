#!/bin/bash
set -euo pipefail

DATA_ROOT=/data/yiimp
LOG_ROOT="${DATA_ROOT}/log"
BACKUP_ROOT="${DATA_ROOT}/backup"
SQL_ROOT=/opt/happychina-yiimp/sql
SEED_SQL=/opt/happychina-yiimp/bootstrap/seed.sql
CONF_ROOT=/etc/yiimp
STRATUM_ROOT="${CONF_ROOT}/stratum"

DB_HOST="${DB_HOST:-mariadb}"
DB_NAME="${DB_NAME:-yaamp}"
DB_USER="${DB_USER:-yiimp}"
DB_PASSWORD="${DB_PASSWORD:-yiimp}"
DB_ROOT_PASSWORD="${DB_ROOT_PASSWORD:-yiimp-root}"

POOL_SITE_NAME="${POOL_SITE_NAME:-HappyChina Pool}"
POOL_SITE_HOST="${POOL_SITE_HOST:-umbrel.local}"
POOL_STRATUM_HOST="${POOL_STRATUM_HOST:-${POOL_SITE_HOST}}"
POOL_ADMIN_USER="${POOL_ADMIN_USER:-admin}"
POOL_ADMIN_PASS="${POOL_ADMIN_PASS:-umbrelpool}"
POOL_ADMIN_IP="${POOL_ADMIN_IP:-0.0.0.0/0}"
STRATUM_NOTIFY_PASSWORD="${STRATUM_NOTIFY_PASSWORD:-blocknotify}"

log() {
  printf '[yiimp-bootstrap] %s\n' "$*"
}

mysql_exec() {
  MYSQL_PWD="${DB_ROOT_PASSWORD}" mysql \
    -h "${DB_HOST}" \
    -u root \
    --batch \
    --skip-column-names \
    "${DB_NAME}" \
    "$@"
}

mysql_exec_force_file() {
  MYSQL_PWD="${DB_ROOT_PASSWORD}" mysql \
    -h "${DB_HOST}" \
    -u root \
    --force \
    "${DB_NAME}" < "$1"
}

wait_for_db() {
  local try=0
  until MYSQL_PWD="${DB_ROOT_PASSWORD}" mysqladmin ping -h "${DB_HOST}" -u root --silent >/dev/null 2>&1; do
    try=$((try + 1))
    if [ "${try}" -ge 60 ]; then
      log "database did not become ready in time"
      exit 1
    fi
    sleep 5
  done
}

write_serverconfig() {
  mkdir -p "${CONF_ROOT}" "${STRATUM_ROOT}" "${LOG_ROOT}" "${BACKUP_ROOT}" "${DATA_ROOT}"
  cat > "${CONF_ROOT}/serverconfig.php" <<PHP
<?php

ini_set('date.timezone', 'UTC');

define('YIIMP_MEMCACHE_HOST', '127.0.0.1');
define('YIIMP_MEMCACHE_PORT', 11211);

define('YIIMP_LOGS', '${LOG_ROOT}');
define('YIIMP_HTDOCS', '/var/www');
define('YIIMP_BIN', '/var/www/bin');

define('YIIMP_DBHOST', '${DB_HOST}');
define('YIIMP_DBNAME', '${DB_NAME}');
define('YIIMP_DBUSER', '${DB_USER}');
define('YIIMP_DBPASSWORD', '${DB_PASSWORD}');

define('YIIMP_SITE_URL', '${POOL_SITE_HOST}');
define('YIIMP_STRATUM_URL', '${POOL_STRATUM_HOST}');
define('YIIMP_SITE_NAME', '${POOL_SITE_NAME}');

define('YIIMP_PRODUCTION', true);
define('YIIMP_LIMIT_ESTIMATE', false);

define('YIIMP_FEES_SOLO', 0);
define('YIIMP_FEES_MINING', 0);
define('YIIMP_FEES_EXCHANGE', 0);
define('YIIMP_FEES_RENTING', 0);
define('YIIMP_TXFEE_RENTING_WD', 0.002);
define('YIIMP_PAYMENTS_FREQ', 3*60*60);
define('YIIMP_PAYMENTS_MINI', 0.001);

define('YIIMP_ALLOW_EXCHANGE', false);
define('YIIMP_BTCADDRESS', '1BoatSLRHtKNngkdXEeobR76b53LETtpyT');

define('YIIMP_ADMIN_EMAIL', 'admin@local');
define('YIIMP_ADMIN_USER', '${POOL_ADMIN_USER}');
define('YIIMP_ADMIN_PASS', '${POOL_ADMIN_PASS}');
define('YIIMP_ADMIN_IP', '${POOL_ADMIN_IP}');
define('YIIMP_ADMIN_WEBCONSOLE', true);
define('YIIMP_CREATE_NEW_COINS', false);
define('YIIMP_NOTIFY_NEW_COINS', false);
define('YIIMP_DEFAULT_ALGO', 'scrypt');
define('YIIMP_PUBLIC_EXPLORER', true);
define('YIIMP_PUBLIC_BENCHMARK', false);
define('YIIMP_ADMIN_LOGIN', true);

define('YAAMP_LOGS', YIIMP_LOGS);
define('YAAMP_HTDOCS', YIIMP_HTDOCS);
define('YAAMP_BIN', YIIMP_BIN);

define('YAAMP_DBHOST', YIIMP_DBHOST);
define('YAAMP_DBNAME', YIIMP_DBNAME);
define('YAAMP_DBUSER', YIIMP_DBUSER);
define('YAAMP_DBPASSWORD', YIIMP_DBPASSWORD);

define('YAAMP_SITE_URL', YIIMP_SITE_URL);
define('YAAMP_API_URL', YAAMP_SITE_URL);
define('YAAMP_STRATUM_URL', YIIMP_STRATUM_URL);
define('YAAMP_SITE_NAME', YIIMP_SITE_NAME);

define('YAAMP_PRODUCTION', YIIMP_PRODUCTION);
define('YAAMP_LIMIT_ESTIMATE', YIIMP_LIMIT_ESTIMATE);
define('YAAMP_RENTAL', false);
define('YAAMP_USE_NICEHASH_API', false);
define('YAAMP_FEES_SOLO', YIIMP_FEES_SOLO);
define('YAAMP_FEES_MINING', YIIMP_FEES_MINING);
define('YAAMP_FEES_EXCHANGE', YIIMP_FEES_EXCHANGE);
define('YAAMP_FEES_RENTING', YIIMP_FEES_RENTING);
define('YAAMP_TXFEE_RENTING_WD', YIIMP_TXFEE_RENTING_WD);
define('YAAMP_PAYMENTS_FREQ', YIIMP_PAYMENTS_FREQ);
define('YAAMP_PAYMENTS_MINI', YIIMP_PAYMENTS_MINI);
define('YAAMP_ALLOW_EXCHANGE', false);
define('YAAMP_BTCADDRESS', YIIMP_BTCADDRESS);
define('YAAMP_ADMIN_EMAIL', YIIMP_ADMIN_EMAIL);
define('YAAMP_ADMIN_USER', YIIMP_ADMIN_USER);
define('YAAMP_ADMIN_PASS', YIIMP_ADMIN_PASS);
define('YAAMP_ADMIN_IP', YIIMP_ADMIN_IP);
define('YAAMP_ADMIN_WEBCONSOLE', YIIMP_ADMIN_WEBCONSOLE);
define('YAAMP_CREATE_NEW_COINS', false);
define('YAAMP_NOTIFY_NEW_COINS', false);
define('YAAMP_DEFAULT_ALGO', 'scrypt');

define('GITHUB_ACCESSTOKEN', '');

define('SMTP_HOST', '');
define('SMTP_PORT', 25);
define('SMTP_USEAUTH', false);
define('SMTP_USERNAME', '');
define('SMTP_PASSWORD', '');
define('SMTP_DEFAULT_FROM', '');
define('SMTP_DEFAULT_HELO', '${POOL_SITE_HOST}');

define('YAAMP_USE_NGINX', false);
PHP

  cat > "${CONF_ROOT}/keys.php" <<PHP
<?php
defined('YIIMP_MYSQLDUMP_USER') or define('YIIMP_MYSQLDUMP_USER', 'root');
defined('YIIMP_MYSQLDUMP_PASS') or define('YIIMP_MYSQLDUMP_PASS', '${DB_ROOT_PASSWORD}');
defined('YIIMP_MYSQLDUMP_PATH') or define('YIIMP_MYSQLDUMP_PATH', '${BACKUP_ROOT}');
defined('EXCH_AUTO_WITHDRAW') or define('EXCH_AUTO_WITHDRAW', 9999.9999);
PHP

  printf '%s\n' "${POOL_ADMIN_USER}" > "${DATA_ROOT}/admin-user.txt"
  printf '%s\n' "${POOL_ADMIN_PASS}" > "${DATA_ROOT}/admin-password.txt"

  # Legacy Yiimp entrypoints still load these files relative to /var/www.
  ln -sf "${CONF_ROOT}/serverconfig.php" /var/www/serverconfig.php
  ln -sf "${CONF_ROOT}/keys.php" /var/www/keys.php
  ln -sf "${CONF_ROOT}/serverconfig.php" /var/www/yaamp/serverconfig.php
  ln -sf "${CONF_ROOT}/keys.php" /var/www/yaamp/keys.php
}

write_stratum_config() {
  local port="$1"
  local diff="$2"
  local diff_min="$3"
  local diff_max="$4"
  cat > "${STRATUM_ROOT}/scrypt-${port}.conf" <<EOF
[TCP]
server = ${POOL_STRATUM_HOST}
port = ${port}
password = ${STRATUM_NOTIFY_PASSWORD}

[SQL]
host = ${DB_HOST}
database = ${DB_NAME}
username = ${DB_USER}
password = ${DB_PASSWORD}
port = 3306

[STRATUM]
algo = scrypt
difficulty = ${diff}
diff_min = ${diff_min}
diff_max = ${diff_max}
nicehash = ${diff}
nicehash_diff_min = ${diff_min}
nicehash_diff_max = ${diff_max}
max_ttf = 40000
max_cons = 5000
reconnect = 1
renting = 0
EOF
}

write_stratum_configs() {
  write_stratum_config 3331 4000000000 4000000000 4000000000
  write_stratum_config 3332 1000000 1000000 1000000
  write_stratum_config 3333 1000000 1000000 5000000000
  write_stratum_config 3334 2000000000 2000000000 2000000000
  write_stratum_config 3335 500000000 500000000 5000000000
  write_stratum_config 3336 50000000 50000000 5000000000
}

import_base_sql_if_needed() {
  local tables
  tables="$(mysql_exec -e "SHOW TABLES LIKE 'coins';" || true)"
  if [ -z "${tables}" ]; then
    log "importing base Yiimp schema"
    gzip -cd "${SQL_ROOT}/2024-03-06-complete_export.sql.gz" | MYSQL_PWD="${DB_ROOT_PASSWORD}" mysql -h "${DB_HOST}" -u root "${DB_NAME}"
  fi
}

run_migrations() {
  log "applying bundled migrations"
  while IFS= read -r file; do
    [ -n "${file}" ] || continue
    mysql_exec_force_file "${file}"
  done < <(find "${SQL_ROOT}/archived" -maxdepth 1 -type f -name '*.sql' | sort)

  while IFS= read -r file; do
    [ -n "${file}" ] || continue
    mysql_exec_force_file "${file}"
  done < <(find "${SQL_ROOT}" -maxdepth 1 -type f -name '*.sql' | sort)
}

seed_pool_if_needed() {
  local count
  count="$(mysql_exec -e "SELECT COUNT(*) FROM coins WHERE symbol='LTC' AND algo='scrypt';" | tail -n 1 || true)"
  if [ "${count:-0}" = "0" ]; then
    log "seeding clean scrypt pool configuration"
    mysql_exec_force_file "${SEED_SQL}"
  fi
}

normalize_seeded_pool_config() {
  mysql_exec -e "
    UPDATE coins
    SET hasgetinfo = 0
    WHERE algo = 'scrypt' AND symbol IN ('LTC', 'BELLS');
  "

  mysql_exec -e "
    UPDATE coins
    SET auto_exchange = 1
    WHERE algo = 'scrypt' AND symbol IN ('LTC', 'DOGE', 'BELLS', 'JKC', 'PEPE', 'LKY', 'DINGO', 'TRMP');
  "
}

fix_permissions() {
  mkdir -p /var/www/log /var/yiimp2/runtime /var/yiimp2/web/assets
  chown -R www-data:www-data /var/www /var/yiimp2 "${DATA_ROOT}"
}

main() {
  wait_for_db
  write_serverconfig
  write_stratum_configs
  import_base_sql_if_needed
  run_migrations
  seed_pool_if_needed
  normalize_seeded_pool_config
  fix_permissions
  log "bootstrap complete"
}

main "$@"
