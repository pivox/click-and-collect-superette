# Front marchand — prochains chantiers

Date : 2026-05-25

## Contexte

Le front marchand dispose désormais des bases suivantes :

- **PR #134** — fondations front marchand : login, contexte marchand, shell, dashboard, liste des commandes actives ;
- **PR #135** — détail commande et actions jusqu'à `ready` : accepter, refuser, accepter partiellement, préparer les lignes, marquer la commande prête ;
- **PR #136** — retrait sécurisé marchand : scan par token QR, passage en `pickup_pending`, confirmation marchand, force completion avec note ;
- **PR #138** — historique commandes marchand : onglet historique, filtres "À retirer" / "Clôturées", pagination, détail existant ;
- **PR #139** — notifications marchand : page notifications, badge non lu, filtres, rafraîchissement manuel, marquage lu et liens commandes.

Ce document liste les prochains chantiers front marchand, dans un ordre compatible avec le MVP Kadhia. Les chantiers déjà couverts sont conservés en bas de document pour garder la trace des décisions. Le périmètre reste strict : pas de paiement en ligne, pas de livraison, pas de programme de fidélité, pas de panier marketplace multi-marchands.

## Priorité recommandée

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

### PR #139 — Notifications marchand

Objectif livré : informer le marchand des nouvelles commandes, annulations client et retraits finalisés sans push ni temps réel dans le MVP.

Livré :

- service API notifications marchand et contrat frontend typé ;
- entrée "Notifications" dans `MerchantShell` ;
- badge de notifications non lues dans la navigation marchand ;
- page `/merchant/notifications` ;
- filtres "Toutes" / "Non lues" ;
- rafraîchissement manuel ;
- actions de marquage lu unitaire et global ;
- pagination ;
- liens vers les commandes liées ;
- états vide et erreur.

Points d'attention :

- pas de changement backend dans cette tranche ;
- pas de polling automatique, push, SMS, email, WebSocket ni Mercure ;
- la notification reste un écran consultatif MVP, basé sur l'API existante.

Tests couverts :

- service notifications marchand ;
- rendu de la page notifications ;
- filtres toutes / non lues ;
- marquage lu unitaire et global ;
- badge non lu dans `MerchantShell` ;
- `tsc --noEmit`, lint et build frontend.

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

Tests couverts :

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

1. Catalogue marchand.
2. Créneaux, horaires et fermetures.
3. Onboarding marchand guidé.
4. QR code magasin marchand.
5. Paramètres et thème supérette.
6. Export CSV.

Après l'historique et les notifications, cet ordre privilégie les opérations quotidiennes restantes : maintenir le catalogue, puis gérer les rendez-vous de retrait.
