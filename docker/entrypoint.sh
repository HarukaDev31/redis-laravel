#!/bin/sh
set -e

# storage/ viene montado desde la carpeta del servidor: asegurar estructura y permisos
mkdir -p storage/app/public storage/fonts storage/logs \
    storage/framework/cache/data storage/framework/sessions storage/framework/views
chown -R www-data:www-data storage bootstrap/cache

# No se usa config:cache: el código lee env() directamente en los jobs
php artisan view:clear >/dev/null 2>&1 || true

exec "$@"
