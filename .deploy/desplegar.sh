#!/bin/bash
#
# Despliega a producción DESDE LOCAL: actualiza el servidor a origin/<rama>.
#
#   .deploy/desplegar.sh              enseña qué va a subir y pregunta
#   .deploy/desplegar.sh -p           antes, .deploy/preparar.sh (compilar,
#                                     commitear public/build, push)
#   .deploy/desplegar.sh -s           sin preguntas (también las de preparar)
#   .deploy/desplegar.sh --forzar     repite los pasos del servidor aunque ya
#                                     esté al día
#
# Las opciones se pueden combinar: -p -s --forzar.
#
# El servidor despliega lo que hay en origin/<rama>, no lo que tengas en local.
# Sin -p, lo que no esté commiteado y subido NO llega, y se avisa de ello antes
# de preguntar. Ojo con public/build: si se tocó CSS o JS y no se compiló, el
# servidor seguirá sirviendo los assets viejos.
#
# A qué servidor se conecta lo dice .deploy/servidor.env, que no se versiona.
# Se crea copiando .deploy/servidor.env.example.

main() {
    set -euo pipefail

    local si=0 forzar=0 preparar=0
    while [ $# -gt 0 ]; do
        case "$1" in
            -p|--preparar) preparar=1 ;;
            -s|--si) si=1 ;;
            --forzar) forzar=1 ;;
            -h|--help) sed -n '2,/^$/p' "$0" | sed 's/^# \{0,1\}//'; return 0 ;;
            *) echo "Opción desconocida: $1 (usa --help)" >&2; return 2 ;;
        esac
        shift
    done

    local raiz
    raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
    cd "$raiz"

    local config=.deploy/servidor.env
    if [ ! -f "$config" ]; then
        echo "Falta $config. Cópialo de $config.example y rellénalo." >&2
        return 1
    fi
    # shellcheck source=/dev/null
    source "$config"

    : "${DEPLOY_SSH:?Falta DEPLOY_SSH en $config}"
    local dir="${DEPLOY_DIR:-dps-laravel}"
    local rama="${RAMA:-main}"
    export RAMA="$rama"

    aviso() { printf '\033[33m! %s\033[0m\n' "$*"; }

    if [ "$preparar" -eq 1 ]; then
        local opciones=()
        [ "$si" -eq 1 ] && opciones+=(-s)
        # Así y no "${opciones[@]}": el bash 3.2 de macOS da un array vacío por
        # no definido y set -u lo corta.
        .deploy/preparar.sh ${opciones[@]+"${opciones[@]}"}
    else
        # Sin preparar no se para nada: sólo se dice lo que se queda fuera.
        git fetch --quiet origin "$rama"

        if [ -n "$(git status --porcelain)" ]; then
            aviso "Hay cambios sin commitear. No se despliegan:"
            git status --short | sed 's/^/    /'
        fi

        local sin_subir
        sin_subir="$(git rev-list --count "origin/$rama..HEAD" 2>/dev/null || echo 0)"
        if [ "$sin_subir" -gt 0 ]; then
            aviso "Hay $sin_subir commit(s) sin subir a origin/$rama. No se despliegan:"
            git --no-pager log --oneline "origin/$rama..HEAD" | sed 's/^/    /'
        fi

        local actual
        actual="$(git rev-parse --abbrev-ref HEAD)"
        if [ "$actual" != "$rama" ]; then
            aviso "Estás en «${actual}», pero se despliega origin/${rama}."
        fi
    fi

    # Lo que se despliega es origin/<rama>; después de preparar es lo mismo que
    # HEAD, y sin preparar puede no serlo.
    local destino
    destino="$(git rev-parse "origin/$rama")"

    printf '\n\033[1m== Servidor (%s:%s)\033[0m\n' "$DEPLOY_SSH" "$dir"

    # Qué commit tiene ahora el servidor, para enseñar qué va a cambiar.
    local remoto
    remoto="$(ssh "$DEPLOY_SSH" "git -C $(printf %q "$dir") rev-parse HEAD")"

    if [ "$remoto" = "$destino" ] && [ "$forzar" -eq 0 ]; then
        echo "El servidor ya está en $(git log -1 --format='%h %s' "$destino"). Nada que desplegar (usa --forzar para repetir los pasos)."
        return 0
    fi

    if git cat-file -e "$remoto^{commit}" 2>/dev/null; then
        echo "En el servidor: $(git log -1 --format='%h %s' "$remoto")"
        if [ "$remoto" != "$destino" ]; then
            echo "Se despliega (origin/$rama):"
            git --no-pager log --oneline "$remoto..$destino" | sed 's/^/    /'
        fi
    else
        echo "El servidor está en ${remoto:0:7}, que no existe aquí. Se desplegará $(git log -1 --format='%h %s' "$destino")."
    fi

    if [ "$si" -eq 0 ]; then
        local respuesta
        read -r -p "¿Desplegar en $DEPLOY_SSH? [s/N] " respuesta </dev/tty
        [[ "$respuesta" =~ ^[sSyY]$ ]] || { echo "Cancelado."; return 1; }
    fi

    local argumentos=""
    [ "$forzar" -eq 1 ] && argumentos="--forzar"

    # -t: con terminal, para que la salida de artisan llegue según ocurre.
    ssh -t "$DEPLOY_SSH" "cd $(printf %q "$dir") && RAMA=$(printf %q "$rama") .deploy/actualizar.sh $argumentos"
}

main "$@"
exit $?
