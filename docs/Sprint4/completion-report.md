# Sprint 4 — Rapport de clôture

## Objectif du sprint

Sprint 4 sécurise la remise physique de la Kadhia en supérette. Il ajoute une `PickupSession` unique par commande prête, un token QR opaque, le scan marchand, la double validation client + marchand, la force completion contrôlée et les notifications in-app.

## PRs couvertes

| PR | Livraison |
|---|---|
| #69 | Socle `PickupSession` + `Notification`, repositories et création automatique de session au passage `ready` |
| #70 | `GET /api/me/orders/{orderId}/pickup-session` |
| #76 | `POST /api/merchant/pickup-sessions/scan` |
| #77 | `PATCH /api/merchant/pickup-sessions/{id}/confirm` |
| #81 | `PATCH /api/me/pickup-sessions/{id}/confirm` et finalisation `completed` |
| #82 | `PATCH /api/merchant/pickup-sessions/{id}/force-complete` |
| #83 | Notifications client et marchand |
| #84 | `GET /api/me/orders/{orderId}/status` |
| #85 | Rappel de retrait 1h avant créneau |

## Fonctionnalités livrées

- Génération d'une `PickupSession` au passage de la commande en `ready`.
- Token QR opaque, UUID, unique par session.
- Lecture du QR côté client propriétaire.
- Scan marchand avec contrôle ownership supérette.
- Transition `ready` → `pickup_pending`.
- Confirmation marchand.
- Confirmation client.
- Finalisation `completed` par double validation.
- Force completion marchand après 5 minutes avec note obligatoire.
- Notifications in-app client.
- Notifications in-app marchand.
- Suivi statut client par polling.
- Rappel de retrait 1h avant le créneau via Symfony Messenger.
- Historique `OrderStatusLog` sur les transitions clés.

## Endpoints livrés

```http
GET   /api/me/orders/{orderId}/pickup-session
POST  /api/merchant/pickup-sessions/scan
PATCH /api/merchant/pickup-sessions/{id}/confirm
PATCH /api/me/pickup-sessions/{id}/confirm
PATCH /api/merchant/pickup-sessions/{id}/force-complete

GET   /api/me/notifications?page=1&unread=true
PATCH /api/me/notifications/{id}/read
PATCH /api/me/notifications/read-all

GET   /api/merchant/notifications?page=1&unread=true
PATCH /api/merchant/notifications/{id}/read
PATCH /api/merchant/notifications/read-all

GET   /api/me/orders/{orderId}/status
```

Routes de contexte déjà livrées et utilisées par Sprint 4 :

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
GET  /api/me/orders/{orderId}/status-history
GET  /api/merchant/stores/{storeId}/orders/{orderId}/status-history
```

## Règles métier finales

- La session de retrait est créée automatiquement quand une commande passe en `ready`.
- Le QR code encode `PickupSession.token`, pas l'id de commande.
- Le token est opaque et au format UUID.
- Le token n'est exposé qu'au client propriétaire via l'endpoint pickup-session.
- Le marchand ne peut scanner qu'une commande de sa supérette.
- Le scan vérifie token existant, session non utilisée, non expirée et commande `ready`.
- Après scan, la commande passe en `pickup_pending`.
- Après scan, le TTL du token ne bloque plus la confirmation client ni la force completion.
- La confirmation marchand seule ne finalise pas.
- La confirmation client seule ne finalise pas.
- La commande passe en `completed` lorsque client et marchand ont confirmé.
- La force completion exige scan effectué, commande `pickup_pending`, confirmation marchand, client non confirmé, délai de 5 minutes et note obligatoire.
- Une session utilisée ne peut pas être réutilisée.
- Une commande `completed` ne peut plus être scannée ou confirmée.

Limite d'alignement : la confirmation marchand conserve encore un garde d'expiration côté processor après scan.

## Modèle de données utilisé

### `PickupSession`

`PickupSession` porte le cycle de retrait : `order`, `token`, `generatedAt`, `expiresAt`, `scannedAt`, `merchantConfirmedAt`, `customerConfirmedAt`, `used`, `forceCompletedByMerchant`, `forceNote`, `createdAt`.

Contraintes principales :

- une session par commande ;
- un token unique ;
- TTL de 24 heures avant scan ;
- usage unique après double validation ou force completion.

### `Notification`

`Notification` stocke une notification in-app liée à un `User` et optionnellement à une `Order`, avec titre et contenu FR/AR, état de lecture et date de création.

La contrainte unique `(order_id, type)` protège notamment le rappel de retrait contre les doublons.

## Notifications livrées

Client :

- Kadhia acceptée ;
- Kadhia refusée ;
- Kadhia partiellement acceptée ;
- Kadhia en préparation ;
- Kadhia prête ;
- rappel de retrait ;
- Kadhia retirée.

Marchand :

- nouvelle commande ;
- commande annulée ;
- retrait finalisé.

Les notifications sont persistées et consultables par API. Le MVP actuel ne déclenche pas de push mobile, SMS, email ni websocket.

## Rappel 1h

Le rappel est planifié par `PickupReminderScheduler` lors du passage en `ready`.

La planification utilise Symfony Messenger et `DelayStamp` :

- message différé jusqu'à 1h avant le créneau si possible ;
- message immédiat si le créneau est déjà dans moins d'une heure ;
- pas de notification si la commande n'est plus `ready`, si la session a été scannée/utilisée ou si le créneau a commencé.

Limite production : le différé réel nécessite un transport async persistant et un worker actif. En environnement local/test, le transport peut être `sync://` ou `in-memory://`.

