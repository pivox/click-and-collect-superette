# Rapport technique Sprint 3 — état livré

## Contexte

Ce rapport était le document de préparation technique du Sprint 3. Il est maintenant conservé comme trace de readiness et aligné avec l'état du backend après les PR Sprint 3 mergées.

Sprint 3 reste aligné MVP : pas de paiement en ligne, pas de livraison, pas de programme de fidélité et pas de panier marketplace multi-marchands.

Objectif Sprint 3 : permettre au marchand de traiter les commandes de sa supérette depuis la réception jusqu'à la commande prête, avec acceptation, refus, acceptation partielle, annulation client, préparation ligne par ligne, historique de statuts, gestion ponctuelle des créneaux et dashboard journalier.

## Sources relues

- `AGENTS.md`
- `AI_CONTEXT.md`
- `README.md`
- `docs/roadmap/mvp-roadmap.md`
- `docs/Sprint3/README.md`
- `docs/product/mvp-functional-audit.md`
- User stories Sprint 3 : US-005, US-006, US-022, US-023, US-024, US-036, US-037, US-040, US-045, US-051.
- `apps/backend/src/`
- `apps/backend/tests/`

## Vérification d'inventaire

Inventaire local effectué pour cette mise à jour documentaire :

- inspection des ApiResource, processors, providers, repositories et entités Sprint 3 ;
- inspection des tests fonctionnels et Doctrine associés ;
- vérification des routes Symfony avec `php bin/console debug:router --env=test`.

## PRs Sprint 3 réalisées

| PR | Sujet | Etat |
| --- | --- | --- |
| #55 | US-040 OrderStatusLog et endpoints status-history | Merged |
| #56 | US-024 CRUD créneaux marchand | Merged |
| #59 | US-022 + US-045 détail commande marchand et coordonnées client | Merged |
| #60 | US-005 accept/reject marchand | Merged |
| #61 | US-036 annulation commande client | Merged |
| #62 | US-006 préparation ligne par ligne | Merged |
| #63 | US-023 mark-ready strict | Merged |
| #64 | US-037 acceptation partielle | Merged |
| #65 | US-051 dashboard marchand journalier | Merged |

La consultation liste/détail marchand et les coordonnées client (US-022 + US-045) sont présentes dans le backend et couvertes par `MerchantOrderApiTest`.

## Etat actuel du code

### Entites principales

- `Order` : commande liée à un client, une supérette, une Kadhia optionnelle, un créneau de retrait optionnel, un statut, une raison de refus, des lignes et un total TND.
- `OrderLine` : ligne de commande avec produit marchand, quantité, prix unitaire snapshot, total de ligne et état `prepared`.
- `OrderStatusLog` : historique immuable des transitions avec statut, note optionnelle et horodatage.
- `Kadhia` : panier métier client lié à une supérette, avec statut `draft` ou `submitted`.
- `KadhiaLine` : ligne de Kadhia avec produit marchand, quantité et prix unitaire snapshot.
- `PickupSlot` : créneau de retrait avec capacité, compteur réservé et activation.
- `Shop` : supérette avec propriétaire marchand (`owner`).
- `User` : utilisateur client ou marchand.

### Transitions commande presentes

L'entite `Order` porte les transitions domaine utiles au Sprint 3 :

- `submit()`
- `accept()`
- `partiallyAccept()`
- `resubmit()`
- `reject()`
- `startPreparing()`
- `markReady()`
- `cancel()`

L'enum `OrderStatus` contient les statuts MVP : `draft`, `submitted`, `accepted`, `partially_accepted`, `rejected`, `preparing`, `ready`, `pickup_pending`, `completed`, `cancelled`.

### Services, processors et providers Sprint 3

- `OrderStatusLogRecorder`
- `SubmitOrderProcessor`
- `MerchantAcceptOrderProcessor`
- `MerchantRejectOrderProcessor`
- `MerchantPartiallyAcceptOrderProcessor`
- `CancelOrderProcessor`
- `MerchantStartPreparationProcessor`
- `MerchantPrepareOrderLineProcessor`
- `MerchantMarkReadyProcessor`
- `CreateMerchantPickupSlotProcessor`
- `UpdateMerchantPickupSlotProcessor`
- `DeleteMerchantPickupSlotProcessor`
- `CustomerOrderStatusHistoryProvider`
- `MerchantOrderStatusHistoryProvider`
- `MerchantOrderCollectionProvider`
- `MerchantOrderItemProvider`
- `MerchantPickupSlotCollectionProvider`
- `MerchantDashboardProvider`

## Endpoints Sprint 3 présents

Routes confirmées par `debug:router` :

### Client

```http
GET  /api/me/orders
GET  /api/me/orders/{id}
POST /api/me/orders/{orderId}/cancel
GET  /api/me/orders/{orderId}/status-history
```

### Marchand — commandes

