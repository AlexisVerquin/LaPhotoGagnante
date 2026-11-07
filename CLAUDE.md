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

```bash
docker compose up -d                       # stack complète (https://localhost, MailHog sur :8025)
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load     # admin : alexis.verquin@gmail.com / Password123!
php bin/console app:set-winner             # désigne le gagnant du dernier thème terminé (cron)
vendor/bin/phpstan analyse                 # niveau 6 (phpstan.neon local, phpstan.dist.neon versionné)
composer cs:check / composer cs:fix        # php-cs-fixer
vendor/bin/rector process --dry-run
php bin/console doctrine:schema:validate --skip-sync   # valide le mapping sans BDD
php bin/console lint:twig templates && php bin/console lint:container
```

Pas de tests pour l'instant (`tests/` vide).

## Domaine (src/Entity)

- `Theme` : un concours. `dateStart`/`dateEnd` en **DATE** (pas datetime). `directoryName` = sous-dossier de `public/uploads/themes/`. `winner` (User, ManyToOne), `ThemeChoice` (SurveyChoice, OneToOne).
- `Photo` : `is_visible` = validée par l'admin. Une seule par (user, theme). Fichier dans `public/uploads/themes/<directoryName>/<name>`.
- `Vote` : un vote par (user, theme), modifiable (on change de photo). Ouvert **6 jours avant `dateEnd`**.
- `Survey` / `SurveyChoice` : sondage du thème suivant (dates en **DATETIME**). 1 vote/jour/membre (`User::lastVoteDate`), compteur `numberVoter`.
- `Contact` : messages du formulaire de contact (`type` = `contact` | `reply`, `origin` = id du message d'origine).
- `User` : identifiant = email. Suppression de compte = **anonymisation** (pas de DELETE), cf. `UserManageController::removeUser`.
- `DateTrait` : `created_at` / `updated_at` via callbacks → l'entité doit avoir `#[ORM\HasLifecycleCallbacks]`.

Requêtes clés : `ThemeRepository::findActualTheme()` (thème en cours), `findLastFinishedTheme()`, `SurveyRepository::findActiveSurvey()`.

## Organisation

- `src/Controller/` : public (`MainController` = accueil, galerie, votes ; `PhotoController` = upload), membre (`UserManageController`, sous `/user`), `Admin/` (sous `/admin`, `ROLE_ADMIN`).
- `src/Twig/` : fonctions Twig globales (`getCurrentTheme`, `getCurrentThemeTime`, `getPhotoCurrentTheme`, `getAvatar`…) appelées dans `base.html.twig` / navbar → **doivent tolérer l'absence de thème et d'utilisateur**.
- Endpoints AJAX (vote, vote sondage, upload photo/avatar) : POST, jeton CSRF dans l'en-tête `X-CSRF-Token`, réponse JSON. Les contrôleurs Stimulus attendent du JSON (`success`/`message` ou `error`).
- Emails : expéditeur global `MAILER_FROM` (config/packages/mailer.yaml), email admin = paramètre `app.admin_email` (`ADMIN_EMAIL`). Ne pas remettre de `->from()` en dur.

## Conventions / pièges

- Toute action qui modifie l'état : **POST/DELETE + CSRF** (y compris côté admin).
- Toujours vérifier `$this->getUser() instanceof User` (et `isVerified()` pour participer/voter) plutôt qu'un `@var`.
- Toujours gérer le cas « aucun concours en cours » (`findActualTheme()` peut renvoyer `null`).
- Comparer les colonnes `DATE` avec un paramètre typé `Types::DATE_MUTABLE`.
- Ne pas utiliser l'option de formulaire `data` pour des valeurs par défaut (elle écrase l'entité en édition) : les poser dans le contrôleur.
- Après modification d'entité : créer une migration (`make:migration` nécessite la BDD Docker ; sinon l'écrire à la main dans `migrations/`).
- git : travail sur `dev`, PR vers `main`. Le dépôt est en `core.autocrlf=true` (avertissements LF/CRLF normaux).

## Suivi

`AUDIT.md` : audit du 2026-11-07, liste des corrections faites et des points encore ouverts (tests, EXIF/RGPD, rate limiting, perfs…).