## Tests et non-régressions connues

Résultats connus des PRs Sprint 4 :

- PR #82 : suite complète 558 tests, 2146 assertions ;
- PR #83 : suite complète 586 tests, 2243 assertions ;
- PR #85 : suite complète 618 tests, 2367 assertions ;
- PHPStan OK ;
- PHP CS Fixer dry-run OK ;
- `composer validate --no-check-publish` OK ;
- Doctrine schema validate : mapping OK, validation DB locale bloquée par le rôle PostgreSQL `app` inexistant.

Tests Sprint 4 à maintenir :

- `CustomerPickupSessionApiTest` ;
- `MerchantPickupSessionScanApiTest` ;
- `MerchantPickupSessionConfirmApiTest` ;
- `CustomerPickupSessionConfirmApiTest` ;
- `MerchantPickupSessionForceCompleteApiTest` ;
- `CustomerNotificationApiTest` ;
- `MerchantNotificationApiTest` ;
- `CustomerOrderStatusApiTest` ;
- `SendPickupReminderMessageHandlerTest` ;
- `PickupReminderSchedulerTest`.

## Limites techniques

- Pas de notification externe : ni push, ni SMS, ni email.
- Pas de temps réel Mercure/WebSocket.
- Pas de réouverture admin d'une session expirée.
- Le rappel 1h dépend de Messenger et d'un worker actif en production.
- Les confirmations simultanées ne sont pas verrouillées par un `SELECT FOR UPDATE` dédié.
- La confirmation marchand conserve encore un contrôle d'expiration après scan, contrairement à la confirmation client et à la force completion.
- Le polling client reste simple ; pas de streaming d'événements.

## Hors périmètre

- Paiement en ligne.
- Livraison.
- Programme de fidélité.
- Créneaux récurrents.
- Fermetures exceptionnelles.
- Délai de réponse marchand automatisé.
- Administration des marchands/supérettes.
- Exports/statistiques avancées.

## Critère de sortie

Sprint 4 est considéré terminé côté backend/documentation : le retrait sécurisé est opérable de `ready` à `completed`, les notifications in-app sont disponibles, le rappel de retrait est planifié, et les limites d'infrastructure sont explicites.

## Suite recommandée

1. **Sprint 3b — maturité opérationnelle marchand** : créneaux récurrents, fermetures exceptionnelles, délais automatiques, historique complet.
2. **Sprint 5 — administration minimale** : supérettes, marchands, référentiel produit admin, QR store téléchargeable.
3. **Sprint 7 — production/localisation** : transport Messenger persistant, worker supervisé, observabilité, FR/AR/RTL, accessibilité et politique de rétention.
