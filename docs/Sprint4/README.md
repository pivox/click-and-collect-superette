# Sprint 4 — Retrait sécurisé

## Statut

**Backend terminé.**

Sprint 4 finalise le retrait physique en supérette : QR code de retrait, scan marchand, passage en `pickup_pending`, double validation client + marchand, finalisation `completed`, force completion contrôlée et notifications in-app.

Cette clôture ne couvre pas les notifications push, SMS, email, Mercure/WebSocket, ni la réouverture admin d'une session expirée.

## Objectif du sprint

Permettre à un client de récupérer sa Kadhia avec un retrait sécurisé :

1. la commande passe en `ready` ;
2. une `PickupSession` est créée automatiquement ;
3. le client récupère le token/QR de retrait ;
4. le marchand scanne le QR ;
5. la commande passe en `pickup_pending` ;
6. le marchand confirme la remise ;
7. le client confirme la réception ;
8. la commande passe en `completed`.

Si le client ne confirme pas après scan et confirmation marchand, le marchand peut forcer la complétion après 5 minutes avec une note obligatoire.

## PRs livrées

| PR | Sujet | Statut |
|---|---|---|
| #69 | Socle `PickupSession` + `Notification`, repositories, création de session au passage `ready` | Livré |
| #70 | Lecture QR côté client | Livré |
| #76 | Scan marchand du QR | Livré |
| #77 | Confirmation marchand | Livré |
| #81 | Confirmation client + finalisation `completed` | Livré |
| #82 | Force completion marchand | Livré |
| #83 | Notifications client/marchand | Livré |
| #84 | Suivi statut commande client | Livré |
| #85 | Rappel retrait 1h avant créneau | Livré (planification Messenger) |
| S4-007 | Commande `app:orders:send-pickup-reminders` + contenu enrichi (nom supérette, heure créneau) | Livré |

## User stories concernées

| US | Sujet | Statut backend |
|---|---|---|
| US-025 | Afficher le QR code de retrait côté client | Livré |
| US-007 | Double validation du retrait | Livré |
| US-026 | Suivre le statut de sa commande | Livré |
| US-038 | Notifications client | Livré |
| US-039 | Notifications marchand | Livré |
| US-064 | Rappel de retrait avant expiration du créneau | Livré : commande `app:orders:send-pickup-reminders`, contenu enrichi (nom supérette + heure) |

## Endpoints livrés

### Client — QR code de retrait

```http
GET /api/me/orders/{orderId}/pickup-session
```

Réponse :

```json
{
  "id": "<pickup-session-uuid>",
  "token": "<uuid>",
  "expires_at": "2026-05-15T14:00:00+01:00",
  "is_used": false,
  "is_expired": false,
  "qr_payload": "<uuid>"
}
```

Règles :

- réservé au client propriétaire de la commande ;
- la commande doit être `ready` ;
- le token exposé est le payload opaque du QR de retrait ;
- le token est un UUID et n'est exposé qu'au client propriétaire via cette route.

### Marchand — scan QR

```http
POST /api/merchant/pickup-sessions/scan
```

Payload :

```json
{
  "token": "<pickup-session-token-uuid>"
}
```

Règles :

- réservé à `ROLE_MERCHANT` ;
- vérifie que le marchand est propriétaire de la supérette liée à la commande ;
- refuse un token inconnu, expiré ou déjà utilisé ;
- vérifie que la commande est en `ready` ;
- renseigne `scannedAt` ;
- passe la commande de `ready` à `pickup_pending` ;
- écrit un `OrderStatusLog` en statut `pickup_pending` ;
- un scan répété reste idempotent tant que la session est scannée et la commande encore `pickup_pending`.

### Marchand — confirmation remise

```http
PATCH /api/merchant/pickup-sessions/{id}/confirm
```

Règles :

- réservé au marchand propriétaire de la supérette ;
- la session doit exister, être scannée et non utilisée ;
- la commande doit être `pickup_pending` ;
- renseigne `merchantConfirmedAt` ;
- la confirmation marchand seule ne finalise pas la commande ;
- si le client avait déjà confirmé, la commande passe en `completed`.