```http
GET   /api/merchant/stores/{storeId}/orders
GET   /api/merchant/stores/{storeId}/orders/{orderId}
POST  /api/merchant/stores/{storeId}/orders/{orderId}/accept
POST  /api/merchant/stores/{storeId}/orders/{orderId}/reject
POST  /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
POST  /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation
PATCH /api/merchant/stores/{storeId}/orders/{orderId}/lines/{merchantProductId}/preparation
POST  /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
GET   /api/merchant/stores/{storeId}/orders/{orderId}/status-history
```

### Marchand — créneaux

```http
GET    /api/merchant/stores/{storeId}/pickup-slots
POST   /api/merchant/stores/{storeId}/pickup-slots
PATCH  /api/merchant/stores/{storeId}/pickup-slots/{slotId}
DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}
```

### Marchand — dashboard

```http
GET /api/merchant/stores/{storeId}/dashboard/today
```

### Client — créneaux disponibles

```http
GET /api/stores/{storeId}/pickup-slots
```

## Gaps initiaux et resolution

| Gap initial | Etat actuel |
| --- | --- |
| `OrderStatusLog` absent | Livré avec entité, repository, recorder, migration et endpoints client/marchand |
| Detail commande marchand manquant | Livre via `GET /api/merchant/stores/{storeId}/orders/{orderId}` |
| Coordonnées client non exposées | Livrées dans le détail marchand autorisé, absentes de la collection |
| Accept/reject sans logs suffisants | Renforce : ownership, statut `submitted`, logs, raison de refus, liberation creneau sur reject |
| Annulation client absente | Livree : `POST /api/me/orders/{orderId}/cancel`, `submitted` uniquement |
| Preparation ligne par ligne incomplete | Livree : `OrderLine.prepared` et endpoint de preparation |
| `mark-ready` trop permissif | Renforce : statut `preparing` et toutes lignes preparees |
| Acceptation partielle absente | Livree : endpoint, validations, Kadhia `draft`, lignes refusees retirees, resoumission meme commande |
| Dashboard marchand absent | Livré : `/dashboard/today` avec compteurs, urgence et créneaux |
| CRUD créneaux marchand absent | Livré : GET/POST/PATCH/DELETE marchand |

## Couverture de tests

Tests Sprint 3 principaux presents :

- `MerchantOrderApiTest`
  - liste et détail marchand ;
  - confidentialité des coordonnées ;
  - accept/reject ;
  - acceptation partielle ;
  - start-preparation ;
  - mark-ready strict ;
  - preparation ligne par ligne ;
  - ownership, mauvais role, anonyme, mauvais statut.
- `OrderCancelApiTest`
  - annulation client ;
  - liberation de creneau ;
  - status-history ;
  - historique client ;
  - mauvais role, anonyme, mauvais statut.
- `OrderStatusHistoryApiTest`
  - historique client et marchand ;
  - ordre chronologique ;
  - ownership.
- `PickupSlotApiTest`
  - collection publique ;
  - CRUD marchand ;
  - activation/desactivation ;
  - capacite et chevauchements ;
  - contrats de routes.
- `MerchantDashboardApiTest`
  - acces marchand proprietaire ;
  - refus autre marchand/client/anonyme ;
  - 404 supérette inconnue via `testDashboardReturns404ForNonExistentStore()` ;
  - compteurs du jour ;
  - exclusion hier/autre supérette ;
  - créneaux du jour ;
  - compteur `pickup_pending_count` via `testDashboardCountsOnlyTodayOrdersForTargetShopByStatus()` ;
  - urgent submitted ;
  - absence de donnees client et de lignes.
- `SubmitOrderApiTest`
  - soumission initiale ;
  - resoumission apres acceptation partielle sur la meme commande.
- `OrderStatusLogDoctrineTest`
  - mapping et persistence de l'historique.

## Risques techniques restants

- Les notifications ne sont pas implementees : les transitions changent l'etat et l'historique, mais ne notifient pas encore le client ou le marchand.
- `PickupSession`, QR code de retrait, scan et double validation restent absents jusqu'au Sprint 4.
- `pickup_pending` et `completed` existent dans le domaine mais ne sont pas encore exposes par un parcours de retrait.
- Les créneaux sont ponctuels ; récurrence, fermetures exceptionnelles et heures d'ouverture restent Sprint 3b.
- L'expiration automatique d'une acceptation partielle et le delai de reponse marchand requierent une strategie Messenger/worker et restent Sprint 3b.
- Les endpoints historiques marchand complets avec filtres avances restent Sprint 3b.

## Verification avant nouvelles PR

Pour les PR suivantes, verifier systematiquement :

- que les limites Sprint 3b/Sprint 4 ne sont pas marquees livrees par erreur ;
- que les nouveaux endpoints gardent `storeId` quand l'ownership supérette est necessaire ;
- que les donnees client ne sont exposees qu'a un marchand autorise ;
- que les transitions de statut nouvelles ecrivent un `OrderStatusLog` ;
- que les tests couvrent succes, mauvais role, mauvais proprietaire et transition invalide.

## Conclusion

Le backend Sprint 3 core est livre. La suite produit doit maintenant traiter la maturite operationnelle marchand (Sprint 3b), puis le retrait securise et les notifications (Sprint 4), sans reouvrir les contrats Sprint 3 sauf bug ou correction de coherence.
