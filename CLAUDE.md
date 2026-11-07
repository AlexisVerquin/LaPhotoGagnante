# CLAUDE.md — PhotoPrix

Plateforme communautaire de concours photo mensuel (projet perso d'Alexis Verquin, en développement). Chaque mois : un thème, les membres envoient **une photo**, l'admin la valide, les membres votent, un gagnant est désigné ; un sondage choisit le thème suivant.

Langue de travail : **français** (code commenté en français, UI en fr/en via `translations/`).
Préférence de l'utilisateur : **demander en cas de doute** avant de modifier ; sinon avancer sans demander.

## Stack

- PHP ≥ 8.4 (8.5 en local), **Symfony 8.1**, Doctrine ORM 3, Twig, AssetMapper (pas de Node/webpack)
- Front : Bootstrap 5, Stimulus (`assets/controllers/*_controller.js`), Turbo, UX Icons (Lucide), UX Live Component
- MySQL 8.4, RabbitMQ (Messenger), Redis, MailHog — via `docker-compose.yml` (FrankenPHP + Caddy)
- Bundles : SymfonyCasts VerifyEmail & ResetPassword, KnpPaginator, FOSJsRouting
- Syntaxe PHP 8.4 utilisée partout : `new Foo()->bar()` sans parenthèses autour du `new`

## Commandes

Le PHP de la machine hôte n'a ni GD, ni pdo_mysql, ni amqp : **lancer les commandes dans le conteneur** (`docker compose exec php …`, et `-u appuser` pour composer).

```bash
docker compose up -d                       # stack complète (https://localhost, MailHog sur :8025)
docker compose exec php php bin/phpunit    # tests (recrée la base app_test via les migrations)
docker compose exec -e SKIP_DB_RESET=1 php php bin/phpunit tests/Service   # tests sans base, plus rapide
docker compose exec -u appuser php composer audit
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load     # admin : alexis.verquin@gmail.com / Password123!
php bin/console app:set-winner             # désigne le gagnant du dernier thème terminé (cron)
php bin/console app:photos:strip-exif [--dry-run]   # retire les EXIF des images déjà dans public/uploads
vendor/bin/phpstan analyse                 # niveau 6 (phpstan.neon local, phpstan.dist.neon versionné)
composer cs:check / composer cs:fix        # php-cs-fixer
vendor/bin/rector process --dry-run
php bin/console doctrine:schema:validate --skip-sync   # valide le mapping sans BDD
php bin/console lint:twig templates && php bin/console lint:container
```

### Tests

- PHPUnit 13, `tests/FunctionalTestCase.php` : base vidée avant chaque test (ORMPurger), compteur de connexion remis à zéro, fabriques `createUser/createTheme/createPhoto/createVote`, `reload()`.
- Base `app_test` (compte root via `${MYSQL_ROOT_PASSWORD}` dans `.env.test`), Messenger `in-memory://` en test.
- `tests/TestImage.php` fabrique des JPEG avec EXIF (Make, Orientation, GPS) pour tester le nettoyage.
- Les jetons CSRF des endpoints AJAX se récupèrent dans la page HTML (`data-vote-csrf-value`, `data-token`…).
- Ne pas mettre `cache.app` en `cache.adapter.array` en test : les services sont réinitialisés entre requêtes, le rate limiter ne compterait plus.
- Un test vérifie que les migrations correspondent au mapping : toute modif d'entité sans migration le fait échouer.

## Domaine (src/Entity)

- `Theme` : un concours. `dateStart`/`dateEnd` en **DATE** (pas datetime). `directoryName` = sous-dossier de `public/uploads/themes/`. `winner` (User, ManyToOne), `ThemeChoice` (SurveyChoice, OneToOne).
- `Photo` : `is_visible` = validée par l'admin. Une seule par (user, theme). Fichier dans `public/uploads/themes/<directoryName>/<name>`.
- `Vote` : un vote par (user, theme), modifiable (on change de photo). Ouvert **6 jours avant `dateEnd`**. Voter pour sa propre photo est **autorisé** (décision produit).
- `Survey` / `SurveyChoice` : sondage du thème suivant (dates en **DATETIME**). 1 vote/jour/membre (`User::lastVoteDate`), compteur `numberVoter`.
- `Contact` : messages du formulaire de contact (`type` = `contact` | `reply`, `origin` = id du message d'origine).
- `User` : identifiant = email. Suppression de compte = **anonymisation** (pas de DELETE), cf. `UserManageController::removeUser`.
- `DateTrait` : `created_at` / `updated_at` via callbacks → l'entité doit avoir `#[ORM\HasLifecycleCallbacks]`.

Requêtes clés : `ThemeRepository::findActualTheme()` (thème en cours), `findLastFinishedTheme()`, `SurveyRepository::findActiveSurvey()`.

## Organisation

- `src/Controller/` : public (`MainController` = accueil, galerie, votes ; `PhotoController` = upload), membre (`UserManageController`, sous `/user`), `Admin/` (sous `/admin`, `ROLE_ADMIN`).
- `src/Twig/` : fonctions Twig globales (`getCurrentTheme`, `getCurrentThemeTime`, `getPhotoCurrentTheme`, `getAvatar`…) appelées dans `base.html.twig` / navbar → **doivent tolérer l'absence de thème et d'utilisateur**.
- Endpoints AJAX (vote, vote sondage, upload photo/avatar) : POST, jeton CSRF dans l'en-tête `X-CSRF-Token`, réponse JSON. Les contrôleurs Stimulus attendent du JSON (`success`/`message` ou `error`).
- Uploads (photo, avatar) : passent par `ImageSanitizer` (ré-encodage GD → plus aucune métadonnée EXIF/GPS, orientation appliquée). Participer (upload, vote) exige un **email vérifié** (décision produit).
- Connexion : `login_throttling` = 3 échecs / 15 min par (email + IP) (`config/packages/security.yaml`, `symfony/rate-limiter`).
- Emails : expéditeur global `MAILER_FROM` (config/packages/mailer.yaml), email admin = paramètre `app.admin_email` (`ADMIN_MAIL`). Ne pas remettre de `->from()` en dur.

## Conventions / pièges

- Toute action qui modifie l'état : **POST/DELETE + CSRF** (y compris côté admin).
- Toujours vérifier `$this->getUser() instanceof User` (et `isVerified()` pour participer/voter) plutôt qu'un `@var`.
- Toujours gérer le cas « aucun concours en cours » (`findActualTheme()` peut renvoyer `null`).
- Comparer les colonnes `DATE` avec un paramètre typé `Types::DATE_MUTABLE`.
- Ne pas utiliser l'option de formulaire `data` pour des valeurs par défaut (elle écrase l'entité en édition) : les poser dans le contrôleur.
- Après modification d'entité : créer une migration (`make:migration` nécessite la BDD Docker ; sinon l'écrire à la main dans `migrations/`).
- git : travail sur `dev`, PR vers `main`. Le dépôt est en `core.autocrlf=true` (avertissements LF/CRLF normaux).

## Suivi

`AUDIT.md` : audit du 2026-11-07, liste des corrections faites et des points encore ouverts (perfs, photos en attente servies publiquement, XSS potentielle dans alert_controller…).
