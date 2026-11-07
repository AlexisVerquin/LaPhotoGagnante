# 📷 PhotoPrix

Plateforme communautaire et gratuite de concours photo. Chaque mois, un nouveau thème est lancé : les membres publient leurs photos, votent pour leurs préférées et élisent le thème du mois suivant.

> Projet à but récréatif, développé avec Symfony.

---

## Sommaire

- [Fonctionnalités](#fonctionnalités)
- [Stack technique](#stack-technique)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Commandes utiles](#commandes-utiles)
- [Structure du projet](#structure-du-projet)
- [Emails transactionnels](#emails-transactionnels)
- [Données personnelles (RGPD)](#données-personnelles-rgpd)
- [Déploiement](#déploiement)
- [Auteur](#auteur)

---

## Fonctionnalités

### Côté membre

- Inscription avec confirmation de l'adresse email
- Connexion, réinitialisation du mot de passe
- Profil (nom d'utilisateur, ville facultative, avatar) et suppression du compte
- Participation au concours en cours : envoi de photos
- Galerie du concours et vote pour les photos
- Vote pour le thème du mois suivant (sondage)
- Compte à rebours de fin de concours et d'ouverture des votes
- Notifications par email au lancement d'un thème, désactivables depuis le profil ou depuis l'email

### Côté administration

- Tableau de bord administrateur
- Gestion des thèmes (création, dates de début et de fin, gagnant)
- Gestion des sondages de thèmes
- Validation ou refus des photos avant publication
- Consultation et réponse aux messages du formulaire de contact

### Pages publiques

- Accueil : thème en cours, photo du jour, compte à rebours, derniers gagnants, statistiques
- Conditions générales d'utilisation
- Politique de confidentialité

---

## Stack technique

| Domaine | Technologie |
|---|---|
| Framework | Symfony |
| Langage | PHP |
| Templates | Twig |
| Front | Bootstrap 5, Bootstrap Icons |
| JavaScript | Stimulus (Symfony UX) |
| Icônes | Symfony UX Icons (Lucide) |
| Base de données | Doctrine ORM — [à compléter : MySQL / PostgreSQL / SQLite] |
| Emails | Symfony Mailer, SymfonyCasts VerifyEmailBundle et ResetPasswordBundle |
| Polices | Poppins, Nunito (Google Fonts) |

---

## Prérequis

- PHP [version, à compléter] et extensions usuelles de Symfony (`ctype`, `iconv`, `intl`, `pdo`, `gd` ou `imagick` pour les images…)
- [Composer](https://getcomposer.org/)
- [Symfony CLI](https://symfony.com/download) (recommandé)
- Un serveur de base de données [à compléter]
- Un serveur SMTP ou un outil de test comme [Mailpit](https://mailpit.axllent.org/) en développement

---

## Installation

```bash
# 1. Cloner le dépôt
git clone [URL_DU_DEPOT]
cd photoprix

# 2. Installer les dépendances PHP
composer install

# 3. Configurer l'environnement (voir section suivante)
cp .env .env.local

# 4. Créer la base de données et lancer les migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate

# 5. (Optionnel) Charger des données de test
php bin/console doctrine:fixtures:load

# 6. Lancer le serveur de développement
symfony serve
```

Le site est ensuite accessible sur `https://127.0.0.1:8000`.

---

## Configuration

Les variables sont à définir dans `.env.local` (ce fichier ne doit **jamais** être versionné).

```dotenv
APP_ENV=dev
APP_SECRET=[chaîne aléatoire]

DATABASE_URL="[à compléter selon votre SGBD]"

MAILER_DSN=[ex: smtp://localhost:1025 avec Mailpit]
```

Autres points à vérifier :

- l'adresse expéditeur des emails (confirmation, réinitialisation, réponse au formulaire de contact) ;
- les dossiers d'upload des photos et des avatars, qui doivent être accessibles en écriture par le serveur web ;
- le fuseau horaire, car les dates de début et de fin de concours pilotent les compte à rebours.

### Créer un administrateur

[À compléter : commande dédiée, ou promotion manuelle d'un utilisateur en base avec le rôle `ROLE_ADMIN`.]

---

## Commandes utiles

```bash
# Lister les routes
php bin/console debug:router

# Vider le cache
php bin/console cache:clear

# Créer une migration après modification d'une entité
php bin/console make:migration

# Compiler les assets pour la production (si AssetMapper)
php bin/console asset-map:compile

# Lancer les tests (si présents)
php bin/phpunit
```

---

## Structure du projet

```
├── assets/
│   └── controllers/        # Contrôleurs Stimulus (ex: countdown_controller.js)
├── config/                 # Configuration Symfony
├── migrations/             # Migrations Doctrine
├── public/                 # Point d'entrée web, images, uploads
├── src/
│   ├── Controller/         # Contrôleurs (public, membre, admin)
│   ├── Entity/             # Entités Doctrine (Theme, Photo, Survey, User…)
│   └── Repository/         # Requêtes (ex: findActualTheme)
├── templates/
│   ├── admin/              # Pages d'administration
│   ├── main/               # Accueil, galerie…
│   ├── partials/           # Fragments réutilisables (alertes…)
│   └── base.html.twig
└── translations/
```

---

## Emails transactionnels

Les emails sont en HTML avec mise en page par tableaux et styles inline, pour un rendu fiable sur Gmail, Outlook et Apple Mail :

| Email | Déclencheur |
|---|---|
| Confirmation d'adresse email | Inscription |
| Réinitialisation du mot de passe | Demande « mot de passe oublié » |
| Réponse au formulaire de contact | Réponse depuis l'espace administrateur |
| Lancement d'un nouveau thème | Début d'un concours (désinscription possible) |

---

## Données personnelles (RGPD)

Principes appliqués par le site :

- aucun cookie de suivi ou de mesure d'audience, uniquement le cookie de session technique de Symfony ;
- seules les données nécessaires sont collectées (email, nom d'utilisateur, mot de passe chiffré ; ville et avatar facultatifs) ;
- les photos d'un concours ne sont conservées que le temps du concours, sauf la photo gagnante, conservée au maximum un an ;
- les photos ne sont jamais utilisées en dehors du site ;
- le compte peut être supprimé par l'utilisateur depuis son profil, et les demandes d'accès ou de modification passent par le formulaire de contact.

Voir les pages **Politique de confidentialité** et **CGU** du site pour le détail.

---

## Déploiement

Production sur un serveur maison derrière **Cloudflare Tunnel**, déployée automatiquement par GitHub Actions après chaque CI verte sur `main`.
Installation du serveur, configuration et exploitation : voir **[deploy/README.md](deploy/README.md)**.

---

## Auteur

Projet développé par **Alexis Verquin**.

## Licence

[À compléter : MIT, propriétaire, etc.]
