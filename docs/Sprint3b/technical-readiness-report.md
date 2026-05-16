# Sprint 3b — Rapport technique de preparation

## Synthese

Le backend dispose deja des briques principales pour construire Sprint 3b : gestion manuelle des `PickupSlot`, ownership marchand via `Shop.owner`, commandes avec statuts complets, `OrderStatusLog`, notifications in-app et Symfony Messenger. Les fonctionnalites Sprint 3b restent a implementer et doivent etre livrees par petites PRs atomiques.

Point d'attention majeur : les automatisations US-043 et US-049 dependent d'un transport Messenger asynchrone persistant et d'un worker actif. Le transport de test est `in-memory://` et le transport local peut etre `sync://`; cela ne garantit pas un vrai differe production.

## Etat existant audite

| Brique | Etat actuel | Impact Sprint 3b |
|---|---|---|
| `PickupSlot` | Entite ponctuelle avec `shop`, `startsAt`, `endsAt`, `capacity`, `bookedCount`, `isActive` | Base de generation depuis des regles recurrentes |
| CRUD creneaux ponctuels | `GET/POST/PATCH/DELETE /api/merchant/stores/{storeId}/pickup-slots` | A conserver ; Sprint 3b ajoute les regles, pas une refonte du CRUD |
| `PickupSlotRepository` | Recherche par shop, plage de date, disponibilite, overlap actif | Peut servir a l'idempotence et aux conflits de generation |
| `MerchantOrder` endpoints | Liste operationnelle, detail, transitions accept/reject/partial/start/ready | Historique complet doit etre un endpoint separe |
| `OrderRepository` | Pagination par client/shop, filtre status, compteurs dashboard par date de creneau | A etendre pour filtres avances history et automatisations |
| `OrderStatusLog` | Trace les transitions principales | A reutiliser pour annulations automatiques |
| `OrderTransitionService` | Centralise `markReady`, `markPickupPending`, `markCompleted` | Ne pas refactorer globalement ; ajouter uniquement ce qui est necessaire aux automatisations |
| `NotificationService` | Notifications in-app client/marchand sur transitions et rappel | A reutiliser pour notifications client US-043/US-049 |
| Messenger | `SendPickupReminderMessage` route vers `async`; test en `in-memory://` | Pattern a reutiliser pour messages de delai/expiration |
| `Shop` | Donnees publiques et owner, pas encore `openingHours` | Ajouter un JSON d'horaires en S3B-003 |
| `MerchantProduct` | `isAvailable`, `isVisible`, `merchantNote` | Base pour action de rupture en masse |

## Entites et migrations probables

### `PickupSlotRule` — S3B-001

Champs probables :

- `id` UUID ;
- `shop` ManyToOne obligatoire ;
- `weekday` smallint ou enum interne, 1-7 ou 0-6 a figer explicitement ;
- `startTime` ;
- `endTime` ;
- `capacity` ;
- `isActive` ;
- `createdAt` ;
- `updatedAt`.

Contraintes recommandees :

- index `shop_id, weekday` ;
- validation `startTime < endTime` ;
- `capacity > 0` ;
- prevention des overlaps actifs par supérette et jour.

### `ExceptionalClosure` — S3B-002

Champs probables :

- `id` UUID ;
- `shop` ManyToOne obligatoire ;
- `startsAt` ;
- `endsAt` ;
- `reason` nullable ;
- `createdAt` ;
- `updatedAt`.

Contraintes recommandees :

- index `shop_id, starts_at, ends_at` ;
- validation `startsAt < endsAt` ;
- ownership marchand sur toutes les operations.

### `Shop.openingHours` — S3B-003

Champ probable :

- `openingHours` JSON nullable ou JSON avec structure hebdomadaire par defaut.

Structure indicative :

