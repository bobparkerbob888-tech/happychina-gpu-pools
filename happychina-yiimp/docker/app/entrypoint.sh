#!/bin/sh
set -eu

HOST_WEB_ROOT="${HOST_WEB_ROOT:-/mnt/yiimp-web}"
APP_BACKUP_ROOT="${APP_BACKUP_ROOT:-/app-data/backups}"
OVERLAY_ROOT="/opt/happychina-yiimp/overlay"
STATE_ROOT="/app-data/state"

log() {
  printf '[happychina-yiimp] %s\n' "$*"
}

patch_host_coins_backend() {
  dest="${HOST_WEB_ROOT}/yaamp/core/backend/coins.php"

  if [ ! -f "$dest" ]; then
    log "host coins backend not found at ${dest}; skipping yiimp readiness patch"
    return 0
  fi

  python3 - "$dest" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
text = path.read_text()
old = "\t\tif($template && isset($template['coinbasevalue']))\n\t\t{\n\t\t\t$coin->reward = $template['coinbasevalue']/100000000*$coin->reward_mul;\n"
new = "\t\tif($template && isset($template['coinbasevalue']))\n\t\t{\n\t\t\t$coin->auto_ready = ($coin->connections > 0);\n\t\t\t$coin->reward = $template['coinbasevalue']/100000000*$coin->reward_mul;\n"

if new in text:
    raise SystemExit(0)

if old not in text:
    raise SystemExit("expected yiimp coins.php block not found")

path.write_text(text.replace(old, new, 1))
PY

  log "patched yaamp/core/backend/coins.php readiness logic"
}

patch_host_libdbo() {
  dest="${HOST_WEB_ROOT}/yaamp/core/common/libdbo.php"

  if [ ! -f "$dest" ]; then
    log "host libdbo not found at ${dest}; skipping yiimp model-loader patch"
    return 0
  fi

  python3 - "$dest" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
text = path.read_text()
needle = "///////////////////////////////////////////////////////////////////////\n\nfunction getdbo($class, $id)\n{\n"
insert = "///////////////////////////////////////////////////////////////////////\n\nfunction yiimp_require_model($class)\n{\n\tif(class_exists($class, false)) return;\n\n\t$modelFile = YAAMP_HTDOCS.\"/yaamp/models/{$class}Model.php\";\n\tif(is_file($modelFile))\n\t\trequire_once($modelFile);\n}\n\nfunction getdbo($class, $id)\n{\n\tyiimp_require_model($class);\n"

if "function yiimp_require_model($class)" not in text:
    if needle not in text:
        raise SystemExit("expected yiimp libdbo block not found")
    text = text.replace(needle, insert, 1)

text = text.replace("function getdbosql($class, $sql='1', $params=array())\n{\n//\tdebuglog(\"$class, $sql\");\n\treturn CActiveRecord::model($class)->find($sql, $params);\n}", "function getdbosql($class, $sql='1', $params=array())\n{\n//\tdebuglog(\"$class, $sql\");\n\tyiimp_require_model($class);\n\treturn CActiveRecord::model($class)->find($sql, $params);\n}")
text = text.replace("function getdbolist($class, $sql='1', $params=array())\n{\n//\tdebuglog(\"sql $sql\");\n\treturn CActiveRecord::model($class)->findAll($sql, $params);\n}", "function getdbolist($class, $sql='1', $params=array())\n{\n//\tdebuglog(\"sql $sql\");\n\tyiimp_require_model($class);\n\treturn CActiveRecord::model($class)->findAll($sql, $params);\n}")
text = text.replace("function getdbocount($class, $sql='1', $params=array())\n{\n//\tdebuglog(\"sql $sql\");\n\treturn CActiveRecord::model($class)->count($sql, $params);\n}", "function getdbocount($class, $sql='1', $params=array())\n{\n//\tdebuglog(\"sql $sql\");\n\tyiimp_require_model($class);\n\treturn CActiveRecord::model($class)->count($sql, $params);\n}")
text = text.replace("function getdbolistWith($model, $with, $criteria)\n{\n\treturn CActiveRecord::model($model)->with($with)->findAll($criteria);\n}", "function getdbolistWith($model, $with, $criteria)\n{\n\tyiimp_require_model($model);\n\treturn CActiveRecord::model($model)->with($with)->findAll($criteria);\n}")

path.write_text(text)
PY

  log "patched yaamp/core/common/libdbo.php model loader"
}

patch_host_cronjob_controller() {
  dest="${HOST_WEB_ROOT}/yaamp/modules/thread/CronjobController.php"

  if [ ! -f "$dest" ]; then
    log "host CronjobController not found at ${dest}; skipping exchange-guard patch"
    return 0
  fi

  python3 - "$dest" <<'PY'
from pathlib import Path
import sys

path = Path(sys.argv[1])
text = path.read_text()
text = text.replace("case 1:\n\t\t\t\tif(!YAAMP_PRODUCTION) break;\n", "case 1:\n\t\t\t\tif(!YAAMP_PRODUCTION || !YAAMP_ALLOW_EXCHANGE) break;\n")
text = text.replace("case 2:\n\t\t\t\tif(!YAAMP_PRODUCTION) break;\n", "case 2:\n\t\t\t\tif(!YAAMP_PRODUCTION || !YAAMP_ALLOW_EXCHANGE) break;\n")
text = text.replace("case 5:\n\t\t\t\tTradingSellCoins();\n", "case 5:\n\t\t\t\tif(!YAAMP_ALLOW_EXCHANGE) break;\n\t\t\t\tTradingSellCoins();\n")
path.write_text(text)
PY

  log "patched yaamp/modules/thread/CronjobController.php exchange guards"
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

  patch_host_coins_backend
  patch_host_libdbo
  patch_host_cronjob_controller
else
  log "host web root not found at ${HOST_WEB_ROOT}; skipping overlay apply"
fi

date -u +"%Y-%m-%dT%H:%M:%SZ" > "${STATE_ROOT}/last-apply.txt"
exec "$@"
