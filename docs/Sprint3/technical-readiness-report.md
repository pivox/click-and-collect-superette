# Rapport technique Sprint 3 — préparation développement

## Contexte

Ce rapport synthétise l'état du backend avant le développement du Sprint 3.

Le Sprint 3 reste aligné MVP : pas de paiement en ligne, pas de livraison, pas de programme de fidélité et pas de panier marketplace multi-marchands.

Objectif Sprint 3 : permettre au marchand de traiter les commandes de sa supérette depuis la réception jusqu'à la commande prête, avec acceptation, refus, acceptation partielle, annulation client, préparation ligne par ligne, historique de statuts et dashboard journalier.

## Portée et sources analysées

Ce document est un rapport de préparation technique, pas un contrat API définitif. Les endpoints proposés doivent rester validés par les user stories Sprint 3 et par le design API Platform au moment de l'implémentation.

Sources relues pour cette analyse :

- `AGENTS.md` ;
- `AI_CONTEXT.md` ;
- `README.md` ;
- `docs/roadmap/mvp-roadmap.md` ;
- `docs/Sprint3/README.md` ;
- `docs/product/mvp-functional-audit.md` ;
- user stories Sprint 3 liées aux commandes marchand, à l'annulation client, à l'acceptation partielle, à l'historique, au dashboard et à la préparation ;
- `apps/backend/src/` ;
- `apps/backend/tests/`.

Vérifications réalisées lors de l'analyse :

- lecture des ressources produit et Sprint 3 ;
- inventaire des fichiers backend avec `find` ;
- recherche des ApiResource, processors et providers avec `rg` ;
- lecture ciblée des entités, repositories, processors et tests existants.

Aucun test applicatif Symfony/PHPUnit n'a été lancé pour ce rapport documentaire.

## État actuel du code

### Entités existantes

Les entités métier nécessaires au parcours Sprint 3 existent déjà côté backend :

- `Order` : commande liée à un client, une supérette, une Kadhia optionnelle, un créneau de retrait optionnel, un statut, une raison de refus, des lignes et un total TND.
- `OrderLine` : ligne de commande avec produit marchand, quantité, prix unitaire snapshot et total de ligne.
- `Kadhia` : panier métier client lié à une supérette, avec statut `draft` ou `submitted`.
- `KadhiaLine` : ligne de Kadhia avec produit marchand, quantité et prix unitaire snapshot.
- `PickupSlot` : créneau de retrait avec capacité, compteur réservé et activation.
- `Shop` : supérette avec propriétaire marchand (`owner`).
- `User` : utilisateur avec nom, téléphone optionnel et rôles.

### Transitions commande déjà présentes

L'entité `Order` porte déjà les transitions domaine suivantes :

- `submit()` ;
- `accept()` ;
- `partiallyAccept()` ;
- `resubmit()` ;
- `reject()` ;
- `startPreparing()` ;
- `markReady()` ;
- `cancel()`.

L'enum `OrderStatus` contient déjà les statuts MVP attendus, dont `partially_accepted`, `preparing`, `ready`, `pickup_pending`, `completed` et `cancelled`.

### Processors existants autour des transitions

Les transitions sont actuellement appelées directement depuis des processors API Platform :

- `SubmitOrderProcessor` crée ou resoumet une commande depuis une Kadhia, copie les lignes, calcule le total et réserve le créneau.
- `MerchantAcceptOrderProcessor` accepte une commande soumise.
- `MerchantRejectOrderProcessor` refuse une commande et libère le créneau.
- `MerchantStartPreparationProcessor` passe une commande acceptée en préparation.
- `MerchantMarkReadyProcessor` passe une commande en préparation à prête.

Il n'existe pas encore de service central `OrderStatusTransitionService`. Pour le MVP, un petit service dédié au logging de statuts est préférable à un gros refactor.

## Endpoints déjà présents

### Client

- `POST /api/me/stores/{storeId}/kadhias`
- `GET /api/me/kadhias/{kadhiaId}`
- `PATCH /api/me/kadhias/{kadhiaId}`
- `PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}`
- `DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}`
- `POST /api/me/kadhias/{kadhiaId}/submit`
- `GET /api/me/orders/{id}`
- `GET /api/me/orders`
- `GET /api/stores/{storeId}/pickup-slots`