```json
{
  "timezone": "Africa/Tunis",
  "week": {
    "monday": [{"opens_at": "08:00", "closes_at": "20:00"}],
    "tuesday": [{"opens_at": "08:00", "closes_at": "20:00"}]
  }
}
```

La PR devra valider la structure et eviter de faire porter au frontend une interpretation ambigue.

## Readiness par US

### S3B-001 / US-047 — Creneaux recurrents

Existant reutilisable :

- `PickupSlot`, `PickupSlotRepository::findForShopBetweenStartsAt()`, `hasActiveOverlapForShop()`, `MerchantShopAccessChecker`.

A ajouter :

- entite `PickupSlotRule` ;
- repository dedie ;
- DTO create/patch ;
- outputs API Platform ;
- processor de generation sur 4 semaines.

Risques :

- doublons de creneaux si generation rejouee ;
- fuseau horaire et changement de jour ;
- overlap entre creneaux manuels et generes ;
- generation sur fermeture exceptionnelle a integrer apres S3B-002.

Tests :

- CRUD owner/forbidden/anonymous ;
- validation horaires/capacite ;
- generation 4 semaines ;
- idempotence generation ;
- absence de duplication avec creneaux existants.

### S3B-002 / US-056 — Fermetures exceptionnelles

Existant reutilisable :

- `Shop`, ownership marchand, `PickupSlotRepository`.

A ajouter :

- entite `ExceptionalClosure` ;
- CRUD marchand ;
- integration dans la generation S3B-001.

Risques :

- creneaux deja reserves dans une plage fermee ;
- fermeture partielle de journee ;
- chevauchement entre fermetures.

Tests :

- CRUD owner/forbidden/anonymous ;
- validation `startsAt < endsAt` ;
- blocage generation pendant fermeture ;
- comportement explicite si creneau actif/reserve existe deja.

### S3B-003 / US-057 — Heures d'ouverture

Existant reutilisable :

- `Shop`, `StorePublicOutput`, providers publics et marchands.

A ajouter :

- champ `Shop.openingHours` ;
- endpoint public lecture ;
- endpoint marchand lecture/modification ;
- validation de structure JSON.

Risques :

- contrat JSON trop permissif ;
- confusion entre heures d'ouverture vitrine et creneaux de retrait ;
- localisation FR/AR/RTL cote frontend.

Tests :

- lecture publique ;
- patch marchand proprietaire ;
- autre marchand/client/anonyme selon route ;
- refus structure invalide ;
- preservation des autres champs `Shop`.

### S3B-004 / US-053 — Historique complet marchand

Existant reutilisable :

- `OrderRepository::findByShopPaginated()`, `MerchantOrder` outputs, `OrderStatus`.

A ajouter :

- endpoint `/orders/history` ;
- filtres `status`, `date_from`, `date_to`, `customer_query`, `page`, `limit` ;
- repository query dediee.

Risques :

- fuite de donnees client dans la liste ;
- performances si recherche client naive ;
- divergence avec la liste operationnelle existante.

Tests :

- tous statuts ;
- filtres date/statut/client ;
- pagination ;
- autre supérette refusee ;
- absence de lignes detaillees si le contrat reste un resume.

### S3B-005 / US-052 — Ruptures de stock en masse

Existant reutilisable :

- `MerchantProduct.isAvailable`, `isVisible`, `merchantNote`, `MerchantProductRepository`.

A ajouter :

- DTO bulk availability ;
- processor transactionnel ;
- output resume d'action.

Risques :

- modification partielle si un produit n'appartient pas a la supérette ;
- impact attendu sur commandes deja soumises : elles ne doivent pas etre reecrites ;
- taille maximale du batch a fixer.

Tests :

- action groupee owner ;
- client/anonyme/autre marchand refuses ;
- identifiant inconnu ou autre supérette refuse sans modification partielle ;
- catalogue public ne montre plus les produits indisponibles.

### S3B-006 / US-043 — Delai reponse marchand automatique

Existant reutilisable :

