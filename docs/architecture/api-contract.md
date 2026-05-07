# Contrat API — MVP

## Objectif

Définir les premières routes nécessaires au MVP Click & Collect Supérette Tunisie.

Ce contrat reste fonctionnel. Les chemins, payloads et codes peuvent être ajustés pendant l'implémentation.

## Conventions

- Format : JSON.
- Devise : TND.
- Identifiants : UUID.
- Dates : ISO 8601.
- Langues prévues : français et arabe.

## Stores

### Lire une supérette depuis un QR code

```http
GET /api/stores/by-qr/{qrCodeToken}
```

Réponse :

```json
{
  "id": "store_uuid",
  "name": "Supérette Exemple",
  "address": "Tunis",
  "city": "Tunis",
  "is_active": true
}
```

### Lire le catalogue visible d'une supérette

```http
GET /api/stores/{storeId}/catalog?query=lait&category=lait-produits-laitiers
```

Réponse :

```json
{
  "items": [
    {
      "id": "merchant_product_uuid",
      "product_reference_id": "product_ref_uuid",
      "name_fr": "Lait demi-écrémé",
      "brand": "Vitalait",
      "volume": 1,
      "unit": "litre",
      "price_tnd": "1.650",
      "is_available": true
    }
  ]
}
```

## Kadhia / Orders côté client

### Créer une Kadhia

```http
POST /api/stores/{storeId}/orders
```

Payload :

```json
{
  "customer_name": "Client",
  "customer_phone": "+21600000000"
}
```

### Ajouter une ligne

```http
POST /api/orders/{orderId}/items
```

Payload :

```json
{
  "merchant_product_id": "merchant_product_uuid",
  "quantity": 2
}
```

### Modifier une ligne

```http
PATCH /api/orders/{orderId}/items/{itemId}
```

Payload :

```json
{
  "quantity": 3
}
```

### Supprimer une ligne

```http
DELETE /api/orders/{orderId}/items/{itemId}
```

### Choisir un créneau

```http
PATCH /api/orders/{orderId}/pickup-slot
```

Payload :

```json
{
  "pickup_slot_id": "pickup_slot_uuid"
}
```

### Soumettre la commande

```http
POST /api/orders/{orderId}/submit
```

Réponse :

```json
{
  "id": "order_uuid",
  "status": "submitted",
  "total_amount_tnd": "23.450"
}
```

### Suivre une commande

```http
GET /api/orders/{orderId}
```

## Marchand

### Rechercher dans le référentiel global

```http
GET /api/merchant/product-references?query=vitalait%201l
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
  "is_visible": true
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
  "is_visible": true
}
```

### Lister les commandes marchand

```http
GET /api/merchant/stores/{storeId}/orders?status=submitted
```

### Accepter une commande

```http
POST /api/merchant/orders/{orderId}/accept
```

### Refuser une commande

```http
POST /api/merchant/orders/{orderId}/reject
```

Payload :

```json
{
  "reason": "Produit indisponible"
}
```

### Passer en préparation

```http
POST /api/merchant/orders/{orderId}/start-preparing
```

### Marquer comme prête

```http
POST /api/merchant/orders/{orderId}/mark-ready
```

### Valider le retrait

```http
POST /api/merchant/orders/{orderId}/pickup/validate
```

Payload :

```json
{
  "pickup_token": "qr_token_value"
}
```

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
  "volume": 1,
  "unit": "litre",
  "category_id": "category_uuid",
  "variant_fr": "Demi-écrémé",
  "barcode": null
}
```

## Administration

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

## Thèmes

### Lire le thème actif d'une supérette

```http
GET /api/stores/{storeId}/theme
```

Public, sans authentification. Retourne le `ShopTheme` de la supérette si présent, sinon le `PlatformTheme` global.

Réponse :

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

Réservé à `ROLE_MERCHANT`, propriétaire de la supérette. Retourne le `ShopTheme` de la supérette si présent, sinon le `PlatformTheme` global, avec le payload métier utilisé par le backoffice marchand.

Réponse métier :

```json
{
  "primary_color": "#1B6CA8",
  "secondary_color": "#F0A500",
  "accent_color": "#E63946",
  "text_color": "#1A1A1A",
  "background_color": "#FFFFFF",
  "font_family": "inter",
  "base_font_size": 16,
  "warnings": []
}
```

### Créer ou modifier le thème d'une supérette côté marchand

```http
PUT /api/merchant/stores/{storeId}/theme
```

Réservé à `ROLE_MERCHANT`, propriétaire de la supérette.

Ce `PUT` est un upsert : il crée un `ShopTheme` si la supérette n'en a pas encore, et met à jour le `ShopTheme` existant sinon.

Payload :

```json
{
  "primary_color": "#1B6CA8",
  "secondary_color": "#F0A500",
  "accent_color": "#E63946",
  "text_color": "#1A1A1A",
  "background_color": "#FFFFFF",
  "font_family": "inter",
  "base_font_size": 16
}
```

### Supprimer le thème d'une supérette

Post-MVP ou à arbitrer plus tard. Aucune route contractuelle de suppression du thème marchand n'est exposée dans le MVP actuel.

### Lire le thème global (admin)

```http
GET /api/admin/theme
```

Réservé à `ROLE_ADMIN`.

Réponse métier :

```json
{
  "primary_color": "#1B6CA8",
  "secondary_color": "#F0A500",
  "accent_color": "#E63946",
  "text_color": "#1A1A1A",
  "background_color": "#FFFFFF",
  "font_family": "inter",
  "base_font_size": 16,
  "warnings": []
}
```

### Modifier le thème global (admin)

```http
PUT /api/admin/theme
```

Réservé à `ROLE_ADMIN`. Même payload que les endpoints marchand.

## Codes d'erreur MVP

| Code | Signification |
|---|---|
| `STORE_NOT_FOUND` | Supérette introuvable. |
| `STORE_DISABLED` | Supérette désactivée. |
| `PRODUCT_NOT_AVAILABLE` | Produit indisponible. |
| `ORDER_INVALID_STATUS` | Transition de statut interdite. |
| `PICKUP_TOKEN_INVALID` | QR code de retrait invalide. |
| `PICKUP_TOKEN_ALREADY_USED` | QR code déjà utilisé. |
| `PRODUCT_REFERENCE_DUPLICATE` | Produit de référence probablement déjà existant. |
