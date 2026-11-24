#!/bin/sh
set -e

php artisan package:discover --ansi

# Reintenta por si la BD externa tarda en responder
i=0
until php artisan migrate --force; do
  i=$((i+1))
  if [ "$i" -ge 5 ]; then
    echo "No se pudo migrar tras 5 intentos"
    exit 1
  fi
  echo "Reintentando migración en 5s..."
  sleep 5
done

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port=${PORT:-10000}