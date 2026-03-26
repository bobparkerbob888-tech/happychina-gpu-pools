FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update \
    && apt-get dist-upgrade -y \
    && apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        dumb-init \
        jq \
        netcat-openbsd \
        procps \
        supervisor \
        tar \
        unzip \
        xz-utils \
    && rm -rf /var/lib/apt/lists/*

COPY docker/daemons/entrypoint.sh /usr/local/bin/yiimp-daemons-entrypoint.sh
COPY docker/daemons/coins.tsv /opt/happychina-yiimp/daemons/coins.tsv

RUN chmod +x /usr/local/bin/yiimp-daemons-entrypoint.sh \
    && mkdir -p /data/daemons /etc/supervisor/conf.d

ENTRYPOINT ["/usr/bin/dumb-init", "--", "/usr/local/bin/yiimp-daemons-entrypoint.sh"]
