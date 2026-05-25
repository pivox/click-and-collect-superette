# Front marchand — prochains chantiers

Date : 2026-05-25

## Contexte

Le front marchand dispose désormais des bases suivantes :

- **PR #134** — fondations front marchand : login, contexte marchand, shell, dashboard, liste des commandes actives ;
- **PR #135** — détail commande et actions jusqu'à `ready` : accepter, refuser, accepter partiellement, préparer les lignes, marquer la commande prête ;
- **PR #136** — retrait sécurisé marchand : scan par token QR, passage en `pickup_pending`, confirmation marchand, force completion avec note ;
- **PR #138** — historique commandes marchand : onglet historique, filtres "À retirer" / "Clôturées", pagination, détail existant.

Ce document liste les prochains chantiers front marchand, dans un ordre compatible avec le MVP Kadhia. Les chantiers déjà couverts sont conservés en bas de document pour garder la trace des décisions. Le périmètre reste strict : pas de paiement en ligne, pas de livraison, pas de programme de fidélité, pas de panier marketplace multi-marchands.

## Priorité recommandée

### P1 — Notifications marchand

Objectif : informer le marchand des nouvelles commandes, annulations client et retraits finalisés sans push ni temps réel dans le MVP.

À livrer :

- page ou panneau `/merchant/notifications` ;
- badge de notifications non lues dans `MerchantShell` si l'API retourne l'information nécessaire ;
- lecture paginée `GET /api/merchant/notifications?page=1&unread=true` ;
- actions `PATCH /api/merchant/notifications/{id}/read` et `PATCH /api/merchant/notifications/read-all` ;
- polling simple ou bouton "Actualiser", sans WebSocket ni Mercure.

Tests couverts :

- services notifications ;
- liste vide / erreur / liste paginée ;
- marquage lu unitaire et global.

### P1 — Gestion catalogue marchand

Objectif : permettre au marchand de gérer les produits proposés par sa supérette.

À livrer :

- entrée active "Catalogue" dans `MerchantShell` ;
- page `/merchant/catalogue` ;
- recherche dans le référentiel produit ;
- ajout d'un produit référentiel au catalogue de la supérette ;
- modification prix, disponibilité et visibilité ;
- action de rupture en masse si elle reste utile pour le MVP terrain.

Points d'attention :

- conserver la distinction `ProductReference` / `MerchantProduct` ;
- afficher les prix en TND ;
- ne pas permettre au marchand de modifier directement un produit référentiel approuvé ;
- gérer les états "déjà dans mon catalogue" et "produit indisponible".

Tests attendus :

- services catalogue marchand ;
- ajout depuis référentiel ;
- modification prix/disponibilité/visibilité ;
- garde anti-doublon côté UI.

### P1 — Créneaux, horaires et fermetures

Objectif : donner au marchand l'autonomie minimale pour organiser les rendez-vous de retrait.

À livrer :

- entrée active "Créneaux" dans `MerchantShell` ;
- CRUD des créneaux ponctuels ;
- affichage des règles récurrentes existantes ;
- génération de créneaux depuis les règles ;
- gestion des fermetures exceptionnelles ;
- consultation/modification des horaires d'ouverture.

Points d'attention :

- ne pas créer de logique de capacité uniquement côté frontend ;
- afficher clairement les impacts sur les rendez-vous existants ;
- garder les dates/heures compréhensibles localement.

Tests attendus :

- services créneaux/règles/fermetures ;
- création/modification/suppression ;
- états de conflit ou validation backend.

## Priorité secondaire

### P2 — Onboarding marchand guidé

Objectif : accompagner un marchand nouvellement créé vers une supérette exploitable.

À livrer :

- page ou wizard d'onboarding au premier login ;
- consommation `GET /api/merchant/onboarding` ;
- affichage des étapes par `step.key`, pas par label backend uniquement ;
- action `PATCH /api/merchant/onboarding/complete` ;
- reprise depuis les paramètres.

Points d'attention :

- les labels backend sont en français uniquement ; le frontend doit prévoir des clés i18n ;
- aucune obligation bloquante côté MVP : le backend accepte déjà la complétion de façon idempotente.

### P2 — QR code magasin marchand

Objectif : permettre au marchand d'accéder et de partager le QR code de sa supérette.

À livrer :

- page ou bloc dans paramètres ;
- consommation `GET /api/merchant/stores/{storeId}/qr-code` ;
- rendu QR côté frontend ;
- copie du lien cible ;
- téléchargement PNG si le choix frontend est confirmé.

Point d'attention :

- l'URL cible backend peut être relative selon les endpoints ; composer une URL absolue côté frontend avant impression.

### P2 — Thème et paramètres supérette

Objectif : permettre au marchand de personnaliser l'identité visuelle de sa supérette dans le périmètre Sprint 6.

À livrer :

- entrée active "Paramètres" ;
- lecture et modification du thème de supérette ;
- aperçu simple des couleurs/police ;
- modification des informations publiques autorisées si l'API marchand le permet.

Points d'attention :

- garder les contrôles simples : couleurs, police, taille de base ;
- pas d'upload média dans cette tranche sauf décision explicite.

### P2 — Export CSV commandes

Objectif : exposer côté UI l'export CSV déjà livré côté backend.

À livrer :

- bouton "Exporter CSV" dans l'historique commandes ;
- filtres alignés avec ceux de l'historique ;
- gestion du téléchargement et des erreurs API.

## Chantiers résolus

### PR #138 — Historique complet des commandes marchand

Objectif livré : permettre au marchand de retrouver les commandes terminées, annulées, refusées ou encore à retirer.

Livré :

- onglet "Historique" actif sur `/merchant/commandes` ;
- consommation de `GET /api/merchant/stores/{storeId}/orders/history` ;
- filtres "À retirer" (`ready`, `pickup_pending`) et "Clôturées" (`completed`, `cancelled`, `rejected`) ;
- pagination simple ;
- ouverture du détail existant `/merchant/commandes/{orderId}` ;
- types frontend dédiés au payload historique ;
- support backend des filtres multi-statuts CSV pour l'historique.

Points d'attention :

- pas de mode lecture seule forcé par provenance historique : le détail reste déterminé par le statut courant ;
- export CSV toujours traité comme chantier P2 séparé.

Tests attendus :

- service `listMerchantOrderHistory` avec filtres ;
- rendu de l'onglet historique ;
- navigation vers le détail ;
- test fonctionnel backend des filtres multi-statuts.

## Chantiers à reporter

Ces sujets sont utiles mais ne doivent pas bloquer le MVP marchand :

- scan caméra QR réel dans le navigateur ;
- WebSocket, Mercure ou notifications push ;
- statistiques avancées ;
- multi-supérette complexe pour un même marchand ;
- gestion de stock multi-entrepôts ;
- paiement en ligne ou livraison.

## Ordre de PR conseillé

1. Notifications marchand.
2. Catalogue marchand.
3. Créneaux, horaires et fermetures.
4. Onboarding marchand guidé.
5. QR code magasin marchand.
6. Paramètres et thème supérette.
7. Export CSV.

Cet ordre privilégie d'abord les opérations quotidiennes : retrouver une commande, être notifié, maintenir le catalogue, puis gérer les rendez-vous de retrait.
