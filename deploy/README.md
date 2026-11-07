# Déploiement de LaPhotoGagnante (serveur maison + Cloudflare)

## Fonctionnement

```
Visiteur ──HTTPS──▶ Cloudflare ──tunnel (connexion sortante)──▶ cloudflared ──HTTP──▶ php:8080 (FrankenPHP)
                                                                                     ├─ messenger (emails)
                                                                                     ├─ database (MySQL)
                                                                                     └─ rabbitmq

git push / merge sur main ─▶ CI GitHub (qualité, tests, image) ─▶ si OK : workflow « Déploiement »
                                                                    └─▶ runner GitHub installé sur le serveur
                                                                         └─▶ deploy/deploy.sh
```

- **Aucun port à ouvrir** sur la box ni sur le serveur : `cloudflared` et le runner GitHub se connectent vers l'extérieur.
- `deploy/deploy.sh` : sauvegarde de la base → construction de l'image → migrations → redémarrage → vérification → **retour à la version précédente** si le site ne répond pas.
- Les données (base, photos, avatars, photos en attente) sont dans des volumes Docker : un déploiement ne les touche pas.
- Le déploiement ne part que depuis `main`, et seulement si toute la CI est verte.

## Installation (une seule fois)

### 1. Prérequis sur le serveur

- Linux (Debian / Ubuntu), Docker Engine et le plugin `docker compose` ([installation](https://docs.docker.com/engine/install/)).
- Un domaine géré par Cloudflare.

```bash
sudo mkdir -p /srv/photoprix/backups /srv/photoprix/logs
```

### 2. Tunnel Cloudflare

1. Cloudflare → **Zero Trust** → **Networks** → **Tunnels** → **Create a tunnel** → type **Cloudflared**, nom `photoprix`.
2. Copier le **jeton** affiché (la longue chaîne après `--token` dans la commande proposée). Ne pas lancer cette commande : le conteneur `cloudflared` de `compose.prod.yaml` s'en charge.
3. Onglet **Public Hostname** → ajouter : sous-domaine / domaine de votre choix, **Service** `HTTP` → URL `php:8080`.

> Si un `cloudflared` tourne déjà sur le serveur (installé hors Docker), ne lancez pas celui de la stack :
> publiez le port en local (`ports: ["127.0.0.1:8080:8080"]` sur le service `php`), faites pointer
> votre tunnel existant vers `http://localhost:8080`, et supprimez le service `cloudflared` de `compose.prod.yaml`.

### 3. Fichier de configuration

```bash
sudo cp deploy/.env.prod.example /srv/photoprix/.env.prod   # depuis un clone du dépôt
sudo nano /srv/photoprix/.env.prod                          # remplacer TOUTES les valeurs change-me
```

- Secret : `openssl rand -hex 32`.
- `DEFAULT_URI` et `SYMFONY_TRUSTED_HOSTS` : votre vrai domaine.
- `MAILER_DSN` : un vrai serveur SMTP, sinon aucun email (inscription, mot de passe oublié) ne partira.
- `CLOUDFLARE_TUNNEL_TOKEN` : le jeton de l'étape 2.

Le script refuse de déployer tant qu'il reste une valeur `change-me`.

### 4. Runner GitHub sur le serveur

1. Créer un utilisateur dédié, autorisé à utiliser Docker :
   ```bash
   sudo useradd -m -s /bin/bash github-runner
   sudo usermod -aG docker github-runner
   sudo chown -R github-runner:github-runner /srv/photoprix
   sudo chmod 600 /srv/photoprix/.env.prod
   ```
   ⚠️ Appartenir au groupe `docker` revient à être administrateur de la machine : n'utilisez ce runner **que pour ce dépôt, qui doit rester privé**.
2. GitHub → dépôt → **Settings** → **Actions** → **Runners** → **New self-hosted runner** → Linux. Suivre les commandes affichées **en tant que `github-runner`** (`sudo -iu github-runner`), en ajoutant le label `photoprix` :
   ```bash
   ./config.sh --url https://github.com/AlexisVerquin/photoprix --token <jeton> --name serveur-maison --labels photoprix --unattended
   ```
3. L'installer comme service (redémarre avec le serveur) :
   ```bash
   sudo ./svc.sh install github-runner
   sudo ./svc.sh start
   ```
4. GitHub → **Settings** → **Environments** → **New environment** `production` → *Deployment branches* : `main` uniquement. (Optionnel : *Required reviewers* pour valider chaque mise en production d'un clic.)

### 5. Premier déploiement

Le workflow de déploiement doit être présent sur `main` (fusionner `dev` dans `main`). Ensuite :
GitHub → **Actions** → **Déploiement** → **Run workflow** (branche `main`).

Puis, une fois le site en ligne : s'inscrire sur le site, confirmer son email, et se donner le rôle admin :

```bash
docker exec photoprix-prod-php-1 php bin/console app:user:promote votre@email.fr
```

### 6. Tâches planifiées

`crontab -e` en tant que `github-runner` :

```cron
# Désigne le gagnant du dernier concours terminé (sans effet s'il n'y en a pas)
15 0 * * * docker exec photoprix-prod-php-1 php bin/console app:set-winner >> /srv/photoprix/logs/set-winner.log 2>&1

# Sauvegarde quotidienne de la base (14 jours conservés)
30 3 * * * docker exec photoprix-prod-database-1 sh -c 'exec mysqldump -uroot -p"$MYSQL_ROOT_PASSWORD" --single-transaction "$MYSQL_DATABASE"' | gzip > /srv/photoprix/backups/photoprix-$(date +\%Y\%m\%d-\%H\%M\%S).sql.gz && ls -1t /srv/photoprix/backups/photoprix-*.sql.gz | tail -n +15 | xargs -r rm --
```

Pensez à copier régulièrement `/srv/photoprix/backups` **hors du serveur** (disque externe, autre machine).

## Au quotidien

| Besoin | Commande (sur le serveur, dans le clone du runner ou n'importe quel clone) |
|---|---|
| État des conteneurs | `docker compose -f compose.prod.yaml --env-file /srv/photoprix/.env.prod ps` |
| Logs du site | `docker logs -f --tail 100 photoprix-prod-php-1` |
| Logs des emails | `docker logs -f --tail 100 photoprix-prod-messenger-1` |
| Console Symfony | `docker exec -it photoprix-prod-php-1 php bin/console …` |
| Redéployer à la main | `PHOTOPRIX_ENV_FILE=/srv/photoprix/.env.prod ./deploy/deploy.sh` |
| Revenir à la version précédente | `docker tag photoprix-app:previous photoprix-app:latest && docker compose -f compose.prod.yaml --env-file /srv/photoprix/.env.prod up -d --no-build php messenger` |

### Restaurer une sauvegarde

```bash
gunzip -c /srv/photoprix/backups/photoprix-AAAAMMJJ-HHMMSS.sql.gz \
  | docker exec -i photoprix-prod-database-1 sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

⚠️ Le retour automatique à la version précédente ne défait **pas** les migrations de base de données : en cas de migration ratée, restaurer la sauvegarde faite juste avant (son chemin est affiché dans les logs du déploiement).
