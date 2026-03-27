#!/bin/bash
set -euo pipefail

DATA_ROOT=/data/daemons
CACHE_ROOT="${DATA_ROOT}/cache"
BIN_ROOT="${DATA_ROOT}/bin"
RUN_ROOT="${DATA_ROOT}/run"
SUPERVISOR_CONF=/etc/supervisor/conf.d/daemons.conf
NOTIFY_SCRIPT=/usr/local/bin/scrypt-blocknotify-all.sh
COINS_FILE=/opt/happychina-yiimp/daemons/coins.tsv
PREBUILT_ROOT=/opt/happychina-yiimp/daemons/prebuilt

RPC_USER="${RPC_USER:-umbrel}"
RPC_PASSWORD="${RPC_PASSWORD:-umbrel}"
STRATUM_HOST="${STRATUM_HOST:-yiimp}"
STRATUM_NOTIFY_PASSWORD="${STRATUM_NOTIFY_PASSWORD:-blocknotify}"
STRATUM_PORTS="${STRATUM_PORTS:-3331 3332 3333 3334 3335 3336}"
GITHUB_TOKEN="${GITHUB_TOKEN:-}"
HOST_ARCH="$(uname -m)"

log() {
  printf '[yiimp-daemons] %s\n' "$*"
}

github_api() {
  local url="$1"
  if [ -n "${GITHUB_TOKEN}" ]; then
    curl -fsSL \
      -H "Accept: application/vnd.github+json" \
      -H "Authorization: Bearer ${GITHUB_TOKEN}" \
      "${url}"
  else
    curl -fsSL \
      -H "Accept: application/vnd.github+json" \
      "${url}"
  fi
}

validate_archive() {
  local archive_path="$1"

  case "${archive_path}" in
    *.tar.gz|*.tgz)
      tar -tzf "${archive_path}" >/dev/null
      ;;
    *.tar.xz)
      tar -tJf "${archive_path}" >/dev/null
      ;;
    *.zip)
      unzip -tqq "${archive_path}" >/dev/null
      ;;
    *)
      return 1
      ;;
  esac
}

download_release_asset() {
  local asset_url="$1"
  local archive_path="$2"
  local tmp_dir
  local tmp_path

  tmp_dir="$(mktemp -d)"
  tmp_path="${tmp_dir}/$(basename "${archive_path}")"

  if [ -n "${GITHUB_TOKEN}" ]; then
    curl -fL \
      --retry 6 \
      --retry-delay 2 \
      --retry-all-errors \
      --connect-timeout 20 \
      -H "Authorization: Bearer ${GITHUB_TOKEN}" \
      -o "${tmp_path}" \
      "${asset_url}"
  else
    curl -fL \
      --retry 6 \
      --retry-delay 2 \
      --retry-all-errors \
      --connect-timeout 20 \
      -o "${tmp_path}" \
      "${asset_url}"
  fi

  if ! validate_archive "${tmp_path}"; then
    rm -rf "${tmp_dir}"
    log "downloaded archive failed validation: ${asset_url}"
    return 1
  fi

  mv -f "${tmp_path}" "${archive_path}"
  rmdir "${tmp_dir}"
}

extract_archive() {
  local archive_path="$1"
  local output_dir="$2"

  case "${archive_path}" in
    *.tar.gz|*.tgz)
      tar -xzf "${archive_path}" -C "${output_dir}"
      ;;
    *.tar.xz)
      tar -xJf "${archive_path}" -C "${output_dir}"
      ;;
    *.zip)
      unzip -qo "${archive_path}" -d "${output_dir}"
      ;;
    *)
      log "unsupported archive format: ${archive_path}"
      exit 1
      ;;
  esac
}

