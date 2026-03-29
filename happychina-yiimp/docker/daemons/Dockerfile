# ---------------------------------------------------------------------------
# Stage 1 — build JKC, PEPE, DINGO, TRMP from source (no ARM64 releases)
# ---------------------------------------------------------------------------
FROM mirror.gcr.io/library/ubuntu:22.04 AS builder

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get dist-upgrade -y \
    && apt-get install -y --no-install-recommends \
        autoconf automake build-essential ca-certificates curl git \
        libboost-filesystem-dev libboost-program-options-dev \
        libboost-system-dev libboost-thread-dev libboost-chrono-dev \
        libboost-test-dev \
        libdb5.3-dev libdb5.3++-dev \
        libevent-dev libfmt-dev libssl-dev libtool pkg-config python3 bsdmainutils \
        unzip xz-utils \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p \
      /opt/prebuilt/LTC \
      /opt/prebuilt/DOGE \
      /opt/prebuilt/BELLS \
      /opt/prebuilt/JKC \
      /opt/prebuilt/PEPE \
      /opt/prebuilt/LKY \
      /opt/prebuilt/DINGO \
      /opt/prebuilt/FLOP \
      /opt/prebuilt/CRC \
      /opt/prebuilt/TRMP

RUN bash -lc '\
  set -euo pipefail; \
  fetch_release() { \
    local url="$1" outdir="$2" daemon_name="$3" cli_name="$4"; \
    local tmp_dir archive_path daemon_path cli_path; \
    tmp_dir="$(mktemp -d)"; \
    archive_path="${tmp_dir}/asset"; \
    curl -fsSL --retry 6 --retry-delay 2 --retry-all-errors -o "${archive_path}" "${url}"; \
    case "${url}" in \
      *.tar.gz|*.tgz) tar -xzf "${archive_path}" -C "${tmp_dir}" ;; \
      *.tar.xz) tar -xJf "${archive_path}" -C "${tmp_dir}" ;; \
      *.zip) unzip -q "${archive_path}" -d "${tmp_dir}" ;; \
      *) echo "unsupported archive: ${url}" >&2; exit 1 ;; \
    esac; \
    daemon_path="$(find "${tmp_dir}" -type f -name "${daemon_name}" | head -n 1)"; \
    cli_path="$(find "${tmp_dir}" -type f -name "${cli_name}" | head -n 1)"; \
    test -n "${daemon_path}"; \
    test -n "${cli_path}"; \
    install -m 0755 "${daemon_path}" "${outdir}/${daemon_name}"; \
    install -m 0755 "${cli_path}" "${outdir}/${cli_name}"; \
    rm -rf "${tmp_dir}"; \
  }; \
  fetch_release "https://github.com/litecoin-project/litecoin/releases/download/v0.21.4/litecoin-0.21.4-x86_64-linux-gnu.tar.gz" /opt/prebuilt/LTC litecoind litecoin-cli; \
  fetch_release "https://github.com/dogecoin/dogecoin/releases/download/v1.14.9/dogecoin-1.14.9-x86_64-linux-gnu.tar.gz" /opt/prebuilt/DOGE dogecoind dogecoin-cli; \
  fetch_release "https://github.com/Nintondo/bellscoinV3/releases/download/v3.0.0-rc2/bells-3.0.0-rc2-x86_64-linux-gnu.tar.gz" /opt/prebuilt/BELLS bellsd bells-cli; \
  fetch_release "https://github.com/LuckycoinFoundation/Luckycoin/releases/download/v5.0.1/luckycoin-5.0.1-x86_64-linux-gnu.tar.gz" /opt/prebuilt/LKY luckycoind luckycoin-cli; \
  fetch_release "https://github.com/Flopcoin/Flopcoin/releases/download/v2.0.0.0/flopcoin-v2.0.0.0-linux.tar.gz" /opt/prebuilt/FLOP flopcoind flopcoin-cli; \
  fetch_release "https://github.com/craftcoin2013/craftcoinV3/releases/download/v3.0.3/craftcoin-3.0.0-linux-x86_64.tar.xz" /opt/prebuilt/CRC craftcoind craftcoin-cli'

