# TODO — LaPhotoGagnante

Liste établie le 2026-11-07. Pour chaque point : ce qui existe déjà, pour repartir vite.

- [ ] **1. Désigner le gagnant automatiquement à la fin du concours**
  - Existe : commande `app:set-winner` (dernier thème terminé, photos validées, égalité → photo la plus ancienne, sans effet si déjà un gagnant) + ligne cron proposée dans `deploy/README.md` § 6.
  - À faire : choisir cron serveur **ou** Symfony Scheduler (worker dans la stack Docker, rien à configurer sur l'hôte) ; annoncer le gagnant aux membres (email / page d'accueil).

- [ ] **2. Newsletter et/ou réseaux sociaux** (nouveau concours, résultats…)
  - Existe : Messenger + RabbitMQ pour l'envoi asynchrone d'emails ; le README évoque une option « notifications par email » désactivable (à vérifier : pas de champ d'abonnement dans `User` aujourd'hui).
  - À faire : abonnement / désabonnement (lien dans chaque email, obligation RGPD), envoi par lots ; publication auto (Symfony Notifier a des connecteurs Facebook Page, Telegram, Discord, Bluesky, Mastodon…).

- [ ] **3. Configuration de Cloudflare**
  - Existe : procédure DNS OVH → Cloudflare et tunnel dans `deploy/README.md` § 1 bis et § 2.
  - À faire : le faire réellement ; redirection `www` ; SSL/HSTS ; règles de cache pour `/uploads` et `/assets` ; WAF / protection anti-bots ; éventuellement Cloudflare Access sur `/admin`.

- [ ] **4. Estimation de la charge serveur** (prévoir l'achat d'un VPS si besoin)
  - Existe : stack Docker (FrankenPHP, MySQL, RabbitMQ, messenger, cloudflared).
  - À faire : mesurer la consommation au repos (`docker stats`), test de charge (k6 / ab) sur accueil, galerie, vote, upload ; estimer le stockage (photos ≤ 5 Mo × membres × mois) ; tenir compte du débit montant de la box.

- [ ] **5. Mise en place des sauvegardes**
  - Existe : sauvegarde mysqldump avant chaque déploiement + cron quotidien proposé (14 jours conservés) dans `deploy/README.md`.
  - À faire : sauvegarder aussi les **photos** (volumes `uploads` et `pending_photos`) ; copie **hors du serveur** (autre disque, cloud chiffré : restic / borg vers B2, S3, OVH Object Storage…) ; tester une restauration complète.

- [ ] **6. Migration du site en cas de changement d'hébergement**
  - Existe : tout est dans Docker (`compose.prod.yaml`), configuration dans un seul fichier hors dépôt (`/srv/photoprix/.env.prod`).
  - À faire : script d'export / import (base + volumes photos + `.env.prod`) et procédure écrite pas à pas (nouveau serveur → restauration → bascule du tunnel Cloudflare).

- [ ] **7. Vérification de l'accessibilité**
  - Existe : début de bonnes pratiques (lien d'évitement, `aria-label`, `visually-hidden`).
  - À faire : audit RGAA / WCAG 2.2 AA (Lighthouse, axe, navigation clavier, lecteur d'écran, contrastes, textes alternatifs des photos, messages d'alerte annoncés) ; déclaration d'accessibilité.

- [ ] **8. Vérification RGPD et CGU**
  - Existe : pages `/rgpd` et `/cgu` (complétées pour Google / Facebook, suppression des données), EXIF supprimés, anonymisation à la suppression du compte.
  - À faire : relire en entier (mentions légales / hébergeur, responsable de traitement, durées de conservation réellement appliquées : photos non gagnantes supprimées après le concours ?, gagnante 1 an ?) ; automatiser ces purges ; registre des traitements ; vérifier l'absence de cookies non essentiels (Bootstrap Icons et Google Fonts chargés depuis des CDN externes → transfert d'IP, à héberger localement).

## Points techniques encore ouverts (cf. `AUDIT.md`)

- XSS potentielle dans `assets/controllers/alert_controller.js` (messages insérés en `innerHTML`).
- Performances : `findActualTheme()` appelée plusieurs fois par page, comptages de votes en N+1.
- Messages en dur en français dans les contrôleurs (site traduit fr/en).
