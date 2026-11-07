# Audit du code LaPhotoGagnante — 7 novembre 2026

Branche auditée : `dev` (commit `21f2f26`). Périmètre : `src/`, `templates/`, `assets/controllers/`, `config/`, Docker.

Légende : ✅ corrigé dans cette passe · ⚠️ à traiter (décision ou chantier plus large) · 💡 amélioration

---

## 1. Bugs bloquants / sécurité — corrigés ✅

| # | Problème | Impact | Correction |
|---|---|---|---|
| 1 | **Mapping Doctrine invalide** : `Vote::$user` en ManyToOne mais `User::$votes` en ManyToMany ; `User::addVote()` appelait `Vote::addUser()` qui n'existe pas | `doctrine:schema:validate` en échec, crash si on utilise ces méthodes | `User::$votes` → OneToMany, méthodes corrigées |
| 2 | **`Theme::$winner` en OneToOne avec `cascade: remove`** | Un membre ne pouvait gagner **qu'une seule fois** (index UNIQUE) et **supprimer un thème supprimait l'utilisateur gagnant** | ManyToOne sans cascade + migration `Version20261107120000` |
| 3 | **Vote photo sans CSRF** : le jeton était envoyé par le JS mais jamais vérifié ; route accessible en GET | Vote forgé depuis un site tiers | CSRF vérifié, POST uniquement |
| 4 | Vote possible pour une photo **non validée** ou d'un **ancien concours** ; crash si aucun concours | Triche, erreur 500 | Vérifie visibilité + thème en cours + email vérifié |
| 5 | **Upload de photo sans authentification**, sans vérifier le concours en cours ni le doublon | Erreur 500 (user null) après écriture du fichier sur disque ; plusieurs photos par membre | Connexion + email vérifié requis, 1 photo / concours, POST uniquement |
| 6 | L'upload renvoyait une **redirection** alors que le JS attend du **JSON** | Message « Une erreur est survenue » affiché même en cas de succès | Réponse JSON |
| 7 | **Galerie : affichait les photos non validées** | La modération admin était contournée | Filtre `isVisible` |
| 8 | **Changement de mot de passe cassé** : comparait le hash avec lui-même, ignorait les champs du formulaire | Impossible de changer son mot de passe | Vérifie l'actuel, la confirmation, les règles de robustesse ; erreurs affichées |
| 9 | Formulaire profil invalide → l'utilisateur connecté restait modifié en mémoire | Session corrompue / déconnexion surprise | `refresh()` de l'entité si le formulaire est invalide |
| 10 | **Validation photo admin en GET sans CSRF** | Une simple `<img src=…>` piégée validait une photo à l'insu de l'admin | POST + CSRF (bouton dans un formulaire) |
| 11 | Adresses expéditeur `'no-reply@${PROJECT_NAME}.fr'` : entre apostrophes PHP, **jamais interpolé** | Adresse invalide → envoi d'emails en échec (inscription, réponse contact, commande gagnant) | Expéditeur global `MAILER_FROM` dans `config/packages/mailer.yaml` |
| 12 | **Dernier jour du concours perdu** : colonne `DATE` comparée à un `DATETIME` | Le jour de clôture, le concours n'était plus « en cours » (et la galerie plantait) | Paramètre typé `Types::DATE_MUTABLE` |
| 13 | Page d'accueil en **500** si le dernier thème terminé n'a pas de gagnant | Site inaccessible | Null-check dans `PhotoService` |
| 14 | `findActiveSurvey()` sans `setMaxResults(1)` | 500 sur l'accueil si deux sondages se chevauchent | Corrigé |
| 15 | Galerie / extensions Twig plantaient s'il n'y a **aucun concours en cours** | 500 | Redirection + extensions null-safe |
| 16 | Vote sondage : pas de vérification que le choix appartient au **sondage ouvert** ; incrément non atomique | Votes sur des sondages fermés, votes perdus en concurrence | Vérification + `UPDATE … SET n = n + 1` |
| 17 | Formulaire de contact : champ caché `userId` modifiable par le client | Usurpation de l'identité d'un membre | Rempli côté serveur |
| 18 | `ThemeType` : option `data` sur les dates | **Éditer un thème réécrasait ses dates** avec le mois courant ; création impossible (`directoryName` / `isAlreadyActived` non renseignés → erreur SQL) ; gagnant obligatoire | Défauts posés dans le contrôleur, gagnant facultatif |
| 19 | `app:set-winner` : prenait le thème **en cours** au lieu du thème terminé, comptait les photos non validées, crash si aucun thème | Mauvais gagnant | Réécrite : dernier thème terminé, photos validées, idempotente |
| 20 | Suppression de compte : l'utilisateur restait connecté, le mot de passe restait valide | RGPD / sécurité | Mot de passe aléatoire, déconnexion, bio/ville vidées |
| 21 | Locale arbitraire stockée en session via `/locale/{xxx}` | 500 sur la session (locale invalide) | `requirements: en|fr` + validation dans `LocaleSubscriber` |
| 22 | Admin : réponse au contact sans Post/Redirect/Get, message d'exception SQL affiché en flash, suppression de message sur id inexistant → 500 | Double envoi, fuite d'infos | Corrigé |
| 23 | Avatar : l'ancien fichier n'était jamais supprimé | Fichiers orphelins (RGPD) | Supprimé lors du remplacement |
| 24 | Aucune contrainte de validation sur `User` (email, nom, ville) et `Contact` | Erreurs SQL (longueur), emails invalides | `Assert\Email`, `NotBlank`, `Length` |

