# Sprint 3b — Maturité opérationnelle marchand

## Statut

**Statut : backend terminé — 2026-05-17.**

Sprint 3b est entièrement livré côté backend. Il complète l'outillage quotidien du marchand autour des créneaux, disponibilités, historiques et automatisations. PRs #91 à #101 mergées sur main.

## Objectif

Permettre au marchand de gérer son activité quotidienne avec moins d'opérations manuelles :

- créer des créneaux récurrents ;
- déclarer des fermetures exceptionnelles ;
- publier les heures d'ouverture ;
- consulter un historique complet des commandes ;
- gérer les ruptures de stock en masse ;
- automatiser les délais de réponse et d'expiration d'acceptation partielle.

## Périmètre

| US | Sujet | Objectif | Statut |
|---|---|---|---|
| US-047 | Créneaux récurrents | Définir des règles hebdomadaires et générer les créneaux ponctuels sur 4 semaines | ✅ Livré (S3B-001, PR #92) |
| US-056 | Fermeture exceptionnelle | Bloquer une plage de dates/heures sans supprimer les règles récurrentes | ✅ Livré (S3B-002, PR #93) |
| US-057 | Heures d'ouverture | Définir les horaires hebdomadaires et les exposer publiquement | ✅ Livré (S3B-003, PR #94) |
| US-053 | Historique complet marchand | Lister toutes les commandes avec filtres et pagination | ✅ Livré (S3B-004, PR #95) |
| US-052 | Ruptures de stock en masse | Mettre à jour la disponibilité de plusieurs produits marchand | ✅ Livré (S3B-005, PR #97) |
| US-043 | Délai de réponse marchand | Annuler automatiquement une commande non traitée avant 2h du créneau | ✅ Livré (S3B-006, PR #98) |
| US-049 | Expiration acceptation partielle | Annuler automatiquement si le client ne re-soumet pas avant 2h du créneau | ✅ Livré (S3B-007, PR #99 + #101) |

## Endpoints cibles

### US-047 — Créneaux récurrents

```http
GET    /api/merchant/stores/{storeId}/pickup-slot-rules
POST   /api/merchant/stores/{storeId}/pickup-slot-rules
PATCH  /api/merchant/stores/{storeId}/pickup-slot-rules/{id}
DELETE /api/merchant/stores/{storeId}/pickup-slot-rules/{id}
POST   /api/merchant/stores/{storeId}/pickup-slot-rules/generate
```

Règles métier :

- réservé à `ROLE_MERCHANT` ;
- ownership strict via `Shop.owner` ;
- `weekday`, `start_time`, `end_time`, `capacity` obligatoires ;
- génération sur 4 semaines glissantes ;
- génération idempotente : ne pas dupliquer un `PickupSlot` déjà créé pour la même supérette et la même plage ;
- respecter les fermetures exceptionnelles lorsque US-056 sera livrée ;
- ne pas supprimer les créneaux déjà réservés.

### US-056 — Fermetures exceptionnelles

```http
GET    /api/merchant/stores/{storeId}/exceptional-closures
POST   /api/merchant/stores/{storeId}/exceptional-closures
PATCH  /api/merchant/stores/{storeId}/exceptional-closures/{id}
DELETE /api/merchant/stores/{storeId}/exceptional-closures/{id}
```

Règles métier :

- réservé à `ROLE_MERCHANT` ;
- ownership strict via `Shop.owner` ;
- une fermeture bloque la génération de nouveaux créneaux dans la plage ;
- elle ne supprime pas les règles récurrentes ;
- si des créneaux ponctuels actifs existent déjà dans la plage, la PR devra trancher explicitement : refus de création, désactivation sans commande réservée, ou signalement manuel.

### US-057 — Heures d'ouverture

```http
GET   /api/stores/{storeId}/opening-hours
GET   /api/merchant/stores/{storeId}/opening-hours
PATCH /api/merchant/stores/{storeId}/opening-hours
```

Règles métier :

- lecture publique pour la vitrine client ;
- modification réservée au marchand propriétaire ;
- structure hebdomadaire stable, compatible FR/AR/RTL côté frontend ;
- fuseau de référence : `Africa/Tunis`, sauf configuration future explicite.

### US-053 — Historique complet marchand

```http
GET /api/merchant/stores/{storeId}/orders/history?status=&date_from=&date_to=&query=&page=&limit=
```

Filtres cibles :

- `status` ;
- `date_from` ;
- `date_to` ;
- `query` : recherche par nom, prénom ou téléphone client ;
- `page` ;
- `limit`.

Règles métier :

- réservé au marchand propriétaire ;
- tous statuts inclus, y compris `completed`, `cancelled`, `rejected` et `partially_accepted` ;
- pagination obligatoire ;
- ne pas exposer plus de données client que le détail commande marchand déjà autorisé ;
- ne pas casser `GET /api/merchant/stores/{storeId}/orders`, qui reste la liste opérationnelle existante.

### US-052 — Ruptures de stock en masse

```http
PATCH /api/merchant/stores/{storeId}/products/bulk-availability
```

Payload indicatif :

```json
{
  "merchant_product_ids": ["merchant-product-uuid"],
  "is_available": false,
  "merchant_note": "Rupture temporaire"
}
```

Règles métier :

- réservé au marchand propriétaire ;
- chaque `merchant_product_id` doit appartenir à la supérette cible ;
- action atomique : aucun produit modifié si un identifiant est invalide ;
- ne modifie pas les commandes déjà soumises ;
- ne modifie pas le référentiel produit global.

### US-043 — Délai de réponse marchand automatique

Objectif : annuler automatiquement une commande non traitée avant 2h du créneau.

Règles cible :

- statuts concernés à confirmer dans la PR : au minimum `submitted` ;
- annulation automatique avant `pickupSlot.startsAt - 2h` ;
- libération de la capacité du créneau si la commande est annulée ;
- création d'un `OrderStatusLog` ;
- notification client in-app ;
- exécution via Symfony Messenger avec `DelayStamp`.

### US-049 — Expiration acceptation partielle

Objectif : annuler automatiquement une acceptation partielle si le client ne re-soumet pas avant 2h du créneau.

Règles cible :

- statut concerné : `partially_accepted` ;
- alerte préventive client 4h avant le début du créneau si la commande est encore `partially_accepted` ;
- si la Kadhia n'est pas re-soumise avant le délai, la commande est annulée ;
- libération éventuelle du créneau à trancher en PR selon l'état exact de réservation ;
- création d'un `OrderStatusLog` ;
- notification client in-app pour l'alerte 4h et pour l'annulation automatique ;
- exécution via Symfony Messenger avec `DelayStamp`.

## Hors périmètre Sprint 3b

- Paiement en ligne.
- Livraison.
- Programme de fidélité.
- Marketplace multi-marchands avec Kadhia partagée.
- Refonte du retrait sécurisé Sprint 4.
- Refonte Auth.
- Administration Sprint 5.
- Export CSV et statistiques avancées Sprint 7.
- Notifications push, SMS, email, Mercure/WebSocket.

## Ordre recommandé des PR

1. **S3B-001 — Créneaux récurrents foundation**
   - Entité `PickupSlotRule`.
   - CRUD marchand.
   - Génération de `PickupSlot` sur 4 semaines.
2. **S3B-002 — Fermetures exceptionnelles**
   - Entité `ExceptionalClosure`.
   - CRUD marchand.
   - Blocage de génération sur périodes fermées.
3. **S3B-003 — Heures d'ouverture supérette**
   - Champ `Shop.openingHours`.
   - Lecture publique.
   - Modification marchand.
4. **S3B-004 — Historique complet commandes marchand**
   - Liste toutes commandes.
   - Filtres avancés.
   - Pagination.
5. **S3B-005 — Ruptures stock en masse**
   - Action groupée catalogue marchand.
6. **S3B-006 — Délai réponse marchand automatique**
   - Message Messenger.
   - Annulation automatique.
   - Notification client.
   - `OrderStatusLog`.
7. **S3B-007 — Expiration acceptation partielle**
   - Message Messenger.
   - Annulation si client ne répond pas.
   - Notification client.
   - `OrderStatusLog`.
8. **S3B-008 — Audit + clôture Sprint 3b**

## Critère de sortie Sprint 3b

Sprint 3b sera terminé lorsque le marchand pourra gérer ses créneaux récurrentement, déclarer des fermetures, exposer ses horaires, consulter tout son historique, traiter des ruptures de stock en masse et bénéficier d'automatisations fiables sur les commandes sans réponse ou partiellement acceptées.

---

## Clôture Sprint 3b

### Statut global : backend terminé — 2026-05-17

### US livrées

| US | Sujet | Statut |
|---|---|---|
| US-047 | Créneaux récurrents (PickupSlotRule + génération 4 semaines) | ✅ Livré (S3B-001, PR #92) |
| US-056 | Fermetures exceptionnelles | ✅ Livré (S3B-002, PR #93) |
| US-057 | Heures d'ouverture supérette | ✅ Livré (S3B-003, PR #94) |
| US-053 | Historique complet commandes marchand | ✅ Livré (S3B-004, PR #95) |
| US-052 | Ruptures de stock en masse | ✅ Livré (S3B-005, PR #97) |
| US-043 | Délai de réponse marchand automatique | ✅ Livré (S3B-006, PR #98) |
| US-049 | Expiration acceptation partielle | ✅ Livré (S3B-007, PR #99 + #101) |

### Endpoints livrés

```
GET    /api/merchant/stores/{storeId}/pickup-slot-rules
POST   /api/merchant/stores/{storeId}/pickup-slot-rules
PATCH  /api/merchant/stores/{storeId}/pickup-slot-rules/{ruleId}
DELETE /api/merchant/stores/{storeId}/pickup-slot-rules/{ruleId}
POST   /api/merchant/stores/{storeId}/pickup-slot-rules/generate

GET    /api/merchant/stores/{storeId}/exceptional-closures
POST   /api/merchant/stores/{storeId}/exceptional-closures
PATCH  /api/merchant/stores/{storeId}/exceptional-closures/{closureId}
DELETE /api/merchant/stores/{storeId}/exceptional-closures/{closureId}

GET    /api/stores/{storeId}/opening-hours
GET    /api/merchant/stores/{storeId}/opening-hours
PATCH  /api/merchant/stores/{storeId}/opening-hours

GET    /api/merchant/stores/{storeId}/orders/history

PATCH  /api/merchant/stores/{storeId}/products/bulk-availability
```

### Entités et champs ajoutés

- `PickupSlotRule` : `id`, `shop`, `weekday` (ISO 1–7), `startTime`, `endTime`, `capacity`, `isActive`, `createdAt`, `updatedAt`.
- `ExceptionalClosure` : `id`, `shop`, `startsAt`, `endsAt`, `reason`, `isActive`, `createdAt`, `updatedAt`.
- `Shop.openingHours` : JSON Doctrine (`opening_hours JSON DEFAULT NULL` en migration), structure `{ "timezone": "Africa/Tunis", "weekly": { "1": [...], ... "7": [...] } }`. Clés ISO 1–7 (et non `monday`...) — divergence documentée vs préparation initiale.

### Messages Messenger

- **S3B-006** : `ExpireMerchantResponseMessage` — commande `submitted` → `cancelled` si non traitée avant `pickupSlot.startsAt - 2h`.
- **S3B-007** : `PartialAcceptanceReminderMessage` — notification client à `pickupSlot.startsAt - 4h` (cycleId UUID par cycle d'acceptation partielle).
- **S3B-007** : `ExpirePartialAcceptanceMessage` — commande `partially_accepted` → `cancelled` si le client ne re-soumet pas avant `pickupSlot.startsAt - 2h`.

### Types de notification

- S3B-006 : `merchant_response_timeout`
- S3B-007 : `partial_acceptance_reminder_{cycleId}`, `partial_acceptance_timeout`

### Automatisations Messenger

- Expiration délai réponse marchand : commande `submitted` → `cancelled` si non traitée avant `pickupSlot.startsAt - 2h`
- Rappel acceptation partielle : notification client à `pickupSlot.startsAt - 4h`
- Expiration acceptation partielle : commande `partially_accepted` → `cancelled` si non re-soumise avant `pickupSlot.startsAt - 2h`

### Résultats des tests (audit 2026-05-17)

| Fichier de test | Résultat |
|---|---|
| `MerchantPickupSlotRuleApiTest.php` | OK (13 tests, 107 assertions) |
| `MerchantExceptionalClosureApiTest.php` | OK (16 tests, 76 assertions) |
| `ShopOpeningHoursApiTest.php` | OK (11 tests, 57 assertions) |
| `MerchantOrderHistoryApiTest.php` | OK (9 tests, 100 assertions) |
| `MerchantProductBulkAvailabilityApiTest.php` | OK (8 tests, 67 assertions) |
| `MerchantResponseTimeoutSchedulerTest.php` | OK (5 tests, 10 assertions) |
| `ExpireMerchantResponseMessageHandlerTest.php` | OK (14 tests, 72 assertions) |
| `PartialAcceptanceExpirationSchedulerTest.php` | OK (6 tests, 18 assertions) |
| `PartialAcceptanceReminderMessageHandlerTest.php` | OK (7 tests, 21 assertions) |
| `ExpirePartialAcceptanceMessageHandlerTest.php` | OK (15 tests, 72 assertions) |
| **Suite complète** | **OK (738 tests, 3009 assertions)** |
| PHPStan | 0 erreur (274 fichiers analysés) |
| PHP CS Fixer | 0 fichier à corriger |

### Limites MVP conservées intentionnellement

- Notifications in-app uniquement (pas SMS / email / push / Mercure / WebSocket).
- `DelayStamp` Symfony Messenger nécessite un transport async persistant et un worker actif en production. Le transport `sync://` local ne garantit pas un vrai différé.
- Pas de `SELECT FOR UPDATE` global sur toutes les transitions automatiques.
- Pas d'analytics (Sprint 7), pas d'administration (Sprint 5).
- `cycleId = ''` par défaut dans `PartialAcceptanceReminderMessage` pour compatibilité ascendante avec les messages en transit (corrigé PR #101).

### Suite recommandée

**Sprint 5 — Administration minimale** : CRUD supérettes et marchands admin, référentiel produit, QR code téléchargeable, onboarding marchand guidé.
