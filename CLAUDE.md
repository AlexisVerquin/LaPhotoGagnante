# CLAUDE.md — LaPhotoGagnante

Nom affiché : **LaPhotoGagnante** (anciennement PhotoPrix). Le nom **technique** reste `photoprix` (dépôt, conteneurs, image Docker, `/srv/photoprix`, variables `PHOTOPRIX_*`) : décision du 2026-11-07, à renommer plus tard si besoin.
Domaine de production : **laphotogagnante.fr** (enregistré chez OVH, DNS chez Cloudflare pour le tunnel). Adresses email : `@laphotogagnante.fr` (`MAILER_FROM`, `ADMIN_MAIL`).

Plateforme communautaire de concours photo mensuel (projet perso d'Alexis Verquin, en développement). Chaque mois : un thème, les membres envoient **une photo**, l'admin la valide, les membres votent, un gagnant est désigné ; un sondage choisit le thème suivant.

Langue de travail : **français** (code commenté en français, UI en fr/en via `translations/`).
Préférence de l'utilisateur : **demander en cas de doute** avant de modifier ; sinon avancer sans demander.

## Stack

- PHP ≥ 8.4 (8.5 en local), **Symfony 8.1**, Doctrine ORM 3, Twig, AssetMapper (pas de Node/webpack)
- Front : Bootstrap 5, Stimulus (`assets/controllers/*_controller.js`), Turbo, UX Icons (Lucide), UX Live Component
- MySQL 8.4, RabbitMQ (Messenger), MailHog — dev via `docker-compose.yml` (FrankenPHP + Caddy, cible `dev` du Dockerfile, `docker/caddy/Caddyfile.dev`). Redis est lancé en dev mais **pas utilisé** par l'application (absent de la prod).
- PHP : `docker/php/app.ini` (upload 6 Mo, commun) et `docker/php/prod.ini` (opcache sans revalidation).
- Bundles : SymfonyCasts VerifyEmail & ResetPassword, KnpPaginator, FOSJsRouting
- Syntaxe PHP 8.4 utilisée partout : `new Foo()->bar()` sans parenthèses autour du `new`

## Commandes

Le PHP de la machine hôte n'a ni GD, ni pdo_mysql, ni amqp : **lancer les commandes dans le conteneur avec `-u appuser`** (`docker compose exec -u appuser php …`). Sans `-u appuser`, les fichiers créés (caches, `storage/`, fichiers reformatés) appartiennent à root.

```bash
docker compose up -d                       # stack complète (https://localhost, MailHog sur :8025)
docker compose exec php php bin/phpunit    # tests (recrée la base app_test via les migrations)
docker compose exec -e SKIP_DB_RESET=1 php php bin/phpunit tests/Service   # tests sans base, plus rapide
docker compose exec -u appuser php composer audit
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load     # admin : alexis.verquin@gmail.com / Password123!
php bin/console app:daily                  # cron quotidien : app:set-winner → app:notify-new-theme → app:data:purge (dans cet ordre)
php bin/console app:set-winner             # désigne le gagnant du dernier thème terminé
php bin/console app:notify-new-theme       # email « nouveau concours » aux abonnés, une seule fois par concours (Theme.notifiedAt)
php bin/console app:data:purge [--dry-run] # applique les durées de conservation (DataRetention)
php bin/console app:legal:notify-update "résumé" [--dry-run]  # email à tous les membres après une modif des CGU / de la politique
php bin/console app:photos:strip-exif [--dry-run]   # retire les EXIF des images déjà dans public/uploads
php bin/console app:photos:secure-pending [--dry-run]  # déplace hors de public/ les photos non validées (1 fois au déploiement)
vendor/bin/phpstan analyse                 # niveau 6 (phpstan.neon local, phpstan.dist.neon versionné)
composer cs:check / composer cs:fix        # php-cs-fixer
vendor/bin/rector process --dry-run
php bin/console doctrine:schema:validate --skip-sync   # valide le mapping sans BDD
php bin/console lint:twig templates && php bin/console lint:container
```

### CI

`.github/workflows/ci.yml` (push sur `main`/`dev` et PR) : job qualité + job tests (PHP 8.4 et 8.5, service MySQL 8.4) + job image de prod. Dependabot (`.github/dependabot.yml`) ouvre des PR vers `dev`.
- **La liste des vérifications est dans `bin/ci-checks`** (`quality` | `tests` | `all`), appelé tel quel par le workflow : ajouter une vérification là, jamais directement dans le YAML.
- **Rejouer la CI en local avant de pousser** : `bin/ci` (rapide, arbre de travail, conteneur de dev) ; `bin/ci --clean` (comme GitHub : clone LF propre du dernier commit dans `var/ci/clone`, MySQL jetable, ~1 min) ; options `--php 8.4|all`, `--quality`, `--tests`, `--docker`.
Job `docker` : construit l'image de prod (cible `prod` du Dockerfile) à chaque push.

### CD (production)

- Serveur **maison derrière Cloudflare Tunnel**, aucun port ouvert. Guide d'installation : `deploy/README.md`.
- `.github/workflows/deploy.yml` : après une CI verte sur `main` (push), job sur le **runner auto-hébergé** (labels `self-hosted, photoprix`, environnement GitHub `production`) → `deploy/deploy.sh`.
- `deploy/deploy.sh` : sauvegarde mysqldump → `docker compose -f compose.prod.yaml build` → migrations → `up -d` → `app:photos:secure-pending` → vérification HTTP → rollback sur l'image `photoprix-app:previous` si échec (les migrations ne sont pas annulées).
- `compose.prod.yaml` (projet `photoprix-prod`) : php (FrankenPHP, cible `prod`, port 8080 non-root), messenger, MySQL, RabbitMQ, cloudflared. Config hors dépôt : `/srv/photoprix/.env.prod` (modèle `deploy/.env.prod.example`). Données en volumes nommés (`uploads`, `pending_photos`, `database_data`).
- Derrière le proxy : `SYMFONY_TRUSTED_PROXIES=private_ranges` + `SYMFONY_TRUSTED_HEADERS=x-forwarded-for,x-forwarded-proto` **obligatoires**, sinon tous les visiteurs partagent l'IP du tunnel et les rate limiters bloquent tout le monde. `SYMFONY_TRUSTED_HOSTS` = domaine.
- Tester la stack prod en local : `COMPOSE_PROJECT_NAME=photoprix-prodtest PHOTOPRIX_ENV_FILE=<fichier de test> ./deploy/deploy.sh` (nom de projet différent pour ne pas toucher la stack de dev), puis `down -v`.
- Premier admin : `app:user:promote email` (`--revoke` pour retirer). Cron serveur : `app:daily` + sauvegarde quotidienne (cf. guide).

### Tests

- PHPUnit 13, `tests/FunctionalTestCase.php` : base vidée avant chaque test (ORMPurger), compteur de connexion remis à zéro, fabriques `createUser/createTheme/createPhoto/createVote`, `reload()`.
- Base `app_test` (compte root via `${MYSQL_ROOT_PASSWORD}` dans `.env.test`), Messenger `in-memory://` en test.
- `tests/TestImage.php` fabrique des JPEG avec EXIF (Make, Orientation, GPS) pour tester le nettoyage.
- Les jetons CSRF des endpoints AJAX se récupèrent dans la page HTML (`data-vote-csrf-value`, `data-token`…).
- Ne pas mettre `cache.app` en `cache.adapter.array` en test : les services sont réinitialisés entre requêtes, le rate limiter ne compterait plus.
- Un test vérifie que les migrations correspondent au mapping : toute modif d'entité sans migration le fait échouer.

## Domaine (src/Entity)

- `Theme` : un concours. `dateStart`/`dateEnd` en **DATE** (pas datetime). `directoryName` = sous-dossier de `public/uploads/themes/`. `winner` (User, ManyToOne), `ThemeChoice` (SurveyChoice, OneToOne).
- `Photo` : `is_visible` = validée par l'admin. Une seule par (user, theme). Fichier **en attente** dans `storage/pending/<directoryName>/<name>` (hors web), **validée** dans `public/uploads/themes/<directoryName>/<name>`. Tout passe par `App\Service\PhotoStorage` (`storePending`, `publish` à la validation admin, `locate`). Route `app_photo_image` (`/photo/{id}/image`) : sert l'image, en attente = auteur ou admin uniquement (404 sinon).
- `Vote` : un vote par (user, theme), modifiable (on change de photo). Ouvert **6 jours avant `dateEnd`**. Voter pour sa propre photo est **autorisé** (décision produit).
- `Survey` / `SurveyChoice` : sondage du thème suivant (dates en **DATETIME**). 1 vote/jour/membre (`User::lastVoteDate`), compteur `numberVoter`.
- `Contact` : messages du formulaire de contact (`type` = `contact` | `reply`, `origin` = id du message d'origine).
- Avatar par défaut : `public/images/default-avatar.jpg` (versionné ; `public/uploads` ne l'est pas).
- `User` : identifiant = email. `notifyNewTheme` = abonné aux emails de nouveau concours (désinscription : profil, ou lien signé `/desinscription/{id}` + en-têtes `List-Unsubscribe`, cf. `ThemeNotifier`). Suppression de compte = **vraie suppression** via `App\Service\DataEraser::eraseAccount` (photos + fichiers, votes, messages, demandes de mot de passe, avatar ; `Theme.winner` mis à NULL).
- **`User.isActive = false` veut dire « suspendu »** par la modération (page Administration › Membres) : `App\Security\UserChecker` refuse la connexion (mot de passe et OAuth), `SuspendedUserSubscriber` déconnecte un membre déjà connecté. Ne pas confondre avec les anciens comptes anonymisés (`@deleteduser.fr`), que la purge supprime.
- Modération des photos : `app_admin_photo_delete` (POST + CSRF) = refuser une photo en attente (tableau de bord) ou retirer une photo publiée (bouton admin dans la galerie), via `DataEraser::erasePhoto`.
- Droits RGPD en libre-service dans le profil : export JSON (`/user/export`, `PersonalDataExporter` — à compléter si un nouveau champ personnel apparaît), désinscription, suppression.
- Inscription : case « J'ai au moins N ans » obligatoire (`app.min_age` dans `config/services.yaml`, repris par les pages légales).
- `Contact.closedAt` : demande clôturée par l'admin (bouton POST + CSRF), supprimée 24 h après par la purge.
- **Durées de conservation** (`App\Service\DataRetention`, constantes) : photos non gagnantes supprimées dès que le gagnant est désigné (7 jours max après la fin), photo gagnante 1 an, messages 24 h après clôture (1 an max). **Toute modif d'une durée doit être reportée dans `templates/main/rgpd.html.twig` et `docs/registre-traitements.md`.**
- `DateTrait` : `created_at` / `updated_at` via callbacks → l'entité doit avoir `#[ORM\HasLifecycleCallbacks]`.

Requêtes clés : `ThemeRepository::findActualTheme()` (thème en cours), `findLastFinishedTheme()`, `SurveyRepository::findActiveSurvey()`.

## Organisation

- `src/Controller/` : public (`MainController` = accueil, galerie, votes ; `PhotoController` = upload), membre (`UserManageController`, sous `/user`), `Admin/` (sous `/admin`, `ROLE_ADMIN`).
- `src/Twig/` : fonctions Twig globales (`getCurrentTheme`, `getCurrentThemeTime`, `getPhotoCurrentTheme`, `getAvatar`…) appelées dans `base.html.twig` / navbar → **doivent tolérer l'absence de thème et d'utilisateur**.
- Endpoints AJAX (vote, vote sondage, upload photo/avatar) : POST, jeton CSRF dans l'en-tête `X-CSRF-Token`, réponse JSON. Les contrôleurs Stimulus attendent du JSON (`success`/`message` ou `error`).
- Uploads (photo, avatar) : passent par `ImageSanitizer` (ré-encodage GD → plus aucune métadonnée EXIF/GPS, orientation appliquée). Participer (upload, vote) exige un **email vérifié** (décision produit).
- Formulaire de contact : pot de miel (champ caché `website`, robot → faux succès, rien enregistré) + rate limiter `contact_form` (3 messages/heure/IP, `config/packages/framework.yaml`).
- **Connexion / inscription Google et Facebook** (`knpuniversity/oauth2-client-bundle`, `config/packages/knpu_oauth2_client.yaml`) : `/connect/{provider}` → fournisseur → `/connect/{provider}/check` intercepté par `App\Security\OAuth\SocialAuthenticator` ; `SocialUserProvisioner` retrouve le compte (par `googleId`/`facebookId`, sinon par email → liaison automatique, sinon création : email vérifié, mot de passe aléatoire, nom unique). Pas d'email transmis → refus. Bouton masqué si `OAUTH_<PROVIDER>_CLIENT_ID` vide (fonction Twig `oauth_enabled`). Suppression de compte → délie les deux. Tests : `FakeOAuthClient` remplace `knpu.oauth2.client.<provider>` dans le conteneur.
- Connexion : `login_throttling` = 3 échecs / 15 min par (email + IP) (`config/packages/security.yaml`, `symfony/rate-limiter`).
- Emails : expéditeur global `MAILER_FROM` (config/packages/mailer.yaml), email admin = paramètre `app.admin_email` (`ADMIN_MAIL`). Ne pas remettre de `->from()` en dur.

## Conventions / pièges

- **Performances** : `ThemeRepository::findActualTheme()` est mémorisé le temps d'une requête (réinitialisé entre requêtes et par `App\Doctrine\ThemeCacheInvalidator` quand un concours change). Galerie : `PhotoRepository::findVisibleForTheme` (auteurs joints) + `VoteRepository::countByPhotoForTheme`. `tests/Controller/QueryCountTest.php` échoue si le N+1 revient.
- **Alertes** : `alert_controller.js` n'utilise jamais `innerHTML` ; un lien s'envoie à part (`{message, link: {url, label}}`, URL interne uniquement), jamais dans le texte du message.
- **Aucune ressource externe** dans les pages (RGPD : l'IP des visiteurs ne doit partir chez aucun tiers) : polices et icônes sont dans `assets/fonts/`, `importmap_polyfill: false`, pas d'image de démo distante. Seule exception : le rechargement à chaud FrankenPHP, en dev uniquement.
- **Pages légales** (`/cgu`, `/rgpd`, `/mentions-legales`, gabarit `templates/legal/layout.html.twig`) : les infos (éditeur, hébergeur, date de mise à jour) sont dans le paramètre `app.legal` de `config/packages/legal.yaml` (global Twig `legal`) ; une valeur vide s'affiche « À compléter ». Changer `updated_at` à chaque modification. Le registre CNIL est dans `docs/registre-traitements.md`.
- Twig globales : `site_name`, `site_url` (`config/packages/twig.yaml`), à utiliser dans les emails plutôt que du texte en dur.

- Toute action qui modifie l'état : **POST/DELETE + CSRF** (y compris côté admin).
- Toujours vérifier `$this->getUser() instanceof User` (et `isVerified()` pour participer/voter) plutôt qu'un `@var`.
- Toujours gérer le cas « aucun concours en cours » (`findActualTheme()` peut renvoyer `null`).
- Comparer les colonnes `DATE` avec un paramètre typé `Types::DATE_MUTABLE`.
- Ne pas utiliser l'option de formulaire `data` pour des valeurs par défaut (elle écrase l'entité en édition) : les poser dans le contrôleur.
- Migrations écrites à la main : leur horodatage doit être **le vrai moment** (pas une heure arrondie dans le futur), sinon une migration générée ensuite peut s'exécuter avant elle (incident du 2026-11-07).
- Le serveur web de dev (FrankenPHP) tourne en root et écrit dans `var/cache` : si `cache:clear` échoue en « Permission denied », `docker compose exec php chown -R 1000:1000 /app/var`.
- Après modification d'entité : créer une migration (`make:migration` nécessite la BDD Docker ; sinon l'écrire à la main dans `migrations/`).
- php-cs-fixer : **une seule config, `.php-cs-fixer.dist.php`** (versionnée, utilisée par la CI : `@Symfony` + `@PHP84Migration` + `concat_space` à un espace). Un `.php-cs-fixer.php` local serait prioritaire et masquerait les écarts avec la CI (incident de novembre 2026 : 21 fichiers rejetés par la CI alors que le local disait OK). Vérifier avec `--config=.php-cs-fixer.dist.php` en cas de doute.
- git : travail sur `dev`, PR vers `main`. **Messages de commit courts et en anglais** (une ligne `feat:`/`fix:`/`docs:`…, quelques puces au plus). Le dépôt est en `core.autocrlf=true` (avertissements LF/CRLF normaux).

## Suivi

**`TODO.md` : liste des prochains chantiers (à consulter en début de session, cocher au fur et à mesure).**

`AUDIT.md` : audit du 2026-11-07, corrections faites et points encore ouverts (traduction des messages, formats de dates, petit nettoyage).
