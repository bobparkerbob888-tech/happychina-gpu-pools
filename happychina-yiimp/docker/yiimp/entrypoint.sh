#!/bin/bash
set -euo pipefail

/usr/local/bin/yiimp-bootstrap.sh
exec /usr/bin/supervisord -c /etc/supervisor/conf.d/supervisord.conf
