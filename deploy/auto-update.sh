#!/usr/bin/env bash
#
# Mise à jour automatique, lancée toutes les 5 minutes par le cron du serveur (aucun runner GitHub).
# Déploie le dernier commit de la branche suivie, seulement si sa CI GitHub est terminée et verte.
# Un commit en échec n'est pas retenté : on attend le suivant.
#
#   deploy/auto-update.sh            vérifie et déploie si besoin (silencieux s'il n'y a rien à faire)
#   deploy/auto-update.sh --status   affiche le commit en ligne et le dernier de la branche, sans rien faire
#
# Tout le script est dans une fonction : bash le lit en entier avant de l'exécuter,
# donc le « git checkout » qui le remplace en cours de route ne le perturbe pas.

main() {
    set -Eeuo pipefail

    local repo="${LAPHOTOGAGNANTE_GITHUB_REPO:-AlexisVerquin/LaPhotoGagnante}"
    local branch="${LAPHOTOGAGNANTE_BRANCH:-master}"
    local state_dir="${LAPHOTOGAGNANTE_STATE_DIR:-/srv/laphotogagnante/state}"
    local app_dir
    app_dir="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

    log() { printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"; }

    mkdir -p "$state_dir"
    # Une seule mise à jour à la fois : un déploiement peut durer plus de 5 minutes
    exec 9>"$state_dir/auto-update.lock"
    flock -n 9 || exit 0

    cd "$app_dir"
    git fetch --quiet origin "$branch"
    local target deployed failed
    target="$(git rev-parse "origin/$branch")"
    deployed="$(cat "$state_dir/deployed-sha" 2>/dev/null || true)"
    failed="$(cat "$state_dir/failed-sha" 2>/dev/null || true)"

    if [[ "${1:-}" == "--status" ]]; then
        echo "En ligne : ${deployed:-aucun}   Dernier commit de $branch : $target   Dernier échec : ${failed:-aucun}"
        return 0
    fi

    [[ "$target" == "$deployed" || "$target" == "$failed" ]] && return 0

    # État de la CI de ce commit (API publique : le dépôt est public, aucun jeton nécessaire)
    local ci
    ci="$(curl -fsS "https://api.github.com/repos/$repo/actions/runs?head_sha=$target&event=push&per_page=20" \
        | python3 -c '
import json, sys
runs = [r for r in json.load(sys.stdin)["workflow_runs"] if r["name"] == "CI" and r["head_branch"] == sys.argv[1]]
print(runs[0]["status"] + "/" + str(runs[0]["conclusion"]) if runs else "absente")
' "$branch")"

    case "$ci" in
        completed/success) ;;
        absente | queued/* | in_progress/* | waiting/* | pending/* | requested/*)
            return 0 ;; # pas encore de CI, ou CI en cours : on repassera dans 5 minutes
        *)
            log "Commit ${target:0:7} non déployé : CI « $ci »."
            echo "$target" > "$state_dir/failed-sha"
            return 0 ;;
    esac

    log "Déploiement de ${target:0:7} (CI verte), version en ligne : ${deployed:0:7}"
    git checkout --quiet --force "$target"

    if ./deploy/deploy.sh; then
        echo "$target" > "$state_dir/deployed-sha"
        rm -f "$state_dir/failed-sha"
        log "Déploiement de ${target:0:7} terminé."
    else
        # deploy.sh a déjà remis l'ancienne version en ligne ; on ne réessaie pas ce commit
        echo "$target" > "$state_dir/failed-sha"
        log "ÉCHEC du déploiement de ${target:0:7} : l'ancienne version reste en ligne (détails ci-dessus)."
        return 1
    fi
}

main "$@"
exit $?
