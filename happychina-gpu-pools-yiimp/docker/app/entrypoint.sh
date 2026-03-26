#!/bin/sh
set -eu

HOST_WEB_ROOT="${HOST_WEB_ROOT:-/mnt/yiimp-web}"
APP_BACKUP_ROOT="${APP_BACKUP_ROOT:-/app-data/backups}"
OVERLAY_ROOT="/opt/happychina-yiimp/overlay"
STATE_ROOT="/app-data/state"

log() {
  printf '[happychina-yiimp] %s\n' "$*"
}

backup_and_copy() {
  rel="$1"
  src="${OVERLAY_ROOT}/${rel}"
  dest="${HOST_WEB_ROOT}/${rel}"
  backup="${APP_BACKUP_ROOT}/original/${rel}"

  if [ ! -f "$src" ]; then
    log "missing overlay file: ${rel}"
    return 1
  fi

  mkdir -p "$(dirname "$dest")"
  mkdir -p "$(dirname "$backup")"

  if [ -f "$dest" ] && [ ! -f "$backup" ]; then
    cp -a "$dest" "$backup"
    log "backed up ${rel}"
  fi

  if [ ! -f "$dest" ] || ! cmp -s "$src" "$dest"; then
    cp "$src" "$dest"
    log "applied ${rel}"
  else
    log "already current ${rel}"
  fi
}

mkdir -p "${APP_BACKUP_ROOT}" "${STATE_ROOT}"

if [ -d "${HOST_WEB_ROOT}" ]; then
  for rel in \
    frontend-rustpool.html \
    payout-addresses.php \
    yaamp/modules/admin/AdminController.php \
    yaamp/modules/admin/balances.php \
    yaamp/modules/api/ApiController.php
  do
    backup_and_copy "$rel"
  done
else
  log "host web root not found at ${HOST_WEB_ROOT}; skipping overlay apply"
fi

date -u +"%Y-%m-%dT%H:%M:%SZ" > "${STATE_ROOT}/last-apply.txt"
exec "$@"
