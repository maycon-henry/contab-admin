#!/bin/sh
# Inicialização do container no Render. O plano gratuito não tem "pre-deploy command",
# então as migrations rodam aqui, antes de subir os processos.
set -e
cd /var/www/html

: "${PORT:=10000}"
export PORT

# APP_URL: usa a URL pública do Render quando não foi definida manualmente.
if [ -z "$APP_URL" ] && [ -n "$RENDER_EXTERNAL_URL" ]; then
    export APP_URL="$RENDER_EXTERNAL_URL"
fi

envsubst '${PORT}' < /etc/nginx/templates/app.conf.template > /etc/nginx/conf.d/app.conf

mkdir -p storage/app/private storage/app/public storage/logs \
         storage/framework/cache/data storage/framework/sessions storage/framework/views

php artisan storage:link --force
php artisan migrate --force
php artisan db:seed --class=PermissoesSeeder --force
php artisan optimize
php artisan filament:optimize

exec supervisord -n -c /etc/supervisor/render.conf
