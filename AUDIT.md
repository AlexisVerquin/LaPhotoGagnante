# Audit du code PhotoPrix — 7 novembre 2026

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

## 2. À traiter — décisions ou chantiers ⚠️

1. **Aucun test.** `tests/` est vide. Priorité : tests fonctionnels sur le vote, l'upload, la désignation du gagnant (règles métier les plus sensibles).
2. **Métadonnées EXIF des photos** (dont GPS) conservées et servies publiquement. Contradictoire avec la politique RGPD affichée. Recommandé : ré-encoder l'image avec GD/Imagick à l'upload (supprime les EXIF, normalise le format).
3. **Photos en attente servies publiquement** : `public/uploads/themes/<dir>/` est accessible à qui connaît l'URL. Pour une vraie modération, stocker hors de `public/` et servir via un contrôleur.
4. **Pas de limitation de tentatives de connexion** : ajouter `login_throttling` (nécessite `symfony/rate-limiter`). Idem pour le formulaire de contact (spam) et la demande de réinitialisation.
5. **`alert_controller.js` injecte les messages en `innerHTML`** : aujourd'hui les messages sont contrôlés par le serveur, mais tout futur flash contenant une saisie utilisateur deviendra une XSS. Préférer `textContent` et construire le lien « connectez-vous » en DOM.
6. **Règle métier à confirmer** : un membre peut voter pour **sa propre photo**. Faut-il l'interdire ?
7. **Règle ajoutée à confirmer** : l'upload exige désormais un **email vérifié** (comme le vote du sondage). À retirer si ce n'est pas voulu.
8. **Uniformiser `Survey.dateStart/End`** (DATETIME) et `Theme.dateStart/End` (DATE) : deux logiques de dates différentes, source de bugs comme le n°12.
9. **Performances** : `findActualTheme()` est exécutée 5 à 6 fois par page (base, navbar, compte à rebours ×2, contrôleur…) ; `findAll()` des thèmes sur l'accueil ; `count($photo->getVotes())` charge tous les votes (N+1). Mettre en cache le thème courant par requête et utiliser des `COUNT` SQL.
10. **Messages en dur** en français dans les contrôleurs alors que l'application est traduite (fr/en) : passer par `TranslatorInterface`.
11. **Lecture d'un message admin (`/contact/read/{id}`) en GET** qui modifie l'état : passer en POST + CSRF (risque faible, admin uniquement).
12. **Fichiers suivis par git inutiles** : `rapport.txt` (sortie PHPStan), `.idea/`, `caddy-local-root.crt` et `.caddy-local-root.crt` en double. À supprimer du dépôt si vous êtes d'accord.
13. **`.env` non suivi** mais `.env.example` l'est : à l'inverse de la convention Symfony (`.env` versionné sans secrets, `.env.local` pour les secrets). Pas grave, mais à documenter.
14. `docker-compose` : transport Messenger différent entre `php` (`/%2f`) et `messenger` (`/%2f/messages`), identifiants RabbitMQ en dur alors que des variables existent. Services exposés sur l'hôte (3306, 6379, 5672) : OK en dev, à fermer en prod.
15. Entité `Theme` : propriété `$ThemeChoice` avec une majuscule, `isAlreadyActived` jamais lu ; `User::$isActive` jamais lu. Nettoyage à prévoir.

---

## 3. Qualité 💡

- Types nullable incohérents avec le mapping Doctrine (`?string` pour une colonne `NOT NULL`) : sans gravité, mais à aligner pour que PHPStan soit propre.
- Logique « votes ouverts 6 jours avant la fin » dupliquée (`MainController`, `ThemeExtension`) : à mettre dans une méthode de `Theme` ou un service.
- `UploadExtension::UPLOADS_DIR` et les chemins `%kernel.project_dir%/public/uploads/...` dupliqués : centraliser dans des paramètres.
- Repositories pleins de commentaires générés par le maker : à supprimer.
- `declare(strict_types=1)` présent dans seulement quelques fichiers.

---

## À faire après cette passe

```bash
php bin/console doctrine:migrations:migrate
```

Puis tester à la main : inscription (email reçu ?), envoi de photo, validation admin, vote, changement de mot de passe, suppression de compte, `php bin/console app:set-winner`.
