# Contrat API — MVP

## Objectif

Définir les routes fonctionnelles de référence du MVP Click & Collect Supérette Tunisie.

Ce contrat sert de source de vérité pour les prochains développements backend/frontend. Il doit rester aligné avec les endpoints réellement exposés dans `apps/backend/src/ApiResource`.

## Conventions

- Format : JSON.
- Devise : TND.
- Identifiants : UUID.
- Dates : ISO 8601.
- Langues prévues : français et arabe.
- Authentification : JWT `Authorization: Bearer <token>`.
- Les routes publiques sont explicitement indiquées.
- Les routes `/api/me/*` sont réservées au client connecté (`ROLE_CUSTOMER`).
- Les routes `/api/merchant/*` sont réservées au marchand connecté (`ROLE_MERCHANT`) et propriétaire de la supérette ciblée.
- Les routes `/api/admin/*` sont réservées à l'administrateur (`ROLE_ADMIN`).

---

## Authentification et profil

### Connexion

```http
POST /api/auth/login
```

Payload :

```json
{
  "email": "client@example.com",
  "password": "password"
}
```

Réponse : JWT selon LexikJWTAuthenticationBundle.

### Inscription client

Statut : **livré**.

```http
POST /api/auth/register/customer
```

Payload :

```json
{
  "email": "client@example.com",
  "password": "secret123",
  "first_name": "Haythem",
  "last_name": "Mabrouk",
  "phone": "+21600000000"
}
```

Réponse `201` :

```json
{
  "token": "<jwt>",
  "user": {
    "id": "user-uuid",
    "email": "client@example.com",
    "roles": ["ROLE_CUSTOMER", "ROLE_USER"],
    "first_name": "Haythem",
    "last_name": "Mabrouk",
    "name": "Haythem Mabrouk",
    "phone": "+21600000000"
  }
}
```

Règles :

- crée un utilisateur actif ;
- rôle attribué automatiquement : `ROLE_CUSTOMER` ;
- `ROLE_USER` peut être présent comme rôle Symfony de base ;
- email unique ;
- email trimé et normalisé en minuscules ;
- mot de passe hashé ;
- ne permet jamais de choisir un rôle ;
- ne retourne jamais le mot de passe ni son hash.

### Profil client

Statut : **livré**.

```http
GET   /api/me/profile
PATCH /api/me/profile
```

Réponse `GET 200` :

```json
{
  "id": "user-uuid",
  "email": "client@example.com",
  "roles": ["ROLE_CUSTOMER", "ROLE_USER"],
  "first_name": "Haythem",
  "last_name": "Mabrouk",
  "name": "Haythem Mabrouk",
  "phone": "+21600000000"
}
```

Payload `PATCH` :

```json
{
  "first_name": "Haythem",
  "last_name": "Mabrouk",
  "phone": "+21611111111"
}
```

Champs modifiables :

- `first_name` ;
- `last_name` ;
- `name` pour compatibilité ;
- `phone`.

Champs non modifiables :

- `id` ;
- `email` ;
- `roles` ;
- `password`.

### Réinitialisation de mot de passe

Statut : **livré**.

Routes canoniques :

```http
POST /api/auth/password-reset/request
POST /api/auth/password-reset/confirm
```

Alias documentés :

```http
POST /api/auth/forgot-password
POST /api/auth/reset-password
```

Payload request :

```json
{
  "email": "client@example.com"
}
```

Réponse `202` neutre :

```json
{
  "message": "Si un compte existe pour cet email, un lien de réinitialisation sera envoyé."
}
```

Payload confirm :

```json
{
  "token": "reset-token-opaque",
  "new_password": "newSecret123"
}
```

Réponse confirm : `204 No Content`.

Règles :

- la demande retourne toujours `202`, email connu ou inconnu ;
- le token est créé uniquement pour un compte client existant ;
- le token brut n'est jamais stocké en base ;
- seul le hash du token est persisté ;
- le token expire après 1 heure par défaut ;
- le token est à usage unique ;
- un nouveau reset invalide les anciens tokens actifs du même utilisateur ;
- le nouveau mot de passe est hashé ;
- l'ancien mot de passe ne permet plus la connexion après reset.

---

## Stores publics

### Lire une supérette depuis un QR code

Public.

```http
GET /api/stores/by-qr/{qrCodeToken}
```

Le QR code utilise un token opaque et ne doit pas exposer d'identifiant interne sensible.

### Rechercher une supérette

Public.

```http
GET /api/stores/search?query=amen&city=tunis
```

