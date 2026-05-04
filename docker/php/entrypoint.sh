#!/bin/sh
set -e

cd /var/www/html

mkdir -p cache/volt
chown -R www-data:www-data cache 2>/dev/null || true

if [ ! -d vendor ]; then
  composer install --no-interaction --prefer-dist --no-progress
fi

if [ "${RUN_MIGRATE:-1}" = "1" ]; then
  tries=0
  while [ "$tries" -lt 60 ]; do
    if php cli/migrate.php; then
      break
    fi
    tries=$((tries + 1))
    sleep 2
  done
  if [ "$tries" -eq 60 ]; then
    echo "migrate failed after retries" >&2
    exit 1
  fi
fi

exec "$@"
