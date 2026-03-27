# ---------------------------------------------------------------------------
# Stage 1 — build JKC, PEPE, DINGO, TRMP from source (no ARM64 releases)
# ---------------------------------------------------------------------------
FROM ubuntu:22.04 AS builder

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get dist-upgrade -y \
    && apt-get install -y --no-install-recommends \
        autoconf automake build-essential ca-certificates curl git \
        libboost-filesystem-dev libboost-program-options-dev \
        libboost-system-dev libboost-thread-dev libboost-chrono-dev \
        libboost-test-dev \
        libdb5.3-dev libdb5.3++-dev \
        libevent-dev libssl-dev libtool pkg-config python3 bsdmainutils \
    && rm -rf /var/lib/apt/lists/*

RUN mkdir -p /opt/prebuilt/JKC /opt/prebuilt/PEPE /opt/prebuilt/DINGO /opt/prebuilt/TRMP

# ----- Junkcoin (BDB 5.3) -----
RUN git clone --depth 1 --branch v3.1.1 \
        https://github.com/Junkcoin-Foundation/junkcoin-core.git /src/junkcoin \
    && cd /src/junkcoin \
    && ./autogen.sh \
    && ./configure --without-gui --without-miniupnpc --without-natpmp \
        --disable-tests --disable-bench --disable-man \
    && make -j"$(nproc)" \
    && install -m 0755 src/junkcoind src/junkcoin-cli /opt/prebuilt/JKC/ \
    && rm -rf /src/junkcoin

# ----- Pepecoin (BDB 4.8 expected, use --with-incompatible-bdb for 5.3) -----
RUN git clone --depth 1 --branch 'v.021.2.1' \
        https://github.com/pepecoin-project/pepecoin.git /src/pepecoin \
    && cd /src/pepecoin \
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
    && ./autogen.sh \
    && ./configure --without-gui --without-miniupnpc --without-natpmp \
        --disable-tests --disable-bench --disable-man \
    && make -j"$(nproc)" \
    && install -m 0755 src/trumpowd src/trumpow-cli /opt/prebuilt/TRMP/ \
    && rm -rf /src/trumpow

# ---------------------------------------------------------------------------
# Stage 2 — final runtime image
# ---------------------------------------------------------------------------
FROM ubuntu:22.04

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