### Lire une supérette

Public.

```http
GET /api/stores/{storeId}
```

### Lire le catalogue visible d'une supérette

Public.

```http
GET /api/stores/{storeId}/catalog
GET /api/stores/{storeId}/catalog?query=lait
GET /api/stores/{storeId}/catalog?category=lait-produits-laitiers
GET /api/stores/{storeId}/catalog?query=vitalait&category=lait-produits-laitiers
```

Réponse :

```json
{
  "items": [
    {
      "id": "merchant_product_uuid",
      "product_reference_id": "product_ref_uuid",
      "name_fr": "Lait demi-écrémé",
      "name_ar": null,
      "brand": "Vitalait",
      "category": "Lait et produits laitiers",
      "category_ar": null,
      "category_slug": "lait-produits-laitiers",
      "volume": "1",
      "unit": "litre",
      "price_tnd": "1.650",
      "is_available": true
    }
  ]
}
```

### Lire les créneaux disponibles d'une supérette

Public.

```http
GET /api/stores/{storeId}/pickup-slots?from=today&available=true
```

---

## Relation client / supérette

### Lister mes supérettes reconnues

```http
GET /api/me/stores
```

### Enregistrer une visite / reconnaissance store

```http
POST /api/me/stores/{storeId}/visit
```

Payload :

```json
{
  "source": "search"
}
```

Sources autorisées :

```text
qr_code | search | manual | order
```

### Modifier favori

```http
PATCH /api/me/stores/{storeId}/favorite
```

### Masquer une supérette côté client

```http
DELETE /api/me/stores/{storeId}
```

---

## Kadhia côté client

Le modèle actuel est **Kadhia multiple**.

Règles :

- un client peut créer plusieurs Kadhia pour une même supérette ;
- la création est explicite ;
- un `GET` ne crée jamais de Kadhia ;
- les opérations de lignes ciblent toujours une Kadhia précise via `kadhiaId` ;
- une Kadhia `draft` est modifiable ;
- une Kadhia `submitted` est consultable mais non modifiable ;
- les prix et informations produit sont snapshotés au moment de l'ajout.

### Créer une Kadhia

```http
POST /api/me/stores/{storeId}/kadhias
```

Payload :

```json
{
  "notes": "Courses pour samedi matin"
}
```

Réponse : `201` avec le détail de la Kadhia.

### Lister mes Kadhia

```http
GET /api/me/kadhias
GET /api/me/kadhias?status=draft
GET /api/me/kadhias?status=submitted
GET /api/me/kadhias?store_id={storeId}
GET /api/me/kadhias?page=2
```

Réponse :

```json
{
  "items": [],
  "total": 0,
  "page": 1,
  "per_page": 20,
  "pages": 0
}
```

### Consulter une Kadhia

```http
GET /api/me/kadhias/{kadhiaId}
```

### Modifier les notes d'une Kadhia draft

```http
PATCH /api/me/kadhias/{kadhiaId}
```

Payload :

```json
{
  "notes": "Merci de séparer les sacs"
}
```

### Ajouter ou modifier une ligne

```http
PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
```

Payload :

```json
{
  "quantity": 2
}
```

Sémantique : upsert. Si la ligne existe déjà, la quantité est remplacée.

### Supprimer une ligne

```http
DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
```

### Soumettre une Kadhia

```http
POST /api/me/kadhias/{kadhiaId}/submit
```

Payload :

```json
{
  "pickup_slot_id": "pickup_slot_uuid",
  "notes": "Optionnel"
}
```

Réponse : `201` avec la commande créée ou re-soumise.

Règles :

- Kadhia `draft` uniquement ;
- Kadhia non vide ;
- slot du même store ;
- slot futur et disponible ;
- décrémente la capacité du slot ;
- passe la Kadhia en `submitted` ;
- crée une commande en `submitted` ou re-soumet l'ordre existant après acceptation partielle.

---

## Commandes côté client

### Lister mes commandes

```http
GET /api/me/orders
GET /api/me/orders?page=1&limit=20
```

Réponse :

```json
{
  "items": [],
  "total": 0,
  "page": 1,
  "limit": 20
}
```

### Consulter une commande

```http
GET /api/me/orders/{id}
```

### Annuler une commande

Statut : **livré Sprint 3**.

```http
POST /api/me/orders/{orderId}/cancel
```

Règles :

- client connecté uniquement ;
- commande appartenant au client ;
- statut `submitted` uniquement ;
- libère la capacité réservée du créneau ;
- écrit un `OrderStatusLog` en statut `cancelled`.

