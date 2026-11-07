# Registre des activités de traitement — LaPhotoGagnante

Établi d'après le [modèle de registre simplifié de la CNIL](https://www.cnil.fr/fr/RGDP-le-registre-des-activites-de-traitement)
(onglet « Modèle de fiche de registre »). Il peut être recopié tel quel dans le fichier ODS officiel.
À mettre à jour à chaque changement dans la façon de traiter les données (nouvelle fonctionnalité, nouveau prestataire, nouvelle durée).

- **Création** : 07/11/2026 — **Dernière mise à jour** : 07/11/2026
- **Responsable du traitement** : Alexis Verquin, particulier (adresse et téléphone : *à compléter*), alexis.verquin@gmail.com
- **Délégué à la protection des données** : aucun (non obligatoire)
- **Données sensibles** : aucune, pour tous les traitements ci-dessous

## Prestataires et destinataires communs

| Acteur | Rôle | Pays | Garantie si hors UE |
|---|---|---|---|
| Hébergement : serveur personnel de l'éditeur (*à compléter*) | Hébergement du site et des données | *à compléter* | — |
| Cloudflare, Inc. | Sous-traitant : acheminement et protection du trafic (tunnel) | États-Unis | Data Privacy Framework UE–États-Unis + clauses contractuelles types |
| OVH SAS | Sous-traitant : envoi des emails, nom de domaine | France | — |
| Google Ireland Ltd / Meta Platforms Ireland Ltd | Responsables distincts, seulement si le membre choisit de se connecter avec eux | Irlande (UE) | — |

## Mesures de sécurité communes

Connexion HTTPS, mots de passe hachés, limitation des tentatives de connexion, protection CSRF, accès d'administration réservé à l'éditeur,
photos en attente hors du dossier public, suppression des métadonnées EXIF, sauvegardes quotidiennes (14 jours), aucun port ouvert sur le serveur,
mises à jour de sécurité suivies (Dependabot, `composer audit` en CI).

---

## Fiche 1 — Gestion des comptes membres

- **Finalité principale** : permettre aux membres de créer et gérer leur compte.
- **Sous-finalités** : inscription, connexion (dont Google / Facebook), confirmation de l'email, mot de passe oublié, suppression du compte.
- **Base légale** : exécution du contrat (CGU).
- **Personnes concernées** : membres inscrits.

| Catégorie de données | Description | Durée de conservation |
|---|---|---|
| Identification | Email, nom d'utilisateur, mot de passe haché, ville et avatar (facultatifs), identifiant Google / Facebook | Jusqu'à la suppression du compte |
| Vie personnelle | Préférence d'emails, date du dernier vote au sondage | Jusqu'à la suppression du compte |
| Demandes de mot de passe | Jeton haché de réinitialisation | 1 heure (expiration), supprimé avec le compte |

## Fiche 2 — Organisation des concours photo

- **Finalité principale** : faire fonctionner les concours.
- **Sous-finalités** : envoi et modération des photos, votes, désignation du gagnant, palmarès, sondage du thème suivant.
- **Base légale** : exécution du contrat (CGU).
- **Personnes concernées** : membres ; personnes éventuellement visibles sur les photos.

| Catégorie de données | Description | Durée de conservation |
|---|---|---|
| Images | Photos envoyées (métadonnées EXIF supprimées) | Non gagnantes : jusqu'à la désignation du gagnant (7 jours max après la fin) ; gagnante : 1 an après la fin du concours |
| Vie personnelle | Votes | Supprimés avec la photo concernée ou le compte |
| Identification | Nom du gagnant au palmarès | Tant que le compte du gagnant existe |

## Fiche 3 — Emails de nouveau concours

- **Finalité principale** : prévenir les membres du lancement d'un concours.
- **Base légale** : intérêt légitime (informer les membres de l'activité du service), avec droit d'opposition à tout moment (profil, lien de désinscription, en-tête List-Unsubscribe).
- **Personnes concernées** : membres à l'email vérifié et abonnés.

| Catégorie de données | Description | Durée de conservation |
|---|---|---|
| Identification | Email, nom d'utilisateur | Tant que le membre est inscrit et abonné |

## Fiche 4 — Formulaire de contact

- **Finalité principale** : répondre aux demandes des visiteurs et des membres (questions, signalements, exercice des droits).
- **Base légale** : intérêt légitime (répondre aux demandes).
- **Personnes concernées** : visiteurs et membres qui écrivent.

| Catégorie de données | Description | Durée de conservation |
|---|---|---|
| Identification | Email, identifiant de compte le cas échéant | 24 h après la clôture de la demande (1 an maximum si jamais clôturée) |
| Vie personnelle | Contenu du message et de la réponse | Idem |

## Fiche 5 — Sécurité du site

- **Finalité principale** : protéger le site et les comptes contre les abus.
- **Sous-finalités** : limitation des tentatives de connexion et des messages de contact, journaux d'erreurs, sauvegardes.
- **Base légale** : intérêt légitime (sécurité).
- **Personnes concernées** : visiteurs et membres.

| Catégorie de données | Description | Durée de conservation |
|---|---|---|
| Données de connexion | Adresse IP (compteurs anti-abus) | 15 minutes (connexion) à 1 heure (contact) |
| Données de connexion | Journaux techniques d'erreurs (pas de journal de navigation) | Rotation automatique (10 Mo × 3 par service), 6 mois au maximum |
| Toutes les données de la base | Sauvegardes | 14 jours |