Divers : `docker-compose.yml` (`serverVersion=8:4:0` → `8.4.0`), fichiers `*:Zone.Identifier` retirés du dépôt et ignorés, imports d'une classe inexistante (`App\Constant\AlertConst`) supprimés, code mort sur la locale dans `MainController::index`.

**Vérifications passées** : `doctrine:schema:validate` (mapping OK, il échouait avant), `lint:container`, `lint:twig`, `lint:yaml`, PHPStan niveau 6 (56 erreurs → 4 avertissements de typage mineurs + les « type mapping mismatch » cosmétiques).
**Non vérifié** : rien n'a été testé dans un navigateur ni contre la base (conteneurs arrêtés). La migration doit être lancée.

---

## 2. Deuxième passe (même jour) ✅

- **Tests** : 46 tests PHPUnit (vote, vote sondage, upload, galerie, admin, profil, suppression de compte, connexion, commande gagnant, dates des concours, nettoyage EXIF, cohérence migrations/mapping). Vérifiés : 10 d'entre eux échouent sur le code d'avant l'audit.
- **EXIF** : `App\Service\ImageSanitizer` ré-encode chaque photo et avatar envoyés (GD) : plus aucune métadonnée (GPS, appareil, date), orientation appliquée avant. Commande `app:photos:strip-exif` pour les fichiers existants. Extension `exif` ajoutée au Dockerfile.
- **Connexion** : blocage après **3 échecs** pour 15 min (`login_throttling`, `symfony/rate-limiter`).
- **Dépendances** : 22 alertes de sécurité Symfony (dont 2 graves) → mise à jour de correctifs 8.1.x, `composer audit` propre.
- **Décisions** : voter pour sa propre photo reste autorisé ; l'email vérifié est requis pour participer.
- Dépôt nettoyé : `rapport.txt`, `.idea/`, certificat Caddy en double.

## 3. Troisième passe ✅