### Consulter l'historique de statuts d'une commande

Statut : **livré Sprint 3**.

```http
GET /api/me/orders/{orderId}/status-history
```

---

## Référentiel produit et catalogue marchand

### Rechercher dans le référentiel global, dans le contexte d'une supérette marchand

```http
GET /api/merchant/stores/{storeId}/product-references?q=vitalait
GET /api/merchant/stores/{storeId}/product-references?q=vitalait&categorySlug=lait-produits-laitiers
GET /api/merchant/stores/{storeId}/product-references?q=vitalait&brandId={brandId}
GET /api/merchant/stores/{storeId}/product-references?page=1&limit=20
```

### Lister le catalogue marchand

```http
GET /api/merchant/stores/{storeId}/catalog
```

### Ajouter un produit au catalogue marchand

```http
POST /api/merchant/stores/{storeId}/catalog
```

Payload :

```json
{
  "product_reference_id": "product_ref_uuid",
  "price_tnd": "1.650",
  "is_available": true,
  "is_visible": true,
  "merchant_note": null
}
```

### Modifier un produit marchand

```http
PATCH /api/merchant/catalog/{merchantProductId}
```

Payload :

```json
{
  "price_tnd": "1.700",
  "is_available": false,
  "is_visible": true,
  "merchant_note": "Rupture temporaire"
}
```

### Supprimer un produit du catalogue marchand

```http
DELETE /api/merchant/catalog/{merchantProductId}
```

---

## Commandes côté marchand

### Lister les commandes d'une supérette

Statut : **livré Sprint 3**.

```http
GET /api/merchant/stores/{storeId}/orders
GET /api/merchant/stores/{storeId}/orders?status=submitted
GET /api/merchant/stores/{storeId}/orders?page=1&limit=20
```

Règles :

- marchand connecté uniquement ;
- le marchand doit être propriétaire de la supérette ;
- la liste ne doit pas exposer les coordonnées client.

### Consulter le détail d'une commande marchand

Statut : **livré Sprint 3**.

```http
GET /api/merchant/stores/{storeId}/orders/{orderId}
```

Règles :

- marchand connecté uniquement ;
- le marchand doit être propriétaire de la supérette ;
- la commande doit appartenir à la supérette ciblée ;
- les coordonnées client sont exposées uniquement dans ce détail autorisé.

### Accepter une commande

Statut : **livré Sprint 3**.

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/accept
```

Règles :

- statut `submitted` uniquement ;
- écrit un `OrderStatusLog` en statut `accepted`.

### Refuser une commande

Statut : **livré Sprint 3**.

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/reject
```

Payload :

```json
{
  "reason": "Produit indisponible"
}
```

Règles :

- statut `submitted` uniquement ;
- libère la capacité réservée du créneau ;
- écrit un `OrderStatusLog` en statut `rejected`.

### Accepter partiellement une commande

Statut : **livré Sprint 3**.

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
```

Payload :

```json
{
  "rejected_merchant_product_ids": ["<merchantProductId1>"],
  "notes": "Rupture de stock."
}
```

Règles :

- statut `submitted` uniquement ;
- au moins une ligne refusée ;
- impossible de refuser toutes les lignes via cet endpoint : utiliser `reject` ;
- les lignes refusées sont retirées de la Kadhia ;
- la Kadhia repasse en `draft` ;
- la re-soumission client met à jour la commande existante ;
- écrit un `OrderStatusLog` en statut `partially_accepted`.

### Passer en préparation

Statut : **livré Sprint 3**.

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation
```

Règles :

- statut `accepted` uniquement ;
- écrit un `OrderStatusLog` en statut `preparing`.

### Préparer une ligne de commande

Statut : **livré Sprint 3**.

```http
PATCH /api/merchant/stores/{storeId}/orders/{orderId}/lines/{merchantProductId}/preparation
```

Payload :

```json
{
  "prepared": true
}
```

Règles :

- commande en statut `preparing` uniquement ;
- ligne appartenant à la commande ;
- `prepared` peut être `true` ou `false`.

### Marquer comme prête

