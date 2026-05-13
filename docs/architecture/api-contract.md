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

Statut : **à implémenter**.

```http
POST /api/auth/register/customer
```

Payload cible :

```json
{
  "email": "client@example.com",
  "password": "password",
  "name": "Client Test",
  "phone": "+21600000000"
}
```

Règles :

- crée un utilisateur actif ;
- rôle attribué automatiquement : `ROLE_CUSTOMER` ;
- email unique ;
- ne permet jamais de choisir un rôle.

### Profil client

Statut : **à implémenter**.

```http
GET   /api/me/profile
PATCH /api/me/profile
```

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

```http
GET /api/merchant/stores/{storeId}/orders
GET /api/merchant/stores/{storeId}/orders?status=submitted
GET /api/merchant/stores/{storeId}/orders?page=1&limit=20
```

### Accepter une commande

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/accept
```

### Refuser une commande

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/reject
```

Payload :

```json
{
  "reason": "Produit indisponible"
}
```

### Passer en préparation

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation
```

### Marquer comme prête

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
```

### Accepter partiellement une commande

Statut : **à implémenter**.

```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/partial-accept
```

Objectif : permettre au marchand d'accepter certaines lignes, de repasser la Kadhia en `draft` avec les lignes acceptées, puis de laisser le client re-soumettre.

---

## Créneaux marchand

Statut : **à implémenter / vérifier**.

```http
GET   /api/merchant/stores/{storeId}/pickup-slots
POST  /api/merchant/stores/{storeId}/pickup-slots
PATCH /api/merchant/stores/{storeId}/pickup-slots/{slotId}
```

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

Statut : **à implémenter**.

### Générer / lire la session de retrait côté client

```http
GET /api/me/orders/{orderId}/pickup-session
```

### Scanner un QR code de retrait côté marchand

```http
POST /api/merchant/pickup-sessions/scan
```

Payload :

```json
{
  "token": "pickup_token_value"
}
```

### Confirmation marchand

```http
PATCH /api/merchant/pickup-sessions/{id}/confirm
```

### Confirmation client

```http
PATCH /api/me/pickup-sessions/{id}/confirm
```

Règles :

- token opaque ;
- unique par commande ;
- usage unique ;
- expiration à cadrer, cible 24h après passage en `ready` ;
- passage `ready` → `pickup_pending` après scan ;
- passage `pickup_pending` → `completed` après double validation.

---

## Notifications

Statut : **à cadrer / implémenter**.

MVP recommandé : notifications persistées en base, lecture par API, sans push/SMS obligatoire au départ.

```http
GET   /api/me/notifications
PATCH /api/me/notifications/{id}/read
GET   /api/merchant/notifications
PATCH /api/merchant/notifications/{id}/read
```

Événements minimaux :

- commande soumise : notifier marchand ;
- commande acceptée : notifier client ;
- commande refusée : notifier client ;
- commande prête : notifier client ;
- retrait finalisé : notifier client et marchand.

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
| `PICKUP_TOKEN_INVALID` | QR code de retrait invalide. |
| `PICKUP_TOKEN_ALREADY_USED` | QR code déjà utilisé. |
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
