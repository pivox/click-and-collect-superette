# Sprint 3b — Rapport technique de préparation

## Synthèse

Le backend dispose déjà des briques principales pour construire Sprint 3b : gestion manuelle des `PickupSlot`, ownership marchand via `Shop.owner`, commandes avec statuts complets, `OrderStatusLog`, notifications in-app et Symfony Messenger. Les fonctionnalités Sprint 3b restent à implémenter et doivent être livrées par petites PRs atomiques.

Point d'attention majeur : les automatisations US-043 et US-049 dépendent d'un transport Messenger asynchrone persistant et d'un worker actif. Le transport de test est `in-memory://` et le transport local peut être `sync://` ; cela ne garantit pas un vrai différé production.

## État existant audité

| Brique | État actuel | Impact Sprint 3b |
|---|---|---|
| `PickupSlot` | Entité ponctuelle avec `shop`, `startsAt`, `endsAt`, `capacity`, `bookedCount`, `isActive` | Base de génération depuis des règles récurrentes |
| CRUD créneaux ponctuels | `GET/POST/PATCH/DELETE /api/merchant/stores/{storeId}/pickup-slots` | À conserver ; Sprint 3b ajoute les règles, pas une refonte du CRUD |
| `PickupSlotRepository` | Recherche par shop, plage de date, disponibilité, overlap actif | Peut servir à l'idempotence et aux conflits de génération |
| `MerchantOrder` endpoints | Liste opérationnelle, détail, transitions accept/reject/partial/start/ready | Historique complet doit être un endpoint séparé |
| `OrderRepository` | Pagination par client/shop, filtre status, compteurs dashboard par date de créneau | À étendre pour filtres avancés history et automatisations |
| `OrderStatusLog` | Trace les transitions principales | À réutiliser pour annulations automatiques |
| `OrderTransitionService` | Centralise `markReady`, `markPickupPending`, `markCompleted` | Ne pas refactorer globalement ; ajouter uniquement ce qui est nécessaire aux automatisations |
| `NotificationService` | Notifications in-app client/marchand sur transitions et rappel | A réutiliser pour notifications client US-043/US-049 |
| Messenger | `SendPickupReminderMessage` route vers `async`; test en `in-memory://` | Pattern à réutiliser pour messages de délai/expiration |
| `Shop` | Données publiques et owner, pas encore `openingHours` | Ajouter un JSON d'horaires en S3B-003 |
| `MerchantProduct` | `isAvailable`, `isVisible`, `merchantNote` | Base pour action de rupture en masse |

## Entités et migrations probables

### `PickupSlotRule` — S3B-001

Champs probables :

- `id` UUID ;
- `shop` ManyToOne obligatoire ;
- `weekday` smallint ou enum interne, 1-7 ou 0-6 à figer explicitement ;
- `startTime` ;
- `endTime` ;
- `capacity` ;
- `isActive` ;
- `createdAt` ;
- `updatedAt`.

Contraintes recommandées :

- index `shop_id, weekday` ;
- validation `startTime < endTime` ;
- `capacity > 0` ;
- prévention des overlaps actifs par supérette et jour.

### `ExceptionalClosure` — S3B-002

Champs probables :

- `id` UUID ;
- `shop` ManyToOne obligatoire ;
- `startsAt` ;
- `endsAt` ;
- `reason` nullable ;
- `createdAt` ;
- `updatedAt`.

Contraintes recommandées :

- index `shop_id, starts_at, ends_at` ;
- validation `startsAt < endsAt` ;
- ownership marchand sur toutes les opérations.

### `Shop.openingHours` — S3B-003

Champ probable :

- `openingHours` JSON nullable ou JSON avec structure hebdomadaire par défaut.

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

La PR devra valider la structure et éviter de faire porter au frontend une interprétation ambiguë.

## Readiness par US

### S3B-001 / US-047 — Créneaux récurrents

Existant réutilisable :

- `PickupSlot`, `PickupSlotRepository::findForShopBetweenStartsAt()`, `hasActiveOverlapForShop()`, `MerchantShopAccessChecker`.

À ajouter :

- entité `PickupSlotRule` ;
- repository dédié ;
- DTO create/patch ;
- outputs API Platform ;
- processor de génération sur 4 semaines.

Risques :

- doublons de créneaux si génération rejouée ;
- fuseau horaire et changement de jour ;
- overlap entre créneaux manuels et générés ;
- génération sur fermeture exceptionnelle à intégrer après S3B-002.

Tests :

- CRUD owner/forbidden/anonymous ;
- validation horaires/capacité ;
- génération 4 semaines ;
- idempotence génération ;
- absence de duplication avec créneaux existants.

### S3B-002 / US-056 — Fermetures exceptionnelles

Existant réutilisable :

- `Shop`, ownership marchand, `PickupSlotRepository`.

À ajouter :

- entité `ExceptionalClosure` ;
- CRUD marchand ;
- intégration dans la génération S3B-001.

Risques :

- créneaux déjà réservés dans une plage fermée ;
- fermeture partielle de journée ;
- chevauchement entre fermetures.

Tests :

- CRUD owner/forbidden/anonymous ;
- validation `startsAt < endsAt` ;
- blocage génération pendant fermeture ;
- comportement explicite si créneau actif/réservé existe déjà.

### S3B-003 / US-057 — Heures d'ouverture

Existant réutilisable :

- `Shop`, `StorePublicOutput`, providers publics et marchands.

À ajouter :

- champ `Shop.openingHours` ;
- endpoint public lecture ;
- endpoint marchand lecture/modification ;
- validation de structure JSON.

Risques :

- contrat JSON trop permissif ;
- confusion entre heures d'ouverture vitrine et créneaux de retrait ;
- localisation FR/AR/RTL côté frontend.

Tests :