Statut : **livré Sprint 3**.

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
```

Règles :

- statut `preparing` uniquement ;
- toutes les lignes doivent être `prepared=true` ;
- écrit un `OrderStatusLog` en statut `ready` ;
- crée automatiquement une `PickupSession` (token UUID opaque, TTL 24 h) si elle n'existe pas encore — livré Sprint 4.

### Consulter l'historique de statuts d'une commande marchand

Statut : **livré Sprint 3**.

```http
GET /api/merchant/stores/{storeId}/orders/{orderId}/status-history
```

### Dashboard marchand journalier

Statut : **livré Sprint 3**.

```http
GET /api/merchant/stores/{storeId}/dashboard/today
```

Règles :

- marchand connecté uniquement ;
- le marchand doit être propriétaire de la supérette ;
- retourne les compteurs du jour, les compteurs par statut, les créneaux du jour et les commandes `submitted` urgentes ;
- n'expose pas de données client ni de lignes de commande.

---

## Créneaux marchand

Statut : **livré Sprint 3 pour les créneaux ponctuels**.

```http
GET    /api/merchant/stores/{storeId}/pickup-slots
POST   /api/merchant/stores/{storeId}/pickup-slots
PATCH  /api/merchant/stores/{storeId}/pickup-slots/{slotId}
DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}
```

Règles :

- marchand connecté uniquement ;
- le marchand doit être propriétaire de la supérette ;
- `DELETE` désactive le créneau plutôt que de le supprimer physiquement ;
- les créneaux récurrents et fermetures exceptionnelles restent Sprint 3b.

---

## Produits proposés

### Proposer un produit manquant

```http
POST /api/merchant/product-proposals
```

Payload :

```json
{
  "store_id": "store_uuid",
  "brand_name": "Vitalait",
  "name_fr": "Lait demi-écrémé",
  "name_ar": null,
  "volume": "1",
  "unit": "litre",
  "category_id": "category_uuid",
  "variant_fr": "Demi-écrémé",
  "barcode": null
}
```

---

## Administration

### Thème global

```http
GET /api/admin/theme
PUT /api/admin/theme
```

### Marchands

Statut : **à implémenter**.

```http
GET   /api/admin/merchants
POST  /api/admin/merchants
PATCH /api/admin/merchants/{merchantId}/activate
PATCH /api/admin/merchants/{merchantId}/suspend
```

### Supérettes

Statut : **à implémenter**.

```http
GET   /api/admin/stores
POST  /api/admin/stores
PATCH /api/admin/stores/{storeId}
PATCH /api/admin/stores/{storeId}/owner
```

### Régénérer le QR code d'une supérette

Statut : **à implémenter**.

```http
POST /api/admin/stores/{storeId}/regenerate-qr
```

Règles :

- admin connecté uniquement (`ROLE_ADMIN`) ;
- la supérette doit exister ;
- génère un nouveau `qrCodeToken` opaque (UUID v4) pour la supérette ;
- l'ancien token est immédiatement invalidé — tout lien ou QR physique imprimé avec l'ancien token ne fonctionnera plus ;
- retourne le nouveau token afin que l'admin puisse régénérer le QR physique.

Cas d'usage : QR code compromis, QR physique endommagé ou changement de supérette propriétaire.

### Valider une proposition produit

```http
POST /api/admin/product-proposals/{proposalId}/approve
```

### Refuser une proposition produit

```http
POST /api/admin/product-proposals/{proposalId}/reject
```

Payload :

```json
{
  "reason": "Produit déjà existant"
}
```

### Fusionner une proposition avec un produit existant

```http
POST /api/admin/product-proposals/{proposalId}/merge
```

Payload :

```json
{
  "product_reference_id": "existing_product_ref_uuid"
}
```

---

## Thèmes

### Lire le thème actif d'une supérette

Public.

```http
GET /api/stores/{storeId}/theme
```

Réponse : variables CSS.

```json
{
  "--color-primary": "#1B6CA8",
  "--color-secondary": "#F0A500",
  "--color-accent": "#E63946",
  "--color-text": "#1A1A1A",
  "--color-background": "#FFFFFF",
  "--font-family": "Inter",
  "--font-size-base": "16px"
}
```

Cache : `Cache-Control: public, max-age=300`.

### Lire le thème d'une supérette côté marchand

```http
GET /api/merchant/stores/{storeId}/theme
```

### Créer ou modifier le thème d'une supérette côté marchand

```http
PUT /api/merchant/stores/{storeId}/theme
```

### Lire et modifier le thème global admin

```http
GET /api/admin/theme
PUT /api/admin/theme
```

---

## Retrait sécurisé

Statut : **livré Sprint 4**.

### Lire la session de retrait côté client

```http
GET /api/me/orders/{orderId}/pickup-session
```

Réponse `200` :

```json
{
  "id": "pickup-session-uuid",
  "token": "pickup-token-uuid",
  "expires_at": "2026-05-15T14:00:00+01:00",
  "is_used": false,
  "is_expired": false,
  "qr_payload": "pickup-token-uuid"
}
```

Règles :

- client connecté uniquement ;
- commande appartenant au client ;
- commande en statut `ready` ;
- le QR code encode `PickupSession.token` ;
- le token est opaque, UUID, et n'est exposé que via cette route client.

### Scanner un QR code de retrait côté marchand

```http
POST /api/merchant/pickup-sessions/scan
```

Payload :

```json
{
  "token": "pickup-session-token-uuid"
}
```

Réponse `200` :

```json
{
  "id": "pickup-session-uuid",
  "order_id": "order-uuid",
  "store_id": "store-uuid",
  "order_number": null,
  "status": "pickup_pending",
  "scanned_at": "2026-05-15T13:00:00+00:00",
  "customer": {
    "first_name": "Haythem",
    "last_name": "Mabrouk",
    "phone": "+21600000000"
  },
  "lines": [
    {
      "merchant_product_id": "merchant-product-uuid",
      "name": "Lait Vitalait 1L",
      "quantity": 2,
      "unit_price_tnd": "2.800"
    }
  ]
}
```

Règles :

- marchand connecté uniquement ;
- le marchand doit être propriétaire de la supérette liée à la commande ;
- token existant, non expiré et non utilisé ;
- commande en `ready` ;
- passe la commande en `pickup_pending` ;
- écrit un `OrderStatusLog` `pickup_pending` ;
- un scan répété est idempotent si la session est déjà scannée et la commande encore `pickup_pending`.

### Confirmation marchand

```http
PATCH /api/merchant/pickup-sessions/{id}/confirm
```

Réponse `200` :

```json
{
  "id": "pickup-session-uuid",
  "order_id": "order-uuid",
  "order_status": "pickup_pending",
  "scanned_at": "2026-05-15T13:00:00+00:00",
  "merchant_confirmed_at": "2026-05-15T13:02:00+00:00",
  "customer_confirmed_at": null,
  "is_used": false,
  "is_completed": false
}
```

### Confirmation client

```http
PATCH /api/me/pickup-sessions/{id}/confirm
```

Même format de réponse que la confirmation marchand.

### Force completion marchand

```http
PATCH /api/merchant/pickup-sessions/{id}/force-complete
```

Payload :

```json
{
  "note": "Client parti sans confirmer sur son téléphone."
}
```

Réponse `200` :

```json
{
  "id": "pickup-session-uuid",
  "order_id": "order-uuid",
  "order_status": "completed",
  "scanned_at": "2026-05-15T13:00:00+00:00",
  "merchant_confirmed_at": "2026-05-15T13:02:00+00:00",
  "customer_confirmed_at": null,
  "is_used": true,
  "is_completed": true,
  "force_completed_by_merchant": true,
  "force_note": "Client parti sans confirmer sur son téléphone."
}
```

Règles :

- `PickupSession.token` est opaque et unique par commande ;
- usage unique après double validation ou force completion ;
- expiration 24h après passage en `ready` ;
- le scan bloque un token expiré ;
- après scan, la confirmation client et la force completion ne bloquent plus sur le TTL ;
- limite actuelle : la confirmation marchand conserve encore un garde d'expiration ;
- passage `ready` → `pickup_pending` après scan marchand ;
- passage `pickup_pending` → `completed` après double validation client + marchand ;
- force completion possible après 5 minutes si le marchand a confirmé, le client n'a pas confirmé et une note est fournie ;
- pas de réouverture admin d'une session expirée dans le MVP.

---

## Notifications

Statut : **livré Sprint 4**.

MVP recommandé : notifications persistées en base, lecture par API, sans push/SMS obligatoire au départ.

```http
GET   /api/me/notifications?page=1&unread=true
PATCH /api/me/notifications/{id}/read
PATCH /api/me/notifications/read-all

