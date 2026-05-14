# Sprint 4 — Retrait sécurisé

## Objectif du sprint

Sprint 4 finalise le cycle de vie d'une commande avec la remise physique en supérette : génération d'un QR code de retrait, scan marchand, double validation client + marchand, et finalisation.

Le client peut présenter son QR code, le marchand le scanne, les deux parties confirment et la commande est finalisée.

## Parcours cible

```text
Commande en statut ready
→ le client reçoit une notification « Kadhia prête ! »
→ le client affiche le QR code de retrait sur son téléphone
→ le marchand scanne le QR code
→ la commande passe en pickup_pending
→ le marchand confirme la remise
→ le client confirme la réception
→ la commande passe en completed
```

## Décisions produit

- Le QR code encode un token opaque (`PickupSession.token`, UUID v4).
- Le token est généré lors du passage en statut `ready` (par `MerchantMarkReadyProcessor`).
- Le token a une durée de validité de 24 heures.
- Le scan marchand précède la confirmation client. Le client ne peut pas confirmer seul.
- Si le client ne confirme pas dans les 5 minutes après le scan marchand, le marchand peut forcer la complétion avec une note.
- Un token déjà utilisé est refusé (usage unique).
- En cas de QR code expiré, la commande reste en `ready` et un nouveau token peut être régénéré par l'admin.
- Les notifications sont envoyées aux transitions clés (US-038 et US-039).

## User stories concernées

| US | Sujet | Epic | Statut |
|---|---|---|---|
| US-025 | Afficher le QR code de retrait (client) | EPIC-007 | Existante |
| US-007 | Double validation retrait | EPIC-007 | Existante |
| US-026 | Suivre le statut de sa commande | EPIC-007 | Existante |
| US-038 | Notifications client | EPIC-014 | Ajoutée |
| US-039 | Notifications marchand | EPIC-014 | Ajoutée |
| US-064 | Rappel de retrait avant expiration du créneau | EPIC-014 | Ajoutée |

## Modèle métier à prévoir

### Entité `PickupSession`

```text
pickup_sessions
- id (uuid)
- order_id (unique)
- token (uuid, unique)
- generated_at
- expires_at (generated_at + 24h)
- scanned_at (nullable)
- merchant_confirmed_at (nullable)
- customer_confirmed_at (nullable)
- is_used (bool, default false)
- force_completed_by_merchant (bool, default false)
- force_note (varchar 500, nullable)
- created_at

INDEX(token)
INDEX(order_id)
```

### Entité `Notification`

```text
notifications
- id (uuid)
- user_id
- order_id (uuid, nullable)
- title_fr, title_ar
- body_fr, body_ar
- is_read (bool, default false)
- created_at

INDEX(user_id, is_read, created_at)
```

## Endpoints Sprint 4

### Client — QR code de retrait

```http
GET /api/me/orders/{orderId}/pickup-session
```

Réponse :
```json
{
  "id": "<uuid>",
  "token": "<uuid>",
  "expires_at": "2026-05-15T14:00:00+01:00",
  "is_used": false,
  "qr_payload": "<uuid>"
}
```

### Marchand — scan QR

```http
POST /api/merchant/pickup-sessions/scan
```

Payload :
```json
{ "token": "<uuid>" }
```

Réponse 200 avec l'`OrderOutput` en statut `pickup_pending`.

### Marchand — confirmation remise

```http
PATCH /api/merchant/pickup-sessions/{id}/confirm
```

### Client — confirmation réception

```http
PATCH /api/me/pickup-sessions/{id}/confirm
```

### Client et marchand — notifications

```http
GET   /api/me/notifications?page=1&unread=true
PATCH /api/me/notifications/{id}/read
PATCH /api/me/notifications/read-all

GET   /api/merchant/notifications?page=1&unread=true
PATCH /api/merchant/notifications/{id}/read
PATCH /api/merchant/notifications/read-all
```

### Historique de statuts (client et marchand)

```http
GET /api/me/orders/{orderId}/status-history
GET /api/merchant/stores/{storeId}/orders/{orderId}/status-history
```

## Règles de transition complètes

| De | Vers | Déclenché par |
|---|---|---|
| `ready` | `pickup_pending` | Scan marchand `POST .../scan` |
| `pickup_pending` | `completed` | Confirmation marchand + client |
| `pickup_pending` | `completed` | Force marchand si timeout client |

## Hors périmètre Sprint 4

- Push mobile / SMS.
- Mercure temps réel (post-MVP).
- Réouverture d'une session de retrait expirée (admin).
- Paiement en ligne.

## Définition de fini globale

Le Sprint 4 est cohérent lorsque :

1. Un client peut afficher son QR code de retrait pour une commande `ready`.
2. Un marchand peut scanner le QR et passer la commande en `pickup_pending`.
3. Les deux parties peuvent confirmer la remise pour passer en `completed`.
4. Les notifications sont créées aux transitions clés et lisibles via l'API.
5. L'historique des transitions est accessible et horodaté.
6. Un QR code déjà utilisé est refusé.
7. Un QR code expiré affiche un message explicite.