### Marchand

- `GET /api/merchant/stores/{storeId}/orders`
- `POST /api/merchant/stores/{storeId}/orders/{orderId}/accept`
- `POST /api/merchant/stores/{storeId}/orders/{orderId}/reject`
- `POST /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation`
- `POST /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready`

## Gaps Sprint 3

### 1. `OrderStatusLog`

État : absent.

À prévoir :

- entité `OrderStatusLog` ;
- repository ;
- migration Doctrine ;
- endpoints d'historique client et marchand ;
- insertion d'un log à chaque transition : `submitted`, `accepted`, `rejected`, `partially_accepted`, `preparing`, `ready`, `cancelled`, puis transitions Sprint 4.

### 2. Détail commande marchand

État : manquant.

La liste marchand existe, mais il manque :

- `GET /api/merchant/stores/{storeId}/orders/{orderId}` ;
- un provider item marchand ;
- une représentation détail distincte de la liste.

### 3. Coordonnées client dans le détail commande

État : données disponibles, exposition manquante.

`User` contient déjà `name` et `phone`, mais `MerchantOrderOutput` ne les expose pas.

À prévoir uniquement dans le détail marchand :

- `customerName` ;
- `customerPhone` nullable.

Ne pas exposer ces coordonnées dans la liste des commandes.

### 4. Accept / reject

État : présent.

Gaps restants :

- tracer les transitions dans `OrderStatusLog` ;
- stocker la raison de refus dans le log ;
- vérifier que les tests couvrent la sécurité marchand et les transitions invalides.

### 5. Annulation client

État : méthode domaine présente, endpoint absent.

À prévoir :

- `POST /api/me/orders/{orderId}/cancel` ;
- autorisation uniquement pour le client propriétaire ;
- annulation API uniquement depuis `submitted` ;
- libération du créneau de retrait ;
- log `cancelled`.

Attention : `Order::cancel()` autorise aussi `draft` et `accepted`. Le processor client doit donc appliquer une règle plus stricte.

### 6. Préparation ligne par ligne

État : incomplet.

`OrderLine` n'a pas encore de champ `prepared`.

À prévoir :

- champ `prepared` sur `OrderLine` ;
- migration Doctrine ;
- endpoint marchand pour cocher une ligne ;
- exposition de l'état `prepared` dans le détail commande marchand ;
- persistance entre sessions.

### 7. Mark ready strict

État : endpoint présent mais trop permissif.

`mark-ready` vérifie le statut `preparing`, mais ne vérifie pas encore que toutes les lignes sont préparées.

À prévoir :

- bloquer `mark-ready` si une ligne n'est pas préparée ;
- retourner une erreur métier claire ;
- log `ready` lorsque la transition réussit.

### 8. Acceptation partielle

État : méthode domaine présente, endpoint absent.

À prévoir :

- `POST /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept` ;
- DTO avec `rejected_line_ids` et `notes` optionnelles ;
- validation : au moins une ligne refusée, mais pas toutes les lignes ;
- passage commande en `partially_accepted` ;
- retour de la Kadhia en `draft` ;
- suppression des lignes refusées dans la Kadhia ;
- conservation du créneau ;
- resoumission sur la même commande existante.

### 9. Dashboard marchand

État : absent.

À prévoir :

- `GET /api/merchant/stores/{storeId}/dashboard` ;
- compteurs du jour par statut ;
- compteur des commandes urgentes `submitted` avec créneau dans moins de 3 heures ;
- créneaux du jour avec capacité et nombre de commandes ;
- sécurité via `MerchantShopAccessChecker`.

### 10. CRUD créneaux marchand

État : absent côté marchand.

La lecture publique/client existe, mais il manque :

- `GET /api/merchant/stores/{storeId}/pickup-slots` ;
- `POST /api/merchant/stores/{storeId}/pickup-slots` ;
- `PATCH /api/merchant/stores/{storeId}/pickup-slots/{slotId}` ;
- `DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}`.

