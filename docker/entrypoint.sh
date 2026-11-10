#!/bin/sh
set -e
cd /var/www/html

# pirmajā palaišanā datubāze ir kopija no projekta faila bookloop
if [ ! -f "$DB_DATABASE" ]; then
    echo "BookLoop: veido datubāzi no projekta kopijas"
    cp bookloop "$DB_DATABASE"
fi

mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views storage/logs public/storage/covers
chown -R www-data:www-data storage public/storage "$(dirname "$DB_DATABASE")"

php artisan migrate --force

exec apache2-foreground
