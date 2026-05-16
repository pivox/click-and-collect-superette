# Sprint 3b — Maturité opérationnelle marchand

## Statut

**Statut : fondation documentaire.**

Sprint 3b démarre après la clôture backend de Sprint 3 core et Sprint 4. Il ne change pas le parcours de retrait sécurisé ; il complète l'outillage quotidien du marchand autour des créneaux, disponibilités, historiques et automatisations.

Cette fondation ne livre aucun endpoint applicatif. Elle fixe le périmètre, les contrats cibles et l'ordre recommandé des futures PR backend.

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
| US-047 | Créneaux récurrents | Définir des règles hebdomadaires et générer les créneaux ponctuels sur 4 semaines | À coder |
| US-056 | Fermeture exceptionnelle | Bloquer une plage de dates/heures sans supprimer les règles récurrentes | À coder |
| US-057 | Heures d'ouverture | Définir les horaires hebdomadaires et les exposer publiquement | À coder |
| US-053 | Historique complet marchand | Lister toutes les commandes avec filtres et pagination | À coder |
| US-052 | Ruptures de stock en masse | Mettre à jour la disponibilité de plusieurs produits marchand | À coder |
| US-043 | Délai de réponse marchand | Annuler automatiquement une commande non traitée avant 2h du créneau | À coder |
| US-049 | Expiration acceptation partielle | Annuler automatiquement si le client ne re-soumet pas avant 2h du créneau | À coder |

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
- `query` : recherche par numéro de commande (`#0042`) ou nom client ;
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
