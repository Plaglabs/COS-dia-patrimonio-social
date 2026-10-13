#!/bin/bash
#
# Deja el repositorio listo para desplegar, EN LOCAL.
#
#   .deploy/preparar.sh        pregunta antes de commitear o hacer push
#   .deploy/preparar.sh -s     contesta que sí a todo (sin preguntas)
#
# Comprueba que estás en la rama que se despliega, sin cambios sin commitear y
# sin commits del remoto que no tengas; compila los assets dentro del
# contenedor de .docker/ y, si la compilación cambia public/build, ofrece
# commitearla; y, si hay commits sin subir, ofrece el push.
#
# public/build se versiona porque en el servidor no hay Node: lo que no se
# compile y se commitee aquí no llega nunca (DOCUMENTACION-TECNICA.md, punto 5).
#
# Variables que se pueden sobrescribir desde el entorno:
#   RAMA      la rama que se despliega   (por defecto, main)

main() {
    set -euo pipefail

    local si=0
    case "${1:-}" in
        -s|--si) si=1 ;;
        '') ;;
        -h|--help) sed -n '2,/^$/p' "$0" | sed 's/^# \{0,1\}//'; return 0 ;;
        *) echo "Opción desconocida: $1 (usa --help)" >&2; return 2 ;;
    esac

    local raiz
    raiz="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
    cd "$raiz"

    local rama="${RAMA:-main}"

    paso() { printf '\n\033[1m== %s\033[0m\n' "$*"; }
    mal() { printf '\033[31m✗ %s\033[0m\n' "$*" >&2; }
    bien() { printf '\033[32m✓ %s\033[0m\n' "$*"; }

    # Pregunta por la terminal aunque la salida vaya a otro sitio; con -s, sí.
    confirmar() {
        [ "$si" -eq 1 ] && return 0
        local respuesta
        read -r -p "$1 [s/N] " respuesta </dev/tty
        [[ "$respuesta" =~ ^[sSyY]$ ]]
    }

    paso "Rama y cambios"

    local actual
    actual="$(git rev-parse --abbrev-ref HEAD)"
    if [ "$actual" != "$rama" ]; then
        # Con llaves: el bash 3.2 de macOS lee la «»» pegada como parte del nombre.
        mal "Estás en «${actual}» y se despliega «${rama}»."
        return 1
    fi

    # Lo no versionado también cuenta (menos lo ignorado): un archivo nuevo sin
    # añadir es casi siempre algo que se olvidó en el commit.
    if [ -n "$(git status --porcelain)" ]; then
        mal "Hay cambios sin commitear:"
        git status --short >&2
        return 1
    fi
    bien "Rama $rama, sin cambios pendientes"

    git fetch --quiet origin "$rama"
    local detras delante
    detras="$(git rev-list --count "HEAD..origin/$rama")"
    delante="$(git rev-list --count "origin/$rama..HEAD")"
    if [ "$detras" -gt 0 ]; then
        mal "origin/$rama tiene $detras commit(s) que no tienes. Haz git pull antes de desplegar."
        return 1
    fi

    paso "Compilando los assets"

    # Con el entorno levantado se compila en el contenedor que ya corre; si no,
    # en uno de usar y tirar. `npm install` primero por si el package-lock
    # cambió desde la última vez que se levantó el entorno.
    local compose=(docker compose -f .docker/dev.compose.yml)
    local compilar='npm install --no-audit --no-fund --loglevel=error && npm run build'
    if [ -n "$("${compose[@]}" ps -q --status running laravel 2>/dev/null)" ]; then
        "${compose[@]}" exec -T laravel bash -c "$compilar"
    else
        "${compose[@]}" run --rm --no-deps -T laravel bash -c "$compilar"
    fi

    if [ -n "$(git status --porcelain)" ]; then
        echo
        echo "La compilación cambió archivos:"
        git status --short
        echo

        # Sólo se commitea solo lo que sale de compilar. Si cambió otra cosa
        # (el package-lock, por ejemplo), lo tiene que mirar una persona.
        if [ -n "$(git status --porcelain -- . ':!public/build')" ]; then
            mal "Cambió algo más que public/build. Revísalo y commitéalo a mano."
            return 1
        fi

        if confirmar "¿Commiteo public/build como «Assets compilados»?"; then
            git add public/build
            git commit --quiet -m "Assets compilados"
            bien "Commit $(git rev-parse --short HEAD)"
            delante=$((delante + 1))
        else
            mal "public/build sin commitear: el servidor serviría los assets viejos."
            return 1
        fi
    else
        bien "public/build ya estaba al día"
    fi

    paso "Remoto"

    if [ "$delante" -gt 0 ]; then
        git --no-pager log --oneline "origin/$rama..HEAD"
        if confirmar "¿Hago push de $delante commit(s) a origin/$rama?"; then
            git push --quiet origin "$rama"
        else
            mal "Sin push, el servidor no ve estos commits."
            return 1
        fi
    fi
    bien "origin/$rama = $(git log -1 --format='%h %s')"

    echo
    bien "Listo para desplegar."
}

main "$@"
exit $?
