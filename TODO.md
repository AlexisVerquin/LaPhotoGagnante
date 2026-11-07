# TODO — LaPhotoGagnante

Liste établie le 2026-11-07. Pour chaque point : ce qui existe déjà, pour repartir vite.

- [x] **1. Désigner le gagnant automatiquement à la fin du concours**
  - Fait : `app:daily` (gagnant → annonce du nouveau concours → purge), une seule ligne de cron dans `deploy/README.md` § 6.
  - Reste : installer le cron sur le serveur ; annoncer le gagnant aux membres (email / accueil).

- [ ] **2. Newsletter et/ou réseaux sociaux** (nouveau concours, résultats…)
  - Fait : email « nouveau concours » aux membres abonnés, désinscription en un clic (profil, lien, bouton Gmail/Outlook).
  - Reste : email de résultats ; publication sur les réseaux sociaux (Symfony Notifier) — **accord explicite de l'auteur obligatoire** pour diffuser une photo hors du site (CGU § 6).

- [ ] **3. Configuration de Cloudflare**
  - Existe : procédure DNS OVH → Cloudflare et tunnel dans `deploy/README.md` § 1 bis et § 2.
  - À faire : le faire réellement ; redirection `www` ; SSL/HSTS ; règles de cache pour `/uploads` et `/assets` ; WAF / protection anti-bots ; éventuellement Cloudflare Access sur `/admin`.

- [ ] **4. Estimation de la charge serveur** (prévoir l'achat d'un VPS si besoin)
  - Existe : stack Docker (FrankenPHP, MySQL, RabbitMQ, messenger, cloudflared).
  - À faire : mesurer la consommation au repos (`docker stats`), test de charge (k6 / ab) sur accueil, galerie, vote, upload ; estimer le stockage (photos ≤ 5 Mo × membres × mois) ; tenir compte du débit montant de la box.

- [ ] **5. Mise en place des sauvegardes**
  - Fait : `deploy/backup.sh` sauvegarde la base **et les photos** (avant chaque déploiement + cron quotidien), restauration documentée et testée.
  - Reste : copie hors du serveur (restic / borg vers un disque externe ou un stockage distant chiffré) ; tester une restauration sur le vrai serveur.

- [ ] **6. Migration du site en cas de changement d'hébergement**
  - Existe : tout est dans Docker (`compose.prod.yaml`), configuration dans un seul fichier hors dépôt (`/srv/photoprix/.env.prod`).
  - À faire : script d'export / import (base + volumes photos + `.env.prod`) et procédure écrite pas à pas (nouveau serveur → restauration → bascule du tunnel Cloudflare).

- [ ] **7. Vérification de l'accessibilité**
  - Existe : début de bonnes pratiques (lien d'évitement, `aria-label`, `visually-hidden`).
  - À faire : audit RGAA / WCAG 2.2 AA (Lighthouse, axe, navigation clavier, lecteur d'écran, contrastes, textes alternatifs des photos, messages d'alerte annoncés) ; déclaration d'accessibilité.

- [ ] **8. Vérification RGPD et CGU**
  - Fait : CGU, politique de confidentialité et mentions légales réécrites (structure CNIL, alignées sur le code) ; purge automatique, vraie suppression de compte, désinscription, polices et icônes locales ; registre `docs/registre-traitements.md`.
  - Fait aussi (promesses des textes) : modération (refuser / retirer une photo, suspendre / supprimer un membre), export JSON des données dans le profil, âge minimum à l'inscription, email aux membres en cas de changement des textes (`app:legal:notify-update`).
  - Reste : compléter `config/packages/legal.yaml` (hébergeur, pays du serveur, adresse publique de contact) ; relecture par un avocat sur les points ouverts (auto-hébergement et anonymat de l'éditeur, applicabilité du DSA) ; vérifier la durée réelle des journaux sur le serveur.

## Informations à fournir (pages légales)

À renseigner dans `config/packages/legal.yaml` : tant qu'une valeur manque, les pages affichent « À compléter ».

- [x] **Hébergeur** et **pays du serveur** : renseignés ; adresse et téléphone via `LEGAL_HOST_ADDRESS` / `LEGAL_HOST_PHONE` dans `.env.local` et `/srv/photoprix/.env.prod` (jamais dans git).
- [ ] **Email public de contact** : aujourd'hui l'adresse Gmail personnelle ; créer `contact@laphotogagnante.fr` chez OVH ?
- [ ] **Prestataire d'envoi des emails** : OVH indiqué, à confirmer (et `MAILER_DSN` de prod).
- [ ] **Âge minimum** : 15 ans retenu (majorité numérique), à confirmer.
- [ ] **Date de mise à jour** (`updated_at`) à changer à chaque modification des textes, puis prévenir les membres (`app:legal:notify-update`).

## Mise en production

- [ ] Pousser `dev` puis fusionner dans `master` (le déploiement automatique ne démarre qu'après).
- [ ] **Preprod** pour quelques personnes : `preprod.laphotogagnante.fr` + Cloudflare Access (`deploy/README.md`, section « Preprod »).
- [ ] Installer le serveur en suivant `deploy/README.md` : DNS OVH → Cloudflare, tunnel, `.env.prod`, runner GitHub, environnement `production`.
- [ ] Cron `app:daily` + `/srv/photoprix/bin/backup.sh` (`deploy/README.md` § 6).
- [ ] Copier les sauvegardes **hors du serveur** (disque externe, autre machine) : elles sont aujourd'hui sur le même disque que le site.
- [ ] Journaux : la politique annonce 6 mois maximum, mais Docker ne limite que leur taille ; vérifier sur le serveur, ou ajouter une suppression par date.
- [ ] Google et Facebook : déclarer les URI de redirection de production, publier l'app Google, passer l'app Facebook en mode Live (examen Meta éventuel).
- [ ] Créer la boîte `no-reply@laphotogagnante.fr` (envoi des emails), et SPF / DKIM / DMARC dans Cloudflare.

## Relecture juridique

- [ ] Relecture ciblée par un avocat (≈ 300-600 € HT, cf. échange du 2026-11-07) sur :
  - auto-hébergement et anonymat de l'éditeur (mentions légales) ;
  - applicabilité du Digital Services Act à un petit site gratuit (signalement, motivation de la modération) ;
  - diffusion des photos gagnantes sur les réseaux sociaux (accord explicite de l'auteur) ;
  - règlement de jeu-concours si des lots sont un jour attribués.

## Points techniques encore ouverts (cf. `AUDIT.md`)

- Messages en dur en français dans les contrôleurs (site traduit fr/en).
