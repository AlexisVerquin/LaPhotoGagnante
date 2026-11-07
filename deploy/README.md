# Déploiement de LaPhotoGagnante (serveur dédié Scaleway + Cloudflare)

## Fonctionnement

```
Visiteur ──HTTPS──▶ Cloudflare ──tunnel (connexion sortante)──▶ cloudflared ──HTTP──▶ laphotogagnante:8080 (FrankenPHP)
                                                     (partagé, réseau Docker « tunnel »)
                                                                                     ├─ messenger (emails)
                                                                                     ├─ database (MySQL)
                                                                                     └─ rabbitmq

git push / merge sur master ─▶ CI GitHub (qualité, tests, image)
                                        ▲
serveur, toutes les 5 min : deploy/auto-update.sh ── nouveau commit sur master et CI verte ? ─▶ deploy/deploy.sh
```

- **Aucun port à ouvrir** sur le serveur (hors SSH) : `cloudflared` et la mise à jour automatique se connectent vers l'extérieur.
- **Aucun runner GitHub** sur le serveur : le dépôt étant public, une pull request extérieure ne doit jamais pouvoir exécuter du code chez vous. C'est le serveur qui va chercher `master`, que vous seul fusionnez.
- `deploy/deploy.sh` : sauvegarde de la base → construction de l'image → migrations → redémarrage → vérification → **retour à la version précédente** si le site ne répond pas.
- Les données (base, photos, avatars, photos en attente) sont dans des volumes Docker : un déploiement ne les touche pas.
- Le déploiement ne part que depuis `master`, et seulement si toute la CI est verte (vérifié via l'API publique de GitHub, sans jeton). Un commit en échec n'est pas retenté : on attend le suivant.

## Installation (une seule fois)

### 0. Le serveur : Dedibox Scaleway

Production sur une **Dedibox Start-2-M-SATA** (Scaleway SAS, datacenter en région parisienne) : Atom C2750 8 cœurs, 16 Go de RAM, un seul disque dur.
Scaleway est l'hébergeur déclaré dans les mentions légales (`config/packages/legal.yaml`) et dans `docs/registre-traitements.md` :
à mettre à jour si le serveur change.

1. Console Dedibox → **Serveur** → **Installer** : **Debian 13** (ou Ubuntu 24.04 LTS), en ajoutant votre **clé SSH publique**.
2. Premières commandes, en root :
   ```bash
   apt update && apt full-upgrade -y
   apt install -y unattended-upgrades ufw git curl python3
   dpkg-reconfigure -plow unattended-upgrades          # mises à jour de sécurité automatiques
   ```
3. SSH par clé uniquement : dans `/etc/ssh/sshd_config`, `PasswordAuthentication no` et `PermitRootLogin prohibit-password`, puis `systemctl restart ssh`
   (vérifier d'abord qu'une connexion par clé fonctionne, dans un autre terminal).
4. Pare-feu : tout fermé en entrée sauf SSH (le site passe par le tunnel Cloudflare, connexion sortante).
   ```bash
   ufw default deny incoming && ufw default allow outgoing && ufw allow OpenSSH && ufw enable
   ```
   Attention : Docker contourne `ufw` pour les ports **publiés** (`ports:`). `compose.prod.yaml` n'en publie aucun : ne pas en ajouter.
5. Swap de sécurité (16 Go de RAM, mais les builds Docker sont gourmands) :
   ```bash
   fallocate -l 4G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
   echo '/swapfile none swap sw 0 0' >> /etc/fstab
   ```
6. Le processeur est lent (Atom) : le build de l'image à chaque déploiement prend plusieurs minutes. Le site reste en ligne pendant ce temps.

### 1. Prérequis sur le serveur

- Linux (Debian / Ubuntu), Docker Engine et le plugin `docker compose` ([installation](https://docs.docker.com/engine/install/)).
- Le domaine **laphotogagnante.fr** (acheté chez OVH), dont le DNS est confié à Cloudflare : voir l'étape 1 bis.

```bash
sudo mkdir -p /srv/photoprix/backups /srv/photoprix/logs
```

### 1 bis. Domaine OVH → DNS Cloudflare

Le domaine reste **enregistré chez OVH** (renouvellement, facturation) ; seule la gestion du DNS passe chez Cloudflare, ce qu'exige le tunnel.

1. Cloudflare → **Add a domain** → `laphotogagnante.fr` → offre **Free**. Cloudflare importe les enregistrements DNS existants : **vérifiez que les `MX` et `TXT` (SPF) d'OVH sont bien présents** si vous utilisez une adresse email OVH, sinon les emails ne seront plus reçus/envoyés.
2. Cloudflare affiche **deux serveurs DNS** (`xxx.ns.cloudflare.com`). Chez OVH : **Web Cloud** → **Noms de domaine** → `laphotogagnante.fr` → onglet **Serveurs DNS** → **Modifier les serveurs DNS** → remplacer ceux d'OVH par les deux de Cloudflare.
3. Attendre l'email « domain is now active » de Cloudflare (quelques minutes à 24 h).
4. Si **DNSSEC** est activé chez OVH, le désactiver avant l'étape 2 (on pourra le réactiver ensuite depuis Cloudflare).
5. Facultatif : redirection `www` → domaine principal. Cloudflare → **Rules** → **Redirect Rules** → modèle *Redirect from WWW to root*. (L'application n'accepte que `laphotogagnante.fr`, cf. `SYMFONY_TRUSTED_HOSTS`.)

**Emails envoyés par le site** (`MAILER_DSN`) : **Brevo** (gratuit jusqu'à 300 emails/jour, société française). Les adresses `@laphotogagnante.fr` d'OVH ne sont que des **redirections** vers Gmail (réception seulement, pas de SMTP). Dans Brevo → **Domains**, authentifier `laphotogagnante.fr` : recopier dans Cloudflare le code `brevo-code`, les deux `CNAME` DKIM et le `TXT` `_dmarc` donnés. SPF dans Cloudflare : `v=spf1 include:mx.ovh.com include:spf.brevo.com -all`. Identifiant et clé : Brevo → **SMTP & API** → **SMTP**. Vérifier : `dig +short TXT laphotogagnante.fr`, puis envoyer un email de test à [mail-tester.com](https://www.mail-tester.com).

### 2. Tunnel Cloudflare

Le tunnel **n'est pas dans la stack du site** : un seul `cloudflared` sert tout le serveur (le site, et d'autres services éventuels),
pour qu'arrêter ou redéployer le site ne coupe rien d'autre. Il rejoint les services par le réseau Docker `tunnel`.

1. **Si un `cloudflared` partagé tourne déjà** (`docker ps` affiche un conteneur `cloudflared` sur le réseau `tunnel`) : passer au point 3, sur ce tunnel-là.
2. Sinon : Cloudflare → **Zero Trust** → **Networks** → **Tunnels** → **Create a tunnel** → type **Cloudflared**, copier le **jeton**
   (la longue chaîne après `--token`, sans lancer la commande proposée), puis :
   ```bash
   mkdir -p ~/server/cloudflared && cp deploy/cloudflared.compose.yaml ~/server/cloudflared/docker-compose.yml
   cd ~/server/cloudflared && (umask 077; echo 'TUNNEL_TOKEN=le-jeton' > .env) && docker compose up -d
   ```
3. Dans le tunnel → **Public Hostname** (ou **Published application routes**) → ajouter : sous-domaine vide, domaine `laphotogagnante.fr`,
   **Service** `HTTP` → URL `laphotogagnante:8080`. Cloudflare crée lui-même l'enregistrement DNS et le certificat HTTPS.
   (`laphotogagnante` est l'alias du conteneur `php` sur le réseau `tunnel`, cf. `compose.prod.yaml`.)

> Le site répond en « 502 » tant que la stack n'est pas déployée (étape 5) : c'est normal.
> Ne pas lancer en même temps sur ce serveur une autre stack `compose.prod.yaml` (test local avec `COMPOSE_PROJECT_NAME=…`) :
> elle prendrait le même alias sur le réseau `tunnel` et recevrait une partie des visiteurs.

### 3. Fichier de configuration

```bash
sudo cp deploy/.env.prod.example /srv/photoprix/.env.prod   # depuis un clone du dépôt
sudo nano /srv/photoprix/.env.prod                          # remplacer TOUTES les valeurs change-me
```

- Secret : `openssl rand -hex 32`.
- `DEFAULT_URI` et `SYMFONY_TRUSTED_HOSTS` : votre vrai domaine.
- `MAILER_DSN` : un vrai serveur SMTP, sinon aucun email (inscription, mot de passe oublié) ne partira.
- `LEGAL_HOST_PHONE` : téléphone de l'hébergeur (Scaleway SAS), obligatoire dans les mentions légales : le recopier depuis [leurs mentions légales](https://www.scaleway.com/fr/mentions-legales). Nom et adresse de Scaleway sont dans `config/packages/legal.yaml`.
- `LEGAL_EDITOR_ADDRESS` : **laisser vide** pour ne pas publier votre adresse. Éditeur non professionnel, vous êtes identifié auprès de l'hébergeur (LCEN, art. 6-III-2) : la page affiche cette mention à la place.

Le script refuse de déployer tant qu'il reste une valeur `change-me`.

### 3 bis. Connexion Google / Facebook (facultatif)

Chaque bouton n'apparaît que si son identifiant est renseigné dans `.env.prod`.

**Google** — [console.cloud.google.com](https://console.cloud.google.com) :
1. Créer un projet, puis **APIs et services** → **Écran de consentement OAuth** : type *Externe*, nom « LaPhotoGagnante », email d'assistance, logo facultatif, liens vers `https://laphotogagnante.fr/rgpd` et `https://laphotogagnante.fr/cgu`. Champs d'application : `openid`, `email`, `profile` (aucune validation Google nécessaire pour ceux-ci). **Publier** l'application (sinon seuls les comptes de test peuvent se connecter).
2. **Identifiants** → **Créer des identifiants** → **ID client OAuth** → *Application Web* :
   - Origine JavaScript autorisée : `https://laphotogagnante.fr`
   - URI de redirection autorisé : `https://laphotogagnante.fr/connect/google/check`
3. Copier l'ID et le secret dans `OAUTH_GOOGLE_CLIENT_ID` / `OAUTH_GOOGLE_CLIENT_SECRET`.

**Facebook** — [developers.facebook.com](https://developers.facebook.com) :
1. **Créer une app** → cas d'usage *Authentifier et demander des données aux utilisateurs avec Facebook Login*.
2. **Paramètres de l'app** → **Général** : URL de politique de confidentialité `https://laphotogagnante.fr/rgpd`, URL des conditions `https://laphotogagnante.fr/cgu`, **instructions de suppression des données** `https://laphotogagnante.fr/rgpd#suppression-donnees`, domaine de l'app `laphotogagnante.fr`.
3. **Facebook Login** → **Paramètres** : URI de redirection OAuth valide `https://laphotogagnante.fr/connect/facebook/check` (laisser *Strict Mode* activé).
4. **Ajouter la permission `email`** (sinon Facebook affiche « Invalid Scopes: email ») : menu de gauche **Cas d'utilisation** → *Authentifier et demander des données aux utilisateurs avec Facebook Login* → **Personnaliser** → section **Autorisations** → ligne `email` → **Ajouter**. `public_profile` est déjà présente. Accès standard : aucune validation de l'app par Meta n'est nécessaire.
   - Si ce cas d'utilisation n'apparaît pas, l'app a été créée avec un autre type (ex. *Entreprise* / « Facebook Login for Business ») : le plus simple est de recréer une app avec le cas d'utilisation ci-dessus.
5. Passer l'app en mode **Live** (en mode *Développement*, seuls les comptes ajoutés comme testeurs peuvent se connecter).
6. Copier l'ID de l'app et la clé secrète dans `OAUTH_FACEBOOK_CLIENT_ID` / `OAUTH_FACEBOOK_CLIENT_SECRET`.

Puis redémarrer le site pour prendre en compte les nouvelles valeurs : `cd /srv/photoprix/app && PHOTOPRIX_ENV_FILE=/srv/photoprix/.env.prod ./deploy/deploy.sh`.

> **Récapitulatif des URI de redirection à déclarer** (production + dev local, les deux peuvent coexister) :
>
> | Fournisseur | Production | Dev local |
> |---|---|---|
> | Google | `https://laphotogagnante.fr/connect/google/check` | `https://localhost/connect/google/check` |
> | Facebook | `https://laphotogagnante.fr/connect/facebook/check` | `https://localhost/connect/facebook/check` |
>
> En local, les identifiants vont dans `.env.local` ; en production dans `/srv/photoprix/.env.prod`. Les mêmes applications Google / Facebook peuvent servir aux deux.

### 4. Mise à jour automatique

1. Outils nécessaires (présents d'office sur la plupart des serveurs Debian / Ubuntu) : `git`, `curl`, `python3`, `flock`.
2. Un utilisateur dédié, autorisé à utiliser Docker, et le dépôt cloné à côté de la configuration :
   ```bash
   sudo useradd -m -s /bin/bash photoprix
   sudo usermod -aG docker photoprix
   sudo git clone https://github.com/AlexisVerquin/LaPhotoGagnante.git /srv/photoprix/app
   sudo mkdir -p /srv/photoprix/logs
   sudo chown -R photoprix:photoprix /srv/photoprix
   sudo chmod 600 /srv/photoprix/.env.prod
   ```
   Appartenir au groupe `docker` revient à être administrateur de la machine : seul le code de `master` est exécuté par ce compte.
3. `sudo crontab -u photoprix -e` :
   ```cron
   # Toutes les 5 minutes : déploie le dernier commit de master si sa CI est verte (silencieux sinon)
   */5 * * * * /srv/photoprix/app/deploy/auto-update.sh >> /srv/photoprix/logs/deploy.log 2>&1
   ```

### 5. Premier déploiement

Fusionnez `dev` dans `master` et attendez que la CI soit verte : le serveur déploie tout seul dans les 5 minutes.
Pour ne pas attendre, ou suivre le déploiement en direct :

```bash
sudo -iu photoprix /srv/photoprix/app/deploy/auto-update.sh
```

Puis, une fois le site en ligne : s'inscrire sur le site, confirmer son email, et se donner le rôle admin :

```bash
docker exec photoprix-prod-php-1 php bin/console app:user:promote votre@email.fr
```

### 6. Tâches planifiées

`sudo crontab -u photoprix -e`, à la suite de la ligne de mise à jour automatique :

```cron
# Chaque nuit : désigne le gagnant du concours terminé, annonce le nouveau concours par email,
# puis supprime ce qui a dépassé sa durée de conservation (photos, messages clôturés)
15 0 * * * docker exec photoprix-prod-php-1 php bin/console app:daily >> /srv/photoprix/logs/daily.log 2>&1

# Sauvegarde quotidienne de la base et des photos (installée par chaque déploiement)
30 3 * * * /srv/photoprix/bin/backup.sh >> /srv/photoprix/logs/backup.log 2>&1
```

Chaque sauvegarde produit deux fichiers dans `/srv/photoprix/backups` : `photoprix-*.sql.gz` (la base, 14 gardées) et `fichiers-*.tar`
(photos publiées, photos en attente et avatars, 7 gardées car plus lourdes). Ces nombres se règlent avec `PHOTOPRIX_BACKUPS_TO_KEEP`
et `PHOTOPRIX_FILE_BACKUPS_TO_KEEP`. Les photos font au plus 5 Mo : prévoir de la place en conséquence (`du -sh /srv/photoprix/backups`).

Pensez à copier régulièrement `/srv/photoprix/backups` **hors du serveur** (disque externe, autre machine, stockage objet) : la Dedibox n'a qu'un disque, une sauvegarde sur ce disque ne protège pas d'une panne. Exemple depuis le PC : `rsync -av photoprix@serveur:/srv/photoprix/backups/ ~/sauvegardes-photoprix/`.

## Preprod réservée à quelques personnes

Pour tester avec un petit groupe avant d'ouvrir à tous, on suit la même procédure avec un sous-domaine, par exemple `preprod.laphotogagnante.fr` :

1. Tunnel Cloudflare : ajouter le hostname `preprod.laphotogagnante.fr` → `HTTP` `laphotogagnante:8080` (au lieu du domaine principal).
2. `.env.prod` : `DEFAULT_URI=https://preprod.laphotogagnante.fr` et `SYMFONY_TRUSTED_HOSTS=^preprod\.laphotogagnante\.fr$`.
3. **Restreindre l'accès** : Cloudflare Zero Trust → **Access** → **Applications** → *Self-hosted*, domaine `preprod.laphotogagnante.fr`, politique « Allow » avec la liste des emails invités (gratuit jusqu'à 50 personnes). Cloudflare leur envoie un code de connexion à usage unique : personne d'autre, ni aucun moteur de recherche, ne voit le site.
4. Google / Facebook : ajouter aussi les URI `https://preprod.laphotogagnante.fr/connect/…/check`, ou laisser leurs identifiants vides en preprod.

Le jour de l'ouverture, il suffit de remplacer le sous-domaine par `laphotogagnante.fr` aux étapes 1, 2 et 4, et de supprimer l'application Access : les données de la preprod sont conservées.

## Au quotidien

| Besoin | Commande (sur le serveur, en tant que `photoprix`) |
|---|---|
| État des conteneurs | `docker compose -f compose.prod.yaml --env-file /srv/photoprix/.env.prod ps` |
| Logs du site | `docker logs -f --tail 100 photoprix-prod-php-1` |
| Logs des emails | `docker logs -f --tail 100 photoprix-prod-messenger-1` |
| Console Symfony | `docker exec -it photoprix-prod-php-1 php bin/console …` |
| Où en est le déploiement ? | `/srv/photoprix/app/deploy/auto-update.sh --status` et `tail -f /srv/photoprix/logs/deploy.log` |
| Retenter un commit en échec (après correction de la config) | `rm /srv/photoprix/state/failed-sha`, puis attendre 5 min ou lancer `auto-update.sh` |
| Redéployer à la main la version présente | `cd /srv/photoprix/app && PHOTOPRIX_ENV_FILE=/srv/photoprix/.env.prod ./deploy/deploy.sh` |
| Revenir à la version précédente | `docker tag photoprix-app:previous photoprix-app:latest && docker compose -f compose.prod.yaml --env-file /srv/photoprix/.env.prod up -d --no-build php messenger` |

### Restaurer une sauvegarde

La base :

```bash
gunzip -c /srv/photoprix/backups/photoprix-AAAAMMJJ-HHMMSS.sql.gz \
  | docker exec -i photoprix-prod-database-1 sh -c 'exec mysql -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE"'
```

Les photos et avatars (les fichiers présents sont remplacés, les autres restent) :

```bash
docker exec -i photoprix-prod-php-1 tar xf - -C /app < /srv/photoprix/backups/fichiers-AAAAMMJJ-HHMMSS.tar
```

Restaurez la base et les fichiers **de la même date** : sinon des photos peuvent manquer en base, ou l'inverse.

⚠️ Le retour automatique à la version précédente ne défait **pas** les migrations de base de données : en cas de migration ratée, restaurer la sauvegarde faite juste avant (son chemin est affiché dans les logs du déploiement).