# ----- Junkcoin (BDB 5.3) -----
RUN git clone --depth 1 --branch v3.1.1 \
        https://github.com/Junkcoin-Foundation/junkcoin-core.git /src/junkcoin \
    && cd /src/junkcoin \
    && chmod +x autogen.sh \
    && ./autogen.sh \
    && ./configure --without-gui --without-miniupnpc --without-natpmp \
        --disable-tests --disable-bench --disable-man \
    && make -j"$(nproc)" \
    && install -m 0755 src/junkcoind src/junkcoin-cli /opt/prebuilt/JKC/ \
    && rm -rf /src/junkcoin

# ----- Pepecoin -----
RUN git clone --depth 1 --branch v1.1.0 \
        https://github.com/pepecoinppc/pepecoin.git /src/pepecoin \
    && cd /src/pepecoin \
    && chmod +x autogen.sh share/genbuild.sh \
    && ./autogen.sh \
    && ./configure --without-gui --without-miniupnpc --without-natpmp \
        --disable-tests --disable-bench --disable-man \
        --with-incompatible-bdb \
    && make -j"$(nproc)" \
    && install -m 0755 src/pepecoind src/pepecoin-cli /opt/prebuilt/PEPE/ \
    && rm -rf /src/pepecoin

# ----- Dingocoin (BDB 5.3) -----
RUN git clone --depth 1 --branch v1.18.0.0 \
        https://github.com/dingocoin/dingocoin.git /src/dingocoin \
    && cd /src/dingocoin \
    && chmod +x autogen.sh \
    && ./autogen.sh \
    && ./configure --without-gui --without-miniupnpc --without-natpmp \
        --disable-tests --disable-bench --disable-man \
    && make -j"$(nproc)" \
    && install -m 0755 src/dingocoind src/dingocoin-cli /opt/prebuilt/DINGO/ \
    && rm -rf /src/dingocoin

# ----- TrumPOW (BDB 5.3) -----
RUN git clone --depth 1 --branch v1.2.1 \
        https://github.com/trumpowppc/trumpow.git /src/trumpow \
    && cd /src/trumpow \
    && chmod +x autogen.sh \
    && ./autogen.sh \
    && ./configure --without-gui --without-miniupnpc --without-natpmp \
        --disable-tests --disable-bench --disable-man \
    && make -j"$(nproc)" \
    && install -m 0755 src/trumpowd src/trumpow-cli /opt/prebuilt/TRMP/ \
    && rm -rf /src/trumpow

# ---------------------------------------------------------------------------
# Stage 2 — final runtime image
# ---------------------------------------------------------------------------
FROM mirror.gcr.io/library/ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get dist-upgrade -y \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        dumb-init \
        jq \
        libboost-filesystem1.74.0 \
        libboost-program-options1.74.0 \
        libboost-system1.74.0 \
        libboost-thread1.74.0 \
        libboost-chrono1.74.0 \
        libdb5.3 libdb5.3++ \
        libevent-2.1-7 libevent-pthreads-2.1-7 \
        libfmt8 \
        libssl3 \
        netcat-openbsd \
        procps \
        supervisor \
        tar \
        unzip \
        xz-utils \
    && rm -rf /var/lib/apt/lists/*

COPY --from=builder /opt/prebuilt/ /opt/happychina-yiimp/daemons/prebuilt/

COPY docker/daemons/entrypoint.sh /usr/local/bin/yiimp-daemons-entrypoint.sh
COPY docker/daemons/coins.tsv /opt/happychina-yiimp/daemons/coins.tsv

RUN chmod +x /usr/local/bin/yiimp-daemons-entrypoint.sh \
    && mkdir -p /data/daemons /etc/supervisor/conf.d

ENTRYPOINT ["/usr/bin/dumb-init", "--", "/usr/local/bin/yiimp-daemons-entrypoint.sh"]