Pour le MVP, privilégier une désactivation (`isActive=false`) plutôt qu'une suppression physique si des commandes existent.

## Ordre recommandé de développement

### PR 1 — `OrderStatusLog` et logs de transitions existantes

Objectif : poser la traçabilité avant d'ajouter de nouvelles transitions.

Fichiers probables :

- `apps/backend/src/Entity/OrderStatusLog.php`
- `apps/backend/src/Repository/OrderStatusLogRepository.php`
- `apps/backend/src/Service/OrderStatusLogRecorder.php`
- `apps/backend/src/Processor/SubmitOrderProcessor.php`
- `apps/backend/src/Processor/MerchantAcceptOrderProcessor.php`
- `apps/backend/src/Processor/MerchantRejectOrderProcessor.php`
- `apps/backend/src/Processor/MerchantStartPreparationProcessor.php`
- `apps/backend/src/Processor/MerchantMarkReadyProcessor.php`
- `apps/backend/migrations/Version*.php`

Tests nécessaires :

- mapping Doctrine ;
- log créé pour chaque transition existante ;
- ordre chronologique ;
- note de refus dans le log `rejected`.

### PR 2 — Détail commande marchand et coordonnées client

Objectif : fournir le détail métier nécessaire au traitement marchand.

Fichiers probables :

- `apps/backend/src/ApiResource/MerchantOrderOutput.php`
- `apps/backend/src/ApiResource/MerchantOrderDetailOutput.php`
- `apps/backend/src/Provider/MerchantOrderItemProvider.php`
- `apps/backend/src/Provider/MerchantOrderCollectionProvider.php`
- `apps/backend/tests/Functional/Api/MerchantOrderApiTest.php`

Tests nécessaires :

- détail accessible au marchand propriétaire ;
- 403 autre marchand ;
- 404 commande hors supérette ;
- coordonnées présentes dans le détail ;
- coordonnées absentes de la liste.

### PR 3 — Annulation client

Objectif : permettre au client d'annuler une commande `submitted` avant traitement marchand.

Fichiers probables :

- `apps/backend/src/ApiResource/OrderOutput.php`
- `apps/backend/src/Processor/CancelOrderProcessor.php`
- `apps/backend/src/Repository/OrderRepository.php`
- `apps/backend/tests/Functional/Api/OrderCancelApiTest.php`

Tests nécessaires :

- annulation nominale ;
- décrément du créneau ;
- log `cancelled` ;
- 409 si commande déjà traitée ;
- refus si commande d'un autre client ;
- 401 non connecté.

### PR 4 — Acceptation partielle

Objectif : permettre au marchand de refuser certaines lignes sans refuser toute la Kadhia.

Fichiers probables :

- `apps/backend/src/Dto/PartiallyAcceptOrderInput.php`
- `apps/backend/src/ApiResource/MerchantOrderOutput.php`
- `apps/backend/src/Processor/MerchantPartiallyAcceptOrderProcessor.php`
- `apps/backend/src/Repository/OrderRepository.php`
- `apps/backend/src/Repository/KadhiaLineRepository.php`
- `apps/backend/tests/Functional/Api/MerchantOrderApiTest.php`
- `apps/backend/tests/Functional/Api/SubmitOrderApiTest.php`

Tests nécessaires :

- commande passe en `partially_accepted` ;
- Kadhia repasse en `draft` ;
- lignes refusées supprimées ;
- créneau conservé ;
- resoumission met à jour la même commande ;
- 422 si aucune ligne refusée ;
- 422 si toutes les lignes sont refusées ;
- 409 si statut non `submitted` ;
- 403 autre marchand.

### PR 5 — Préparation ligne par ligne et `mark-ready` strict

Objectif : rendre la préparation persistée et empêcher une commande incomplète de passer à prête.

Fichiers probables :

- `apps/backend/src/Entity/OrderLine.php`
- `apps/backend/src/ApiResource/MerchantOrderOutput.php`
- `apps/backend/src/ApiResource/OrderLineOutput.php`
- `apps/backend/src/Dto/PrepareOrderLineInput.php`
- `apps/backend/src/Processor/MerchantPrepareOrderLineProcessor.php`
- `apps/backend/src/Processor/MerchantMarkReadyProcessor.php`
- `apps/backend/migrations/Version*.php`
- `apps/backend/tests/Functional/Api/MerchantOrderApiTest.php`
- `apps/backend/tests/Unit/Entity/OrderTest.php`

