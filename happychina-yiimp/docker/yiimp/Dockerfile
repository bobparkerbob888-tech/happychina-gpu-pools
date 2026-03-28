FROM mirror.gcr.io/library/ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive
ENV YIIMP_REPO=https://github.com/Kudaraidee/yiimp.git
ENV YIIMP_REF=867dc325c9ba9892316599c8535137052f230650

RUN apt-get update \
    && apt-get dist-upgrade -y \
    && apt-get install -y --no-install-recommends \
        apache2 \
        autoconf \
        automake \
        autotools-dev \
        build-essential \
        ca-certificates \
        composer \
        cron \
        curl \
        default-mysql-client \
        dumb-init \
        git \
        jq \
        less \
        libapache2-mod-php \
        libcurl4-openssl-dev \
        libgd-dev \
        libgmp-dev \
        libicu-dev \
        libmariadb-dev \
        libmariadb-dev-compat \
        libmcrypt-dev \
        libmemcached-dev \
        libonig-dev \
        libsodium-dev \
        libssl-dev \
        libtool \
        libxml2-dev \
        libxslt1-dev \
        libzip-dev \
        logrotate \
        memcached \
        nano \
        netcat-openbsd \
        php-bcmath \
        php-cli \
        php-curl \
        php-dev \
        php-exif \
        php-gd \
        php-intl \
        php-mbstring \
        php-memcache \
        php-mysql \
        php-opcache \
        php-pear \
        php-shmop \
        php-soap \
        php-sysvmsg \
        php-sysvsem \
        php-sysvshm \
        php-xml \
        php-xsl \
        php-zip \
        procps \
        python3 \
        python3-venv \
        sudo \
        supervisor \
        unzip \
        vim \
        xz-utils \
        zlib1g-dev \
    && rm -rf /var/lib/apt/lists/*

RUN a2enmod remoteip rewrite \
    && printf '\n' | pecl install mcrypt \
    && echo "extension=mcrypt.so" >> /etc/php/8.1/apache2/php.ini \
    && echo "extension=mcrypt.so" >> /etc/php/8.1/cli/php.ini

RUN git clone --branch dev "${YIIMP_REPO}" /src/yiimp \
    && git -C /src/yiimp checkout "${YIIMP_REF}" \
    && git -C /src/yiimp submodule update --init --recursive

COPY docker/yiimp/patches/coinbase.cpp /src/yiimp/stratum/coinbase.cpp
COPY docker/yiimp/patches/db.cpp /src/yiimp/stratum/db.cpp
COPY docker/yiimp/patches/coind_template.cpp /src/yiimp/stratum/coind_template.cpp
COPY docker/yiimp/patches/coind_submit.cpp /src/yiimp/stratum/coind_submit.cpp
COPY docker/yiimp/patches/blocks.php /src/yiimp/web/yaamp/core/backend/blocks.php

RUN sed -i 's/-march=native//g; s/-mtune=native//g' /src/yiimp/stratum/Makefile \
    && make -C /src/yiimp/stratum buildonly \
    && install -m 0755 /src/yiimp/stratum/stratum /usr/local/bin/stratum

RUN mkdir -p /opt/happychina-yiimp/sql /var/www /var/yiimp2 \
    && cp -a /src/yiimp/sql/. /opt/happychina-yiimp/sql/ \
    && cp -a /src/yiimp/web/. /var/www/ \
    && cp -a /src/yiimp/yiimp2/. /var/yiimp2/

COPY docker/app/overlay/frontend-rustpool.html /var/www/frontend-rustpool.html
COPY docker/app/overlay/payout-addresses.php /var/www/payout-addresses.php
COPY docker/app/overlay/yaamp/modules/admin/AdminController.php /var/www/yaamp/modules/admin/AdminController.php
COPY docker/app/overlay/yaamp/modules/admin/balances.php /var/www/yaamp/modules/admin/balances.php
COPY docker/app/overlay/yaamp/modules/api/ApiController.php /var/www/yaamp/modules/api/ApiController.php
COPY docker/yiimp/patches/site-index.php /var/www/yaamp/modules/site/index.php

RUN mkdir -p /var/yiimp2/runtime /var/yiimp2/web/assets /var/www/log \
    && chown -R www-data:www-data /var/www /var/yiimp2 \
    && sudo -u www-data composer install --no-interaction --prefer-dist --working-dir /var/yiimp2

COPY docker/yiimp/config/000-default.conf /etc/apache2/sites-available/000-default.conf
COPY docker/yiimp/config/001-yiimp2.conf /etc/apache2/sites-available/001-yiimp2.conf
COPY docker/yiimp/config/ports.conf /etc/apache2/ports.conf
COPY docker/yiimp/config/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/yiimp/bootstrap/seed.sql /opt/happychina-yiimp/bootstrap/seed.sql
COPY docker/yiimp/bootstrap.sh /usr/local/bin/yiimp-bootstrap.sh
COPY docker/yiimp/entrypoint.sh /usr/local/bin/yiimp-entrypoint.sh

RUN a2ensite 001-yiimp2 \
    && chmod +x /usr/local/bin/yiimp-bootstrap.sh /usr/local/bin/yiimp-entrypoint.sh \
    && mkdir -p /etc/yiimp/stratum /data/yiimp

ENTRYPOINT ["/usr/bin/dumb-init", "--", "/usr/local/bin/yiimp-entrypoint.sh"]
