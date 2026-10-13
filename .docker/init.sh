#!/bin/bash
set -e

# La base ya está lista: el compose espera a su healthcheck.

if [ ! -f .env ]; then
  echo "Creating .env from .env.example"
  cp .env.example .env
fi

echo "Installing dependencies if missing"
composer install
npm install

if ! grep -qE '^APP_KEY=.+' .env; then
  php artisan key:generate
fi

echo "Executing db setup if necessary"
php artisan migrate --force
php artisan dps:instalar
[ -e public/storage ] || php artisan storage:link

echo "Executing app"
# Lo mismo que el cron del servidor: el planificador vacía la cola de correos
# cada minuto. Sin él, los correos se quedan encolados y Mailpit no recibe nada.
php artisan schedule:work &
npm run dev &
php artisan serve --host=0.0.0.0 --port=8000