- lecture publique ;
- patch marchand propriétaire ;
- autre marchand/client/anonyme selon route ;
- refus structure invalide ;
- préservation des autres champs `Shop`.

### S3B-004 / US-053 — Historique complet marchand

Existant réutilisable :

- `OrderRepository::findByShopPaginated()`, `MerchantOrder` outputs, `OrderStatus`.

À ajouter :

- endpoint `/orders/history` ;
- filtres `status`, `date_from`, `date_to`, `query`, `page`, `limit` ;
- recherche `query` couvrant le numéro de commande (`#0042`) et le nom client ;
- repository query dédiée.

Risques :

- fuite de données client dans la liste ;
- performances si recherche client naïve ;
- divergence avec la liste opérationnelle existante.

Tests :

- tous statuts ;
- filtres date/statut/numéro de commande/nom client ;
- pagination ;
- autre supérette refusée ;
- absence de lignes détaillées si le contrat reste un résumé.

### S3B-005 / US-052 — Ruptures de stock en masse

Existant réutilisable :

- `MerchantProduct.isAvailable`, `isVisible`, `merchantNote`, `MerchantProductRepository`.

À ajouter :

- DTO bulk availability ;
- processor transactionnel ;
- output résumé d'action.

Risques :

- modification partielle si un produit n'appartient pas à la supérette ;
- impact attendu sur commandes déjà soumises : elles ne doivent pas être réécrites ;
- taille maximale du batch à fixer.

Tests :

- action groupée owner ;
- client/anonyme/autre marchand refusés ;
- identifiant inconnu ou autre supérette refuse sans modification partielle ;
- catalogue public ne montre plus les produits indisponibles.

### S3B-006 / US-043 — Délai réponse marchand automatique

Existant réutilisable :

- `Order`, `PickupSlot`, `OrderStatusLogRecorder`, `NotificationService`, Messenger.

À ajouter :

- message dédié, par exemple `ExpireMerchantResponseMessage` ;
- scheduler au moment de soumission ;
- handler transactionnel ;
- notification client ;
- éventuel helper de transition annulation automatique.

Risques :

- worker async absent en production ;
- course avec accept/reject manuel ;
- annulation après changement de statut si le handler ne re-vérifie pas l'état ;
- libération capacité du créneau à faire une seule fois.

Tests :

- message planifié à `pickupSlot.startsAt - 2h` ;
- handler annule uniquement `submitted` ;
- pas d'effet si commande déjà acceptée/refusée/cancelled ;
- `OrderStatusLog` + notification client ;
- capacité libérée.

### S3B-007 / US-049 — Expiration acceptation partielle

Existant réutilisable :

- `partially_accepted`, Kadhia draft après acceptation partielle, `NotificationService`, Messenger.

À ajouter :

- messages dédiés, par exemple `PartialAcceptanceReminderMessage` et `ExpirePartialAcceptanceMessage` ;
- scheduler lors de l'acceptation partielle ;
- handler transactionnel ;
- notification client préventive 4h avant le créneau ;
- notification client d'annulation automatique 2h avant le créneau ;
- règle explicite de libération du créneau.

Risques :

- resoumission client concurrente avec expiration ;
- état Kadhia/commande incohérent si annulation partielle mal bornée ;
- libération créneau alors qu'une nouvelle soumission a conservé la réservation.

Tests :

- alerte 4h planifiée ;
- annulation 2h planifiée ;
- annulation uniquement si commande encore `partially_accepted` ;
- aucun effet après resoumission ;
- notification + `OrderStatusLog` ;
- règle de créneau testée.

## Stratégie de tests

Chaque PR backend Sprint 3b doit ajouter un test fonctionnel dédié et maintenir les tests de non-régression du domaine touché :

- `PickupSlotRuleApiTest` pour S3B-001 ;
- `ExceptionalClosureApiTest` pour S3B-002 ;
- `ShopOpeningHoursApiTest` pour S3B-003 ;
- `MerchantOrderHistoryApiTest` pour S3B-004 ;
- `MerchantBulkAvailabilityApiTest` pour S3B-005 ;
- tests handler/scheduler Messenger pour S3B-006 et S3B-007.

Tests transverses à conserver :

- ownership `Shop.owner` ;
- séparation `ROLE_CUSTOMER` / `ROLE_MERCHANT` / anonyme ;
- cohérence `storeId` ;
- absence de fuite de données client ;
- idempotence des handlers Messenger ;
- logs `OrderStatusLog` pour transitions automatiques.

## Risques techniques principaux

- **Messenger production** : `DelayStamp` ne suffit pas sans transport async persistant et worker supervisé.
- **Concurrence** : les handlers automatiques doivent recharger la commande et re-vérifier statut/capacité au moment d'exécution.
- **Fuseau horaire** : les calculs de génération et délai doivent rester alignés sur `Africa/Tunis`.
- **Idempotence** : génération de créneaux et handlers différés doivent être rejouables sans duplication ni double libération.
- **Données client** : l'historique marchand doit rester proportionné et ne pas exposer de données inutiles en liste.
- **Scope creep** : ne pas transformer Sprint 3b en administration Sprint 5 ni en analytics/export Sprint 7.

## Découpage PR recommandé

1. **S3B-001 — Créneaux récurrents foundation**
2. **S3B-002 — Fermetures exceptionnelles**
3. **S3B-003 — Heures d'ouverture supérette**
4. **S3B-004 — Historique complet commandes marchand**
5. **S3B-005 — Ruptures stock en masse**
6. **S3B-006 — Délai réponse marchand automatique**
7. **S3B-007 — Expiration acceptation partielle**
8. **S3B-008 — Audit + clôture Sprint 3b**

La première PR backend recommandée après cette fondation est **S3B-001 — Créneaux récurrents foundation**.
