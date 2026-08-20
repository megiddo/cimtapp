#!/bin/sh
set -eu

cd /var/www/cimtapp/backend

if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
  composer install --no-interaction --prefer-dist
fi

mkdir -p /var/www/cimtapp/data /var/www/cimtapp/backend/logs /var/www/cimtapp/backend/var/cache

if [ -d /var/www/cimtapp/backend/public-php ]; then
  cp -f /var/www/cimtapp/backend/public-php/index.php /var/www/cimtapp/backend/public/index.php
  cp -f /var/www/cimtapp/backend/public-php/.htaccess /var/www/cimtapp/backend/public/.htaccess
  cp -f /var/www/cimtapp/backend/public-php/router.php /var/www/cimtapp/backend/public/router.php
fi

chown -R www-data:www-data /var/www/cimtapp/data /var/www/cimtapp/backend/logs /var/www/cimtapp/backend/var || true

exec "$@"