- **Spam du formulaire de contact** : pot de miel (champ invisible ; un robot qui le remplit reçoit un faux succès, rien n'est enregistré) + 3 messages par heure et par IP.
- **Photos en attente** : rangées dans `storage/pending/` (hors du dossier web), visibles seulement par leur auteur et les admins via `/photo/{id}/image` ; déplacées dans `public/` à la validation. Commande `app:photos:secure-pending` pour les photos envoyées avant. Les tests ont révélé et fait corriger un bug (dossier de destination non créé).
- **CI GitHub Actions** : qualité (audit, style, PHPStan à 0 erreur, lints) + tests PHP 8.4/8.5 sur MySQL ; Dependabot.
- 59 tests au total.

## 4. Quatrième passe : déploiement continu ✅

- Image Docker multi-cible (`dev` / `prod`) : en prod, code et assets embarqués, opcache, FrankenPHP non-root, en-têtes de sécurité, PHP interdit dans `/uploads`.
- **Bug corrigé** : PHP limitait les envois à 2 Mo alors que le site accepte 5 Mo (`docker/php/app.ini`).
- Stack `compose.prod.yaml` derrière Cloudflare Tunnel (aucun port ouvert), configuration hors dépôt, volumes persistants.
- Proxies de confiance configurés : sans cela, les limites de connexion et de contact auraient bloqué tous les visiteurs à la fois.
- Déploiement automatique après CI verte sur `main` via un runner GitHub sur le serveur ; sauvegarde avant migration, vérification et retour arrière automatique. Testé en local : premier déploiement, mise à jour, sauvegarde, retour arrière, limite de connexion par visiteur.
- Commande `app:user:promote` pour créer le premier administrateur. Guide : `deploy/README.md`.

## 4 bis. Connexion Google / Facebook ✅

- Inscription et connexion via Google et Facebook (OAuth2, paramètre `state` anti-CSRF) ; liaison automatique avec un compte existant de même email ; refus si aucun email transmis.
- Données demandées réduites au minimum (email, nom) ; politique de confidentialité complétée (données reçues, instructions de suppression exigées par Facebook).
- 15 nouveaux tests (76 au total), dont le parcours complet avec un faux fournisseur.

## 5. À traiter — décisions ou chantiers ⚠️

1. ~~Aucun test~~ ✅
2. ~~Métadonnées EXIF~~ ✅
3. ~~Photos en attente servies publiquement~~ ✅
4. ~~Limitation des tentatives de connexion et spam du contact~~ ✅
5. **`alert_controller.js` injecte les messages en `innerHTML`** : aujourd'hui les messages sont contrôlés par le serveur, mais tout futur flash contenant une saisie utilisateur deviendra une XSS. Préférer `textContent` et construire le lien « connectez-vous » en DOM.
6. ~~Vote pour sa propre photo~~ : autorisé (décision).
7. ~~Email vérifié pour l'upload~~ : conservé (décision).
8. **Uniformiser `Survey.dateStart/End`** (DATETIME) et `Theme.dateStart/End` (DATE) : deux logiques de dates différentes, source de bugs comme le n°12.
9. **Performances** : `findActualTheme()` est exécutée plusieurs fois par page (base, navbar, compte à rebours ×2, contrôleur…) ; `photo.votes|length` dans la galerie charge tous les votes (N+1). Mettre en cache le thème courant par requête et utiliser des `COUNT` SQL. (`findAll()` des thèmes sur l'accueil : ✅ supprimé.)
10. **Messages en dur** en français dans les contrôleurs alors que l'application est traduite (fr/en) : passer par `TranslatorInterface`.
11. ~~Lecture d'un message admin en GET~~ ✅ remplacée par « Clôturer » (POST + CSRF).
12. ~~Fichiers inutiles suivis par git~~ ✅
13. **`.env` non suivi** mais `.env.example` l'est : à l'inverse de la convention Symfony (`.env` versionné sans secrets, `.env.local` pour les secrets). Pas grave, mais à documenter.
14. `docker-compose.yml` (dev) : transport Messenger différent entre `php` (`/%2f`) et `messenger` (`/%2f/messages`), identifiants RabbitMQ en dur. (Prod : ✅ `compose.prod.yaml` séparé, aucun port ouvert.)
15. Entité `Theme` : propriété `$ThemeChoice` avec une majuscule, `isAlreadyActived` jamais lu. (`User::$isActive` : ✅ sert maintenant à la suspension.)
16. ~~Conteneur `messenger` marqué *unhealthy*~~ ✅ (il héritait du healthcheck HTTP de FrankenPHP ; désactivé pour ce service).

---

## 6. Qualité 💡

- Types nullable incohérents avec le mapping Doctrine (`?string` pour une colonne `NOT NULL`) : sans gravité, mais à aligner pour que PHPStan soit propre.
- Logique « votes ouverts 6 jours avant la fin » dupliquée (`MainController`, `ThemeExtension`) : à mettre dans une méthode de `Theme` ou un service.
- `UploadExtension::UPLOADS_DIR` et les chemins `%kernel.project_dir%/public/uploads/...` dupliqués : centraliser dans des paramètres.
- Repositories pleins de commentaires générés par le maker : à supprimer.
- `declare(strict_types=1)` présent dans seulement quelques fichiers.

---

## Mise en production

Suivre `deploy/README.md` (installation unique du serveur), puis fusionner `dev` dans `main` : le déploiement est automatique.
Tester à la main après la première mise en ligne : inscription (email reçu ?), envoi de photo, validation admin, vote, changement de mot de passe, suppression de compte, formulaire de contact.