install_coin_release() {
  local symbol="$1"
  local repo="$2"
  local asset_pattern="${3//__ARCH__/${HOST_ARCH}}"
  local daemon_name="$4"
  local cli_name="$5"
  local install_root="${BIN_ROOT}/${symbol}"

  # Handle coins compiled from source and baked into the image
  if [ "${asset_pattern}" = "PREBUILT" ]; then
    local prebuilt_dir="${PREBUILT_ROOT}/${symbol}"
    if [ -x "${prebuilt_dir}/${daemon_name}" ]; then
      mkdir -p "${install_root}"
      ln -sfn "${prebuilt_dir}" "${install_root}/current"
      log "${symbol}: using prebuilt binary"
      return 0
    fi
    log "${symbol}: prebuilt binary not found at ${prebuilt_dir}/${daemon_name}"
    exit 1
  fi

  local release_json
  local tag
  local asset_url
  local asset_name
  local symbol_cache
  local release_dir
  local archive_path
  local extract_dir
  local daemon_path
  local cli_path

  release_json="$(github_api "https://api.github.com/repos/${repo}/releases/latest")"
  tag="$(jq -r '.tag_name // empty' <<<"${release_json}")"
  asset_url="$(
    jq -r --arg re "${asset_pattern}" '.assets[] | select(.name | test($re)) | .browser_download_url' <<<"${release_json}" \
      | head -n 1
  )"

  if [ -z "${tag}" ] || [ -z "${asset_url}" ]; then
    log "unable to resolve release asset for ${symbol} from ${repo}"
    exit 1
  fi

  asset_name="$(basename "${asset_url}")"
  symbol_cache="${CACHE_ROOT}/${symbol}"
  release_dir="${install_root}/${tag}"
  archive_path="${symbol_cache}/${asset_name}"

  mkdir -p "${symbol_cache}" "${install_root}"

  if [ ! -x "${release_dir}/${daemon_name}" ]; then
    log "installing ${symbol} ${tag}"
    if [ -f "${archive_path}" ] && ! validate_archive "${archive_path}"; then
      log "cached archive for ${symbol} is invalid, redownloading"
      rm -f "${archive_path}"
    fi

    if [ ! -f "${archive_path}" ]; then
      download_release_asset "${asset_url}" "${archive_path}"
    fi

    rm -rf "${release_dir}"
    mkdir -p "${release_dir}"
    extract_dir="$(mktemp -d)"
    extract_archive "${archive_path}" "${extract_dir}"

    daemon_path="$(find "${extract_dir}" -type f -name "${daemon_name}" | head -n 1)"
    cli_path="$(find "${extract_dir}" -type f -name "${cli_name}" | head -n 1)"

    if [ -z "${daemon_path}" ]; then
      log "daemon binary ${daemon_name} not found in ${asset_name}"
      rm -rf "${extract_dir}"
      exit 1
    fi

    install -m 0755 "${daemon_path}" "${release_dir}/${daemon_name}"
    if [ -n "${cli_path}" ]; then
      install -m 0755 "${cli_path}" "${release_dir}/${cli_name}"
    fi

    rm -rf "${extract_dir}"
  fi

  ln -sfn "${release_dir}" "${install_root}/current"
}

write_notify_script() {
  cat > "${NOTIFY_SCRIPT}" <<'EOF'
#!/bin/bash
set -euo pipefail

coinid="${1:-}"
blockhash="${2:-}"
host="${STRATUM_HOST:-yiimp}"
password="${STRATUM_NOTIFY_PASSWORD:-blocknotify}"
ports="${STRATUM_PORTS:-3331 3332 3333 3334 3335 3336}"

if [ -z "${coinid}" ] || [ -z "${blockhash}" ]; then
  exit 1
fi

payload=$(printf '{"id":1,"method":"mining.update_block","params":["%s",%s,"%s"]}\n' "${password}" "${coinid}" "${blockhash}")

for port in ${ports}; do
  printf '%s' "${payload}" | nc -w 2 "${host}" "${port}" >/dev/null 2>&1 || true
done
EOF

  chmod +x "${NOTIFY_SCRIPT}"
}

write_coin_conf() {
  local symbol="$1"
  local coinid="$2"
  local rpc_port="$3"
  local p2p_port="$4"
  local conf_name="$5"
  local coin_dir="${RUN_ROOT}/${symbol}"
  local conf_path="${coin_dir}/${conf_name}"

  mkdir -p "${coin_dir}"

  cat > "${conf_path}" <<EOF
server=1
daemon=0
listen=1
printtoconsole=1
txindex=1
maxconnections=32
rpcuser=${RPC_USER}
rpcpassword=${RPC_PASSWORD}
rpcbind=0.0.0.0
rpcallowip=0.0.0.0/0
rpcport=${rpc_port}
port=${p2p_port}
blocknotify=${NOTIFY_SCRIPT} ${coinid} %s
EOF
}

render_supervisord() {
  cat > "${SUPERVISOR_CONF}" <<EOF
[supervisord]
nodaemon=true
logfile=/dev/null
pidfile=/tmp/supervisord.pid

EOF

  while IFS='|' read -r symbol coinid repo asset_pattern daemon_name cli_name rpc_port p2p_port conf_name; do
    [ -n "${symbol}" ] || continue
    cat >> "${SUPERVISOR_CONF}" <<EOF
[program:${symbol,,}]
command=${BIN_ROOT}/${symbol}/current/${daemon_name} -datadir=${RUN_ROOT}/${symbol} -conf=${RUN_ROOT}/${symbol}/${conf_name} -printtoconsole
autostart=true
autorestart=true
startsecs=10
stdout_logfile=/dev/stdout
stdout_logfile_maxbytes=0
stderr_logfile=/dev/stderr
stderr_logfile_maxbytes=0

EOF
  done < "${COINS_FILE}"
}

prepare_daemons() {
  mkdir -p "${DATA_ROOT}" "${CACHE_ROOT}" "${BIN_ROOT}" "${RUN_ROOT}"
  write_notify_script

  while IFS='|' read -r symbol coinid repo asset_pattern daemon_name cli_name rpc_port p2p_port conf_name; do
    [ -n "${symbol}" ] || continue
    install_coin_release "${symbol}" "${repo}" "${asset_pattern}" "${daemon_name}" "${cli_name}"
    write_coin_conf "${symbol}" "${coinid}" "${rpc_port}" "${p2p_port}" "${conf_name}"
  done < "${COINS_FILE}"
}

main() {
  prepare_daemons
  render_supervisord
  log "starting wallet daemons"
  exec /usr/bin/supervisord -n -c /etc/supervisor/supervisord.conf
}

main "$@"
