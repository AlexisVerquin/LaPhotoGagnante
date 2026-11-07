# Déploiement de LaPhotoGagnante (serveur maison + Cloudflare)

## Fonctionnement

```
Visiteur ──HTTPS──▶ Cloudflare ──tunnel (connexion sortante)──▶ cloudflared ──HTTP──▶ php:8080 (FrankenPHP)
                                                                                     ├─ messenger (emails)
                                                                                     ├─ database (MySQL)
                                                                                     └─ rabbitmq

git push / merge sur master ─▶ CI GitHub (qualité, tests, image)
                                        ▲
serveur, toutes les 5 min : deploy/auto-update.sh ── nouveau commit sur master et CI verte ? ─▶ deploy/deploy.sh
```

- **Aucun port à ouvrir** sur la box ni sur le serveur : `cloudflared` et la mise à jour automatique se connectent vers l'extérieur.
- **Aucun runner GitHub** sur le serveur : le dépôt étant public, une pull request extérieure ne doit jamais pouvoir exécuter du code chez vous. C'est le serveur qui va chercher `master`, que vous seul fusionnez.
- `deploy/deploy.sh` : sauvegarde de la base → construction de l'image → migrations → redémarrage → vérification → **retour à la version précédente** si le site ne répond pas.
- Les données (base, photos, avatars, photos en attente) sont dans des volumes Docker : un déploiement ne les touche pas.
- Le déploiement ne part que depuis `master`, et seulement si toute la CI est verte (vérifié via l'API publique de GitHub, sans jeton). Un commit en échec n'est pas retenté : on attend le suivant.

## Installation (une seule fois)

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

**Emails envoyés par le site** (`MAILER_DSN`) : le plus simple est une adresse `no-reply@laphotogagnante.fr` chez OVH (offre email du domaine). Pour ne pas finir en spam, dans le DNS Cloudflare : garder l'enregistrement **SPF** d'OVH (`v=spf1 include:mx.ovh.com ~all`), activer **DKIM** dans l'espace client OVH (puis recopier les enregistrements `CNAME`/`TXT` qu'il donne dans Cloudflare) et ajouter un enregistrement **DMARC** : `TXT` `_dmarc` → `v=DMARC1; p=none; rua=mailto:admin@laphotogagnante.fr`.

### 2. Tunnel Cloudflare

1. Cloudflare → **Zero Trust** → **Networks** → **Tunnels** → **Create a tunnel** → type **Cloudflared**, nom `photoprix`.
2. Copier le **jeton** affiché (la longue chaîne après `--token` dans la commande proposée). Ne pas lancer cette commande : le conteneur `cloudflared` de `compose.prod.yaml` s'en charge.
3. Onglet **Public Hostname** → ajouter : sous-domaine vide, domaine `laphotogagnante.fr`, **Service** `HTTP` → URL `php:8080`. Cloudflare crée lui-même l'enregistrement DNS et le certificat HTTPS.

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
- `LEGAL_HOST_ADDRESS`, `LEGAL_HOST_PHONE` : vos coordonnées d'hébergeur, obligatoires dans les mentions légales. Elles ne sont que dans ce fichier, jamais dans git.

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

Pensez à copier régulièrement `/srv/photoprix/backups` **hors du serveur** (disque externe, autre machine) : une sauvegarde sur le même disque ne protège pas d'une panne de ce disque.

## Preprod réservée à quelques personnes

Pour tester avec un petit groupe avant d'ouvrir à tous, on suit la même procédure avec un sous-domaine, par exemple `preprod.laphotogagnante.fr` :

1. Tunnel Cloudflare : ajouter le hostname `preprod.laphotogagnante.fr` → `php:8080` (au lieu du domaine principal).
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
