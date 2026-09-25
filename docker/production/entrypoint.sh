#!/bin/sh
set -eu
umask 027
release_id="$(cat /opt/biblioteca-release)"
export VIEW_COMPILED_PATH="/var/www/html/storage/framework/views/$release_id"
mkdir -p storage/framework/cache/data storage/framework/sessions "$VIEW_COMPILED_PATH" storage/logs bootstrap/cache
php artisan config:cache >/dev/null
exec "$@"
