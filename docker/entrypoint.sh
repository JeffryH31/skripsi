#!/bin/sh
set -e

echo "==> Copying public assets to shared volume..."
# Sync built assets from image into the shared app_public volume
cp -rn /var/www/html/public/. /mnt/public/ 2>/dev/null || true
# Always overwrite the build folder (new deploy)
cp -rf /var/www/html/public/build /mnt/public/build 2>/dev/null || true

echo "==> Waiting for MySQL to be ready..."
until php -r "new PDO('mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}');" 2>/dev/null; do
  echo "    MySQL not ready yet, retrying in 2s..."
  sleep 2
done
echo "    MySQL is ready."

echo "==> Generating app key (if not set)..."
php artisan key:generate --no-interaction --force

echo "==> Running migrations..."
php artisan migrate --force --no-interaction

echo "==> Creating storage symlink..."
php artisan storage:link --force 2>/dev/null || true

echo "==> Caching config, routes, views..."
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo "==> Setting storage permissions..."
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

echo "==> Starting PHP-FPM..."
exec php-fpm