GET   /api/merchant/notifications?page=1&unread=true
PATCH /api/merchant/notifications/{id}/read
PATCH /api/merchant/notifications/read-all
```

Événements minimaux :

- commande soumise : notifier marchand ;
- commande acceptée : notifier client ;
- commande refusée : notifier client ;
- commande acceptée partiellement : notifier client ;
- commande en préparation : notifier client ;
- commande prête : notifier client ;
- rappel de retrait 1h avant créneau si commande `ready` ;
- retrait finalisé : notifier client et marchand.

Les notifications sont in-app uniquement. Le MVP actuel n'inclut pas push mobile, SMS, email, Mercure ou WebSocket.

---

## Suivi statut client

Statut : **livré Sprint 4**.

```http
GET /api/me/orders/{orderId}/status
```

Réponse `200` :

```json
{
  "order_id": "order-uuid",
  "status": "pickup_pending",
  "status_label_fr": "Retrait en cours",
  "status_label_ar": "الاستلام قيد التنفيذ",
  "updated_at": "2026-05-15T13:00:00+00:00",
  "pickup_session": {
    "exists": true,
    "is_scanned": true,
    "merchant_confirmed": true,
    "customer_confirmed": false,
    "is_used": false,
    "force_completed_by_merchant": false
  }
}
```

Règles :

- client connecté uniquement ;
- commande appartenant au client ;
- ne retourne pas le token QR ;
- prévu pour un polling frontend simple.

---

## Codes d'erreur MVP

| Code | Signification |
| --- | --- |
| `AUTH_EMAIL_ALREADY_USED` | Email déjà utilisé. |
| `AUTH_INVALID_CREDENTIALS` | Identifiants invalides. |
| `STORE_NOT_FOUND` | Supérette introuvable. |
| `STORE_DISABLED` | Supérette désactivée. |
| `CUSTOMER_STORE_NOT_FOUND` | Relation client/store introuvable. |
| `PRODUCT_NOT_AVAILABLE` | Produit indisponible. |
| `PRODUCT_UNAVAILABLE` | Produit indisponible ou invisible à la soumission. |
| `KADHIA_NOT_FOUND` | Kadhia introuvable ou non accessible. |
| `KADHIA_NOT_EDITABLE` | Kadhia non modifiable car non draft. |
| `KADHIA_EMPTY` | Kadhia vide à la soumission. |
| `PICKUP_SLOT_NOT_FOUND` | Créneau introuvable ou d'un autre store. |
| `PICKUP_SLOT_FULL` | Créneau complet. |
| `PICKUP_SLOT_EXPIRED` | Créneau expiré ou déjà commencé. |
| `ORDER_INVALID_STATUS` | Transition de statut interdite. |
| `ORDER_NOT_SUBMITTED` | La commande n'est pas dans un statut soumis attendu. |
| `ORDER_NOT_PREPARING` | La commande n'est pas en préparation. |
| `ORDER_LINE_NOT_FOUND` | Ligne de commande introuvable. |
| `PICKUP_TOKEN_INVALID` | QR code de retrait invalide. |
| `PICKUP_TOKEN_ALREADY_USED` | QR code déjà utilisé. |
| `PICKUP_TOKEN_EXPIRED` | QR code expiré. |
| `PICKUP_SESSION_NOT_FOUND` | Session de retrait introuvable. |
| `PICKUP_SESSION_NOT_SCANNED` | Session de retrait pas encore scannée. |
| `PICKUP_SESSION_ALREADY_USED` | Session de retrait déjà utilisée. |
| `PICKUP_SESSION_EXPIRED` | Session de retrait expirée. |
| `ORDER_NOT_READY` | La commande n'est pas prête au retrait. |
| `ORDER_NOT_PICKUP_PENDING` | La commande n'est pas en retrait en cours. |
| `ORDER_ALREADY_COMPLETED` | La commande est déjà finalisée. |
| `PICKUP_SESSION_NOT_MERCHANT_CONFIRMED` | Le marchand n'a pas encore confirmé le retrait. |
| `PICKUP_SESSION_ALREADY_CUSTOMER_CONFIRMED` | Le client a déjà confirmé le retrait. |
| `PICKUP_FORCE_COMPLETION_TOO_EARLY` | Le délai de 5 minutes n'est pas encore atteint. |
| `PRODUCT_REFERENCE_DUPLICATE` | Produit de référence probablement déjà existant. |

---

## Endpoints obsolètes à ne plus utiliser pour la Kadhia

Les routes suivantes appartiennent à l'ancien modèle et ne doivent plus être utilisées dans les nouvelles PR :

```http
POST   /api/stores/{storeId}/orders
POST   /api/orders/{orderId}/items
PATCH  /api/orders/{orderId}/items/{itemId}
DELETE /api/orders/{orderId}/items/{itemId}
PATCH  /api/orders/{orderId}/pickup-slot
POST   /api/orders/{orderId}/submit
```

Le modèle valide est `Kadhia -> submit -> Order`.
