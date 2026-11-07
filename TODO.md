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
  - Existe : sauvegarde mysqldump avant chaque déploiement + cron quotidien proposé (14 jours conservés) dans `deploy/README.md`.
  - À faire : sauvegarder aussi les **photos** (volumes `uploads` et `pending_photos`) ; copie **hors du serveur** (autre disque, cloud chiffré : restic / borg vers B2, S3, OVH Object Storage…) ; tester une restauration complète.

- [ ] **6. Migration du site en cas de changement d'hébergement**
  - Existe : tout est dans Docker (`compose.prod.yaml`), configuration dans un seul fichier hors dépôt (`/srv/photoprix/.env.prod`).
  - À faire : script d'export / import (base + volumes photos + `.env.prod`) et procédure écrite pas à pas (nouveau serveur → restauration → bascule du tunnel Cloudflare).

- [ ] **7. Vérification de l'accessibilité**
  - Existe : début de bonnes pratiques (lien d'évitement, `aria-label`, `visually-hidden`).
  - À faire : audit RGAA / WCAG 2.2 AA (Lighthouse, axe, navigation clavier, lecteur d'écran, contrastes, textes alternatifs des photos, messages d'alerte annoncés) ; déclaration d'accessibilité.

- [ ] **8. Vérification RGPD et CGU**
  - Fait : CGU, politique de confidentialité et mentions légales réécrites (structure CNIL, alignées sur le code) ; purge automatique, vraie suppression de compte, désinscription, polices et icônes locales ; registre `docs/registre-traitements.md`.
  - Reste : compléter `config/packages/legal.yaml` (hébergeur, pays du serveur, adresse publique de contact) ; relecture par un avocat sur les points ouverts (auto-hébergement et anonymat de l'éditeur, applicabilité du DSA) ; vérifier la durée réelle des journaux sur le serveur.

## Points techniques encore ouverts (cf. `AUDIT.md`)

- XSS potentielle dans `assets/controllers/alert_controller.js` (messages insérés en `innerHTML`).
- Performances : `findActualTheme()` appelée plusieurs fois par page, comptages de votes en N+1.
- Messages en dur en français dans les contrôleurs (site traduit fr/en).