### Client — confirmation réception

```http
PATCH /api/me/pickup-sessions/{id}/confirm
```

Règles :

- réservé au client propriétaire de la commande ;
- la session doit être scannée et non utilisée ;
- la commande doit être `pickup_pending` ;
- renseigne `customerConfirmedAt` ;
- si le marchand avait déjà confirmé, la commande passe en `completed`.

### Marchand — force completion

```http
PATCH /api/merchant/pickup-sessions/{id}/force-complete
```

Payload :

```json
{
  "note": "Client parti sans confirmer sur son téléphone."
}
```

Règles :

- réservé au marchand propriétaire de la supérette ;
- scan déjà effectué ;
- commande en `pickup_pending` ;
- confirmation marchand déjà faite ;
- client non confirmé ;
- délai minimal de 5 minutes après scan ;
- note obligatoire ;
- finalise la commande en `completed` ;
- marque la session utilisée et `forceCompletedByMerchant=true`.

### Client — notifications

```http
GET   /api/me/notifications?page=1&unread=true
PATCH /api/me/notifications/{id}/read
PATCH /api/me/notifications/read-all
```

### Marchand — notifications

```http
GET   /api/merchant/notifications?page=1&unread=true
PATCH /api/merchant/notifications/{id}/read
PATCH /api/merchant/notifications/read-all
```

Règles notifications :

- notifications in-app persistées en base ;
- pagination simple par `page` ;
- filtre optionnel `unread=true` ;
- marquage unitaire ou global comme lu ;
- pas de push mobile, SMS, email, Mercure ou WebSocket dans le MVP backend actuel.

### Client — suivi statut commande

```http
GET /api/me/orders/{orderId}/status
```

Règles :

- réservé au client propriétaire de la commande ;
- retourne le statut courant, les libellés FR/AR, `updated_at` et l'état synthétique de la `PickupSession` ;
- prévu pour un polling frontend simple.

## Règles de transition Sprint 4

| De | Vers | Déclencheur | Log |
|---|---|---|---|
| `preparing` | `ready` | `POST /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready` | `ready` |
| `ready` | `pickup_pending` | `POST /api/merchant/pickup-sessions/scan` | `pickup_pending` |
| `pickup_pending` | `completed` | Confirmation client + marchand | `completed` |
| `pickup_pending` | `completed` | Force completion marchand | `completed` avec note |

`OrderStatusLog` trace les transitions clés `ready`, `pickup_pending` et `completed`.

## Modèle de données utilisé

### `PickupSession`

- `id` UUID ;
- `order` relation unique ;
- `token` UUID opaque unique ;
- `generatedAt` ;
- `expiresAt` ;
- `scannedAt` nullable ;
- `merchantConfirmedAt` nullable ;
- `customerConfirmedAt` nullable ;
- `used` ;
- `forceCompletedByMerchant` ;
- `forceNote` nullable ;
- `createdAt`.

Le token a une durée de validité de 24 heures. L'expiration bloque le scan initial. Après scan et passage en `pickup_pending`, l'expiration ne bloque plus les confirmations afin de permettre de terminer un retrait déjà engagé.

### `Notification`

- `id` UUID ;
- `user` ;
- `order` nullable ;
- `titleFr`, `titleAr` ;
- `bodyFr`, `bodyAr` ;
- `type` nullable ;
- `read` ;
- `createdAt`.

Le type `pickup_reminder` est unique par commande afin d'éviter les doublons de rappel.

## Notifications livrées

Notifications client :

- commande acceptée ;
- commande refusée ;
- commande partiellement acceptée ;
- commande en préparation ;
- commande prête ;
- rappel de retrait avec contenu générique ;
- commande retirée.

Notifications marchand :

- nouvelle commande soumise ;
- commande annulée par le client ;
- retrait finalisé.

## Rappel de retrait 1h

Deux mécanismes coexistent :

### Planification Messenger (PR #85)