- `Order`, `PickupSlot`, `OrderStatusLogRecorder`, `NotificationService`, Messenger.

A ajouter :

- message dedie, par exemple `ExpireMerchantResponseMessage` ;
- scheduler au moment de soumission ;
- handler transactionnel ;
- notification client ;
- eventuel helper de transition annulation automatique.

Risques :

- worker async absent en production ;
- course avec accept/reject manuel ;
- annulation apres changement de statut si le handler ne re-verifie pas l'etat ;
- liberation capacite du creneau a faire une seule fois.

Tests :

- message planifie a `pickupSlot.startsAt - 2h` ;
- handler annule uniquement `submitted` ;
- pas d'effet si commande deja acceptee/refusee/cancelled ;
- `OrderStatusLog` + notification client ;
- capacite liberee.

### S3B-007 / US-049 — Expiration acceptation partielle

Existant reutilisable :

- `partially_accepted`, Kadhia draft apres acceptation partielle, `NotificationService`, Messenger.

A ajouter :

- message dedie, par exemple `ExpirePartialAcceptanceMessage` ;
- scheduler lors de l'acceptation partielle ;
- handler transactionnel ;
- notification client ;
- regle explicite de liberation du creneau.

Risques :

- resoumission client concurrente avec expiration ;
- etat Kadhia/commande incoherent si annulation partielle mal bornee ;
- liberation creneau alors qu'une nouvelle soumission a conserve la reservation.

Tests :

- message planifie ;
- annulation uniquement si commande encore `partially_accepted` ;
- aucun effet apres resoumission ;
- notification + `OrderStatusLog` ;
- regle de creneau testee.

## Strategie de tests

Chaque PR backend Sprint 3b doit ajouter un test fonctionnel dedie et maintenir les tests de non-regression du domaine touche :

- `PickupSlotRuleApiTest` pour S3B-001 ;
- `ExceptionalClosureApiTest` pour S3B-002 ;
- `ShopOpeningHoursApiTest` pour S3B-003 ;
- `MerchantOrderHistoryApiTest` pour S3B-004 ;
- `MerchantBulkAvailabilityApiTest` pour S3B-005 ;
- tests handler/scheduler Messenger pour S3B-006 et S3B-007.

Tests transverses a conserver :

- ownership `Shop.owner` ;
- separation `ROLE_CUSTOMER` / `ROLE_MERCHANT` / anonyme ;
- coherence `storeId` ;
- absence de fuite de donnees client ;
- idempotence des handlers Messenger ;
- logs `OrderStatusLog` pour transitions automatiques.

## Risques techniques principaux

- **Messenger production** : `DelayStamp` ne suffit pas sans transport async persistant et worker supervise.
- **Concurrence** : les handlers automatiques doivent recharger la commande et re-verifier statut/capacite au moment d'execution.
- **Fuseau horaire** : les calculs de generation et delai doivent rester alignes sur `Africa/Tunis`.
- **Idempotence** : generation de creneaux et handlers differes doivent etre rejouables sans duplication ni double liberation.
- **Donnees client** : l'historique marchand doit rester proportionne et ne pas exposer de donnees inutiles en liste.
- **Scope creep** : ne pas transformer Sprint 3b en administration Sprint 5 ni en analytics/export Sprint 7.

## Decoupage PR recommande

1. **S3B-001 — Creneaux recurrents foundation**
2. **S3B-002 — Fermetures exceptionnelles**
3. **S3B-003 — Heures d'ouverture supérette**
4. **S3B-004 — Historique complet commandes marchand**
5. **S3B-005 — Ruptures stock en masse**
6. **S3B-006 — Delai reponse marchand automatique**
7. **S3B-007 — Expiration acceptation partielle**
8. **S3B-008 — Audit + cloture Sprint 3b**

La premiere PR backend recommandee apres cette fondation est **S3B-001 — Creneaux recurrents foundation**.