Tests nécessaires :

- cocher une ligne persiste `prepared=true` ;
- détail expose `prepared` ;
- `mark-ready` refusé si une ligne n'est pas préparée ;
- `mark-ready` accepté si toutes les lignes sont préparées ;
- log `ready` ;
- sécurité marchand.

### PR 6 — CRUD créneaux marchand

Objectif : permettre au marchand de gérer ses rendez-vous de retrait.

Fichiers probables :

- `apps/backend/src/ApiResource/MerchantPickupSlotOutput.php`
- `apps/backend/src/Dto/MerchantPickupSlotCreateInput.php`
- `apps/backend/src/Dto/MerchantPickupSlotPatchInput.php`
- `apps/backend/src/Provider/MerchantPickupSlotCollectionProvider.php`
- `apps/backend/src/Processor/CreateMerchantPickupSlotProcessor.php`
- `apps/backend/src/Processor/UpdateMerchantPickupSlotProcessor.php`
- `apps/backend/src/Processor/DeleteMerchantPickupSlotProcessor.php`
- `apps/backend/src/Repository/PickupSlotRepository.php`
- `apps/backend/tests/Functional/Api/PickupSlotApiTest.php`

Tests nécessaires :

- création ;
- modification ;
- désactivation ;
- créneau désactivé absent côté client ;
- refus capacité inférieure à `bookedCount` ;
- 403 autre marchand.

### PR 7 — Dashboard marchand

Objectif : donner au marchand une synthèse journalière de ses commandes.

Fichiers probables :

- `apps/backend/src/ApiResource/MerchantDashboardOutput.php`
- `apps/backend/src/Provider/MerchantDashboardProvider.php`
- `apps/backend/src/Repository/OrderRepository.php`
- `apps/backend/src/Repository/PickupSlotRepository.php`
- `apps/backend/tests/Functional/Api/MerchantDashboardApiTest.php`

Tests nécessaires :

- compteurs du jour par statut ;
- exclusion des commandes hors journée ;
- commandes urgentes ;
- créneaux du jour avec remplissage ;
- 403 autre marchand.

## Risques techniques

- `order_number` et `submitted_at` ne semblent pas encore présents sur `Order`, alors qu'ils facilitent la liste marchand.
- Les coordonnées client doivent rester absentes de la collection marchand pour préserver la confidentialité.
- L'acceptation partielle est le flux le plus risqué car elle modifie la commande, la Kadhia et les lignes.
- L'annulation client doit être limitée à `submitted`, même si la méthode domaine `cancel()` autorise plus de statuts.
- `mark-ready` est actuellement trop permissif tant que `OrderLine.prepared` n'existe pas.
- Il faut éviter un gros refactor de transitions pour Sprint 3 ; un service léger de logging suffit.

## Vérification avant chaque PR Sprint 3

Avant de coder une PR Sprint 3, vérifier systématiquement :

- que le changement reste limité à une supérette, une Kadhia et un marchand propriétaire ;
- que la sécurité sépare bien client, marchand et administrateur ;
- qu'aucune donnée privée client n'est exposée dans une collection marchand ;
- que toute transition de statut crée un `OrderStatusLog` ;
- que les migrations Doctrine accompagnent tout changement de schéma ;
- que les tests fonctionnels couvrent au minimum le succès, le mauvais rôle, le mauvais propriétaire et la transition invalide.

## Recommandation finale

Découper Sprint 3 en petites PRs indépendantes :

1. `OrderStatusLog` ;
2. détail commande marchand ;
3. annulation client ;
4. acceptation partielle ;
5. préparation ligne par ligne ;
6. CRUD créneaux marchand ;
7. dashboard marchand.

Ce découpage limite les risques, garde le vocabulaire métier Kadhia / supérette / marchand / client / rendez-vous / retrait, et reste strictement aligné MVP.