Le rappel est planifié lors du passage en `ready` :

- si le créneau démarre dans plus d'une heure, un message Messenger est dispatché avec `DelayStamp` ;
- si le créneau démarre dans moins d'une heure, le message est dispatché immédiatement ;
- le handler crée la notification uniquement si la commande est toujours `ready`, si la session n'est pas utilisée/scannée et si le créneau n'a pas commencé.

### Commande de scan périodique (S4-007)

La commande `app:orders:send-pickup-reminders` peut être appelée par un cron toutes les 5–10 minutes :

- détecte les commandes en `ready` ou `pickup_pending` avec un créneau démarrant dans [now+55min, now+65min] ;
- crée la notification in-app si elle n'existe pas déjà (`type = pickup_reminder`) ;
- le contenu inclut le nom de la supérette et l'heure du créneau (Africa/Tunis) ;
- la déduplication repose sur la contrainte unique `(order_id, type)` en base ;
- aucune migration requise — réutilise le système de notification existant.

Limites :

- le transport Messenger en production nécessite un transport async persistant et un worker actif ;
- la confirmation marchand conserve encore un garde d'expiration côté processor ;
- les confirmations simultanées ne sont pas sérialisées par un verrou pessimiste dédié.

## Vérifications connues

Résultats issus des PRs Sprint 4 précédentes :

- PR #82 : suite complète 558 tests, 2146 assertions ;
- PR #83 : suite complète 586 tests, 2243 assertions ;
- PR #85 : suite complète 618 tests, 2367 assertions ;
- S4-007 : suite complète 803 tests, 3366 assertions ;
- PHPStan OK ;
- PHP CS Fixer dry-run OK ;
- `composer validate --no-check-publish` OK ;
- Doctrine schema validate : mapping OK, validation DB locale bloquée par le rôle PostgreSQL `app` inexistant.

## Limites connues

- Notifications in-app uniquement.
- Pas de push mobile.
- Pas de SMS.
- Pas d'email.
- Pas de Mercure/WebSocket.
- Pas de réouverture admin d'une session expirée dans le MVP.
- Le rappel 1h dépend d'un transport Messenger async persistant et d'un worker actif en production.
- Les confirmations simultanées ne sont pas sérialisées par un verrou pessimiste dédié ; le risque de concurrence reste à traiter si le trafic de retrait augmente.
- La confirmation marchand conserve encore un contrôle d'expiration après scan, contrairement à la confirmation client et à la force completion.
- Le contenu du rappel inclut maintenant le nom de la supérette et l'heure du créneau. Le numéro de commande n'est pas encore disponible (champ absent de l'entité Order).

## Hors périmètre Sprint 4

- Paiement en ligne.
- Livraison.
- Notifications externes SMS/email/push.
- Realtime Mercure/WebSocket.
- Réouverture admin de session expirée.
- Créneaux récurrents et fermetures exceptionnelles.
- Administration minimale des supérettes et marchands.

## Critère de sortie

Sprint 4 est terminé côté backend lorsque :

1. une commande `ready` génère une `PickupSession` ;
2. le client peut récupérer le QR de retrait ;
3. le marchand peut scanner le QR et passer la commande en `pickup_pending` ;
4. client et marchand peuvent confirmer le retrait ;
5. la double validation finalise la commande en `completed` ;
6. le marchand peut forcer la complétion après 5 minutes si le client ne confirme pas ;
7. les notifications in-app sont créées et consultables ;
8. le client peut suivre le statut de sa commande ;
9. le rappel de retrait 1h est planifié via Messenger ;
10. les limites techniques sont explicites.

## Suite recommandée

1. **Sprint 3b** — maturité opérationnelle marchand : créneaux récurrents, fermetures exceptionnelles, délais automatiques, historique complet.
2. **Sprint 5** — administration minimale : création supérettes, comptes marchands, référentiel produit admin.
3. **Sprint 7** — production/localisation : worker Messenger supervisé, observabilité, FR/AR/RTL, accessibilité, politique de rétention.
