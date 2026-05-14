# Sprint 3 — Parcours marchand

## Objectif du sprint

Sprint 3 couvre l'intégralité du parcours marchand depuis la réception d'une commande soumise jusqu'à la déclaration « prête à retirer », ainsi que la gestion des créneaux de retrait.

Le marchand peut désormais traiter les commandes de ses clients, gérer partiellement les acceptations et configurer ses créneaux horaires.

## Parcours cible

```text
Nouvelle commande soumise (notification)
→ consultation de la liste des commandes
→ consultation du détail d'une commande
→ décision : accepter / refuser / accepter partiellement
→ (si partiellement accepté) le client resoumets → recommencer depuis la décision
→ passage en préparation
→ déclaration prête
→ commande prête pour le retrait (Sprint 4)
```

## Décisions produit

- Un marchand ne voit que les commandes de la supérette dont il est propriétaire (`Shop.owner`).
- L'acceptation partielle remet la Kadhia en `draft` avec les lignes acceptées ; le client peut modifier et resoumettre.
- La re-soumission après acceptation partielle met à jour la commande existante (pas de création d'une nouvelle commande).
- Le marchand peut configurer des créneaux manuellement. Aucune génération automatique dans le MVP.
- Un créneau désactivé n'est plus visible pour les clients mais les commandes existantes restent valides.
- Le marchand ne peut modifier ni supprimer un créneau passé ayant des commandes associées.
- Chaque transition de statut enregistre un `OrderStatusLog` horodaté.

## User stories concernées

| US | Sujet | Epic | Statut |
|---|---|---|---|
| US-022 | Consulter la liste des commandes soumises | EPIC-005 | Existante |
| US-005 | Accepter ou refuser une commande | EPIC-005 | Existante |
| US-037 | Accepter partiellement une commande | EPIC-005 | Ajoutée |
| US-036 | Annuler une commande (client) | EPIC-004 | Ajoutée |
| US-006 | Préparer une commande ligne par ligne | EPIC-006 | Existante |
| US-023 | Déclarer une commande prête | EPIC-006 | Existante |
| US-024 | Configurer les créneaux de retrait | EPIC-012 | Existante |

## Modèle métier à prévoir

### Créneaux de retrait — gestion marchande

```text
Endpoints nouveaux :
POST   /api/merchant/stores/{storeId}/pickup-slots
PATCH  /api/merchant/stores/{storeId}/pickup-slots/{slotId}
DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}
GET    /api/merchant/stores/{storeId}/pickup-slots
```

Payload création :
```json
{
  "starts_at": "2026-06-01T10:00:00",
  "ends_at": "2026-06-01T11:00:00",
  "capacity": 5
}
```

Payload modification :
```json
{
  "capacity": 8,
  "is_active": false
}
```

### Acceptation partielle

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
```

Payload :
```json
{
  "rejected_line_ids": ["<merchantProductId1>"],
  "notes": "Rupture de stock."
}
```

### Annulation client

```http
POST /api/me/orders/{orderId}/cancel
```

### Historique de statuts

```text
Nouvelle entité OrderStatusLog :
- order_id
- status
- note (nullable)
- created_at
```

Insérer un log à chaque transition dans tous les Processors.

## Endpoints Sprint 3

### Marchand — commandes

```http
GET  /api/merchant/stores/{storeId}/orders
GET  /api/merchant/stores/{storeId}/orders/{orderId}
POST /api/merchant/stores/{storeId}/orders/{orderId}/accept
POST /api/merchant/stores/{storeId}/orders/{orderId}/reject
POST /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
POST /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation
POST /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
```

### Marchand — créneaux

```http
GET    /api/merchant/stores/{storeId}/pickup-slots
POST   /api/merchant/stores/{storeId}/pickup-slots
PATCH  /api/merchant/stores/{storeId}/pickup-slots/{slotId}
DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}
```

### Client — annulation

```http
POST /api/me/orders/{orderId}/cancel
```

### Client et marchand — historique de statuts

```http
GET /api/me/orders/{orderId}/status-history
GET /api/merchant/stores/{storeId}/orders/{orderId}/status-history
```

## Hors périmètre Sprint 3

- QR code de retrait (Sprint 4).
- Double validation retrait (Sprint 4).
- Notifications push ou SMS (Sprint 4).
- Administration supérettes et marchands (Sprint 5).

## Définition de fini globale

Le Sprint 3 est cohérent lorsque :

1. Le marchand reçoit les commandes soumises et peut les consulter avec leurs lignes.
2. Il peut accepter, refuser ou accepter partiellement.
3. L'acceptation partielle remet la Kadhia du client en `draft` avec les bonnes lignes.
4. Le marchand peut configurer des créneaux horaires via l'API.
5. Le client peut annuler une commande `submitted`.
6. Chaque transition de statut est tracée dans `OrderStatusLog`.
7. Le marchand peut passer une commande en préparation puis en prête.
