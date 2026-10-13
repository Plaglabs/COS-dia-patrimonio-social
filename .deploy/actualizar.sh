#!/bin/bash
#
# Actualiza la aplicación EN EL SERVIDOR a lo último de origin/<rama>.
#
#   .deploy/actualizar.sh            sólo si hay commits nuevos
#   .deploy/actualizar.sh --forzar   repite todos los pasos aunque no haya nada nuevo
#
# El despliegue es manual: lo lanza una persona, por ssh. Se ejecuta como el
# usuario de la cuenta cPanel, nunca como root: los archivos que escriba
# (vendor, cachés) tienen que ser de ese usuario o PHP-FPM no podrá tocarlos.
#
# Cuando no hay nada nuevo no toca nada, tampoco la caché. Cada despliegue de
# verdad sí la vacía, así que nada que deba sobrevivir puede vivir en ella
# (DOCUMENTACION-TECNICA.md, punto 5).
#
# Esto NO sustituye al cron del planificador (`schedule:run` cada minuto), que
# es otra cosa y sigue haciendo falta: sin él no sale ni un correo.
#
# Si un paso falla, el sitio SE QUEDA en mantenimiento a propósito: con el
# código nuevo y las dependencias o la base a medias, es mejor un aviso de
# mantenimiento que una aplicación rota sirviendo errores. El mensaje final dice
# cómo salir.
#
# Variables que se pueden sobrescribir desde el entorno:
#   PHP       el binario de PHP 8.4   (por defecto, el de EasyApache si existe)
#   COMPOSER  cómo invocar Composer   (por defecto, ~/bin/composer.phar)
#   RAMA      la rama a desplegar     (por defecto, main)

# Todo va dentro de main(): bash lee el script entero antes de ejecutarlo, así
# que el `git pull`, que reescribe este mismo archivo, no le cambia el texto a
# mitad de camino.
main() {
    set -euo pipefail

    local forzar=0
    case "${1:-}" in
        --forzar) forzar=1 ;;
        '') ;;
        -h|--help) sed -n '2,/^$/p' "$0" | sed 's/^# \{0,1\}//'; return 0 ;;
        *) echo "Opción desconocida: $1 (usa --help)" >&2; return 2 ;;
    esac

    # La raíz del repo es la carpeta de encima de .deploy/, esté donde esté clonado.
    local raiz
    raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
    cd "$raiz"

    local rama="${RAMA:-main}"

    # En un servidor con varios PHP, el `php` del PATH suele ser el del sistema y
    # no el 8.4 que usa la web; el cron, además, no lee los alias del .bashrc.
    local php="${PHP:-}"
    if [ -z "$php" ]; then
        if [ -x /opt/cpanel/ea-php84/root/usr/bin/php ]; then
            php=/opt/cpanel/ea-php84/root/usr/bin/php
        else
            php="$(command -v php)"
        fi
    fi

    # allow_url_fopen está apagado en el servidor y Composer lo necesita para
    # descargar. Se enciende sólo para este proceso; la web sigue sin él.
    local composer="${COMPOSER:-}"
    if [ -z "$composer" ]; then
        if [ -f "$HOME/bin/composer.phar" ]; then
            composer="$php -d allow_url_fopen=1 $HOME/bin/composer.phar"
        elif command -v composer >/dev/null; then
            composer="$php -d allow_url_fopen=1 $(command -v composer)"
        else
            echo "No encuentro Composer. Instálalo en ~/bin/composer.phar o pasa COMPOSER=..." >&2
            return 1
        fi
    fi

    # Dos personas desplegando a la vez: la segunda espera a la primera.
    # El candado va en storage/framework/cache, que git ya ignora entero.
    exec 9>"$raiz/storage/framework/cache/despliegue.lock"
    if command -v flock >/dev/null && ! flock -w 300 9; then
        echo "Hay otro despliegue en marcha desde hace más de 5 minutos. No sigo." >&2
        return 1
    fi

    # Comprobaciones que no tocan nada: si fallan, el sitio sigue como estaba.
    [ -f .env ] || { echo "Falta $raiz/.env" >&2; return 1; }

    "$php" -r 'exit(version_compare(PHP_VERSION, "8.4.1", ">=") ? 0 : 1);' || {
        echo "$php es PHP $("$php" -r 'echo PHP_VERSION;'); el composer.lock pide 8.4.1 o más." >&2
        return 1
    }

    # Un archivo versionado tocado a mano en el servidor haría fallar el pull a
    # mitad del despliegue, con el sitio ya en mantenimiento. Mejor saberlo antes.
    # Lo que no está versionado (.env, un .user.ini de cPanel) no cuenta.
    if ! git diff --quiet || ! git diff --cached --quiet; then
        echo "Hay cambios sin commitear en archivos versionados del servidor:" >&2
        git status --short --untracked-files=no >&2
        echo "Revísalos (git diff) y descártalos o súbelos antes de desplegar." >&2
        return 1
    fi

    git fetch --quiet origin "$rama"

    local actual nuevo
    actual="$(git rev-parse HEAD)"
    nuevo="$(git rev-parse "origin/$rama")"

    if [ "$actual" = "$nuevo" ] && [ "$forzar" -eq 0 ]; then
        echo "Ya está al día: $(git log -1 --format='%h %s'). Nada que desplegar (usa --forzar para repetir los pasos)."
        return 0
    fi

    paso() { echo "[$(date '+%F %T')] $*"; }

    # Desde aquí, si algo falla, se avisa y el sitio queda en mantenimiento.
    trap 'echo; paso "FALLÓ el despliegue de $(git rev-parse --short HEAD). El sitio sigue en mantenimiento."; echo "Arregla el problema y vuelve a ejecutar: $0 --forzar  (o, para salir sin arreglar: $php artisan up)"' ERR

    paso "Desplegando ${actual:0:7} → ${nuevo:0:7} (origin/$rama)"
    git --no-pager log --oneline "$actual..$nuevo" 2>/dev/null | sed 's/^/    /' || true

    paso "Modo mantenimiento"
    "$php" artisan down --retry=30 || true

    paso "git pull"
    git merge --ff-only --quiet "origin/$rama"

    paso "Dependencias de PHP"
    $composer install --no-dev --optimize-autoloader --no-interaction --no-progress

    paso "Migraciones"
    "$php" artisan migrate --force

    # Idempotente. Sin esto, una base nueva se queda sin plantillas de correo ni
    # ajustes, y no da ningún error (DOCUMENTACION-TECNICA.md, «El cron migra
    # pero NO siembra»).
    paso "dps:instalar"
    "$php" artisan dps:instalar

    # public/storage no se versiona; si falta, las imágenes subidas dan 404.
    if [ ! -e public/storage ]; then
        paso "storage:link"
        "$php" artisan storage:link
    fi

    paso "Cachés"
    "$php" artisan optimize:clear
    "$php" artisan optimize

    # El worker de la cola es de vida corta (lo lanza el planificador cada
    # minuto), pero si hay uno en marcha con el código viejo, esto le pide que
    # termine al acabar el trabajo en curso.
    "$php" artisan queue:restart

    "$php" artisan up
    trap - ERR

    paso "Listo: $(git log -1 --format='%h %s')"
}

main "$@"
exit $?
