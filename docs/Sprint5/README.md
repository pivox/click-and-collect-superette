# Sprint 5 — Administration minimale

## Objectif du sprint

Sprint 5 donne à l'opérateur plateforme les outils nécessaires pour onboarder des supérettes, créer des comptes marchands, gérer le référentiel produit et maintenir les données structurantes (marques, catégories).

C'est le sprint qui rend la plateforme opérable sans accès direct à la base de données.

## État actuel

Sprint 5 est en cours.

Livré :

- S5-001 — lecture admin des comptes marchands, PR #103 ;
- S5-002 — lecture admin des supérettes, PR #104 ;
- S5-003 — mutations admin des supérettes (création, mise à jour), PR #107 ;
- S5-003 — mutations admin des supérettes (activation, désactivation), PR #108 ;
- S5-004 — mutations admin des comptes marchands (création, mise à jour, suspension, activation), PR #106 ;
- S5-005 — QR admin supérette (lecture contrat QR, régénération du token).

Endpoints S5-003 livrés (PR #107 + PR #108) :

- `POST   /api/admin/stores` — création d'une supérette, retour 201 (slug auto-généré, qr_code_token opaque) ;
- `PATCH  /api/admin/stores/{storeId}` — mise à jour partielle (name, address, city, phone, owner_id, is_active) ;
- `PATCH  /api/admin/stores/{storeId}/activate` — activation (is_active → true) ;
- `PATCH  /api/admin/stores/{storeId}/deactivate` — désactivation (is_active → false).

Endpoints S5-005 livrés :

- `GET    /api/admin/stores/{storeId}/qr-code` — lecture du contrat QR scannable ;
- `POST   /api/admin/stores/{storeId}/regenerate-qr` — régénération du token QR opaque.

Endpoints S5-004 livrés :

- `POST   /api/admin/merchants` — création d'un compte marchand, retour 201 ;
- `PATCH  /api/admin/merchants/{merchantId}` — mise à jour partielle (first_name, last_name, phone, is_active) ;
- `PATCH  /api/admin/merchants/{merchantId}/suspend` — suspension (is_active → false) ;
- `PATCH  /api/admin/merchants/{merchantId}/activate` — activation (is_active → true).

- S5-006 — CRUD admin des catégories produit, PR #112.

Endpoints S5-006 livrés :

- `GET    /api/admin/categories` — liste paginée des catégories ;
- `GET    /api/admin/categories/{categoryId}` — détail d'une catégorie ;
- `POST   /api/admin/categories` — création, slug auto-généré si absent ;
- `PATCH  /api/admin/categories/{categoryId}` — mise à jour partielle ;
- `DELETE /api/admin/categories/{categoryId}` — suppression physique si non liée à des produits, logique sinon.

Prochaine étape recommandée :

- CRUD admin des marques et référentiel produit.

## Parcours cible

```text
Admin crée une supérette
→ génère le QR code
→ crée un compte marchand et l'associe
→ le marchand peut se connecter et gérer son catalogue
→ Admin gère le référentiel (marques, catégories, produits)
→ Admin valide les propositions de produits des marchands
→ Admin consulte les commandes si nécessaire (support)
```

## Décisions produit

- Les marchands ne peuvent pas s'inscrire en autonome dans le MVP. Leur compte est créé par l'admin.
- L'email d'invitation marchand est envoyé lors de la création du compte (hors périmètre technique MVP strict : un mot de passe temporaire suffit).
- Un marchand peut gérer plusieurs supérettes, mais dans le MVP chaque supérette n'a qu'un seul propriétaire.
- Le slug d'une supérette est généré automatiquement à la création (slugify du nom, suffixe si doublon).
- Le `qrCodeToken` est généré à la création et peut être régénéré par l'admin (invalide l'ancien).
- Les catégories et marques sont des listes gérées par l'admin. Les marchands ne peuvent pas en créer.
- Un produit archivé reste dans les commandes existantes mais ne peut plus être ajouté à de nouveaux catalogues.

## User stories concernées

| US | Sujet | Epic | Statut |
|---|---|---|---|
| US-009 | Créer et gérer les supérettes (admin) | EPIC-009 | Livré — lecture (S5-002) + mutations (S5-003) |
| US-028 | Gérer les comptes marchands | EPIC-009 | Livré — lecture (S5-001) + mutations (S5-004) |
| US-029 | Superviser le référentiel produit global | EPIC-009 | À faire |
| US-030 | Valider les propositions de nouveaux produits | EPIC-009 | À faire |

## Modèle métier — compléments

### CRUD admin Brands

```http
GET    /api/admin/brands
POST   /api/admin/brands         { "nameFr": "Vitalait", "nameAr": "فيتاليت" }
PATCH  /api/admin/brands/{id}
DELETE /api/admin/brands/{id}    (désactivation logique si des produits l'utilisent)
```

### CRUD admin Categories

```http
GET    /api/admin/categories
POST   /api/admin/categories     { "nameFr": "Produits laitiers", "nameAr": "منتجات الألبان", "slug": "produits-laitiers" }
PATCH  /api/admin/categories/{id}
DELETE /api/admin/categories/{id}
```

### CRUD admin ProductReferences

```http
GET    /api/admin/product-references?q=&category=&brand=
POST   /api/admin/product-references
PATCH  /api/admin/product-references/{id}
PATCH  /api/admin/product-references/{id}/archive
```

### Admin Stores

```http
GET    /api/admin/stores
GET    /api/admin/stores/{storeId}
POST   /api/admin/stores
PATCH  /api/admin/stores/{storeId}
PATCH  /api/admin/stores/{storeId}/activate
PATCH  /api/admin/stores/{storeId}/deactivate
PATCH  /api/admin/stores/{storeId}/owner       { "merchantId": "<uuid>" }
GET    /api/admin/stores/{storeId}/qr-code
POST   /api/admin/stores/{storeId}/regenerate-qr
```

Statut :

- S5-002 : `GET /api/admin/stores` et `GET /api/admin/stores/{storeId}` livrés par PR #104.
- S5-003 : `POST /api/admin/stores` et `PATCH /api/admin/stores/{storeId}` livrés par PR #107.
- S5-005 : `GET /api/admin/stores/{storeId}/qr-code` et `POST /api/admin/stores/{storeId}/regenerate-qr` livrés.

Contrat lecture liste :

```json
{
  "items": [
    {
      "id": "store-uuid",
      "name": "Supérette El Amal",
      "slug": "superette-el-amal",
      "city": "Tunis",
      "is_active": true,
      "qr_code_token": "qr-token-opaque",
      "created_at": "2026-05-18T10:00:00+00:00",
      "owner": {
        "id": "merchant-uuid",
        "email": "merchant@example.test"
      },
      "products_count": 12
    }
  ],
  "page": 1,
  "limit": 20,
  "total": 1
}
```

Contrat lecture détail :

```json
{
  "id": "store-uuid",
  "name": "Supérette El Amal",
  "slug": "superette-el-amal",
  "address": "Rue de la République",
  "city": "Tunis",
  "phone": "+21600000000",
  "is_active": true,
  "qr_code_token": "qr-token-opaque",
  "created_at": "2026-05-18T10:00:00+00:00",
  "owner": {
    "id": "merchant-uuid",
    "email": "merchant@example.test"
  },
  "products_count": 12,
  "theme_id": "theme-uuid",
  "opening_hours": null,
  "exceptional_closures_count": 0,
  "pickup_rules_count": 0
}
```

Règles S5-002 :

- réservé à `ROLE_ADMIN` ;
- JWT obligatoire ;
- `ROLE_MERCHANT` et `ROLE_CUSTOMER` interdits ;
- pagination `page` / `limit`, `limit` plafonné à 50 ;
- filtre optionnel `is_active=true|false` sur la liste ;
- tri stable par `created_at DESC`, puis `id DESC` ;
- lecture uniquement, sans création ni modification de supérette ;
- ne retourne ni mot de passe, ni hash, ni token auth, ni rôles utilisateur.

Contrat création S5-003 :

```json
{
  "name": "Supérette El Amal",
  "address": "Rue de la République",
  "city": "Tunis",
  "phone": "+21600000000",
  "ownerId": "merchant-uuid"
}
```

Contrat modification S5-003 :

```json
{
  "name": "Supérette El Amal",
  "address": "Rue de la République",
  "city": "Tunis",
  "phone": "+21600000000",
  "isActive": true,
  "ownerId": null
}
```

Règles S5-003 :

- réservé à `ROLE_ADMIN` ;
- JWT obligatoire ;
- `ROLE_MERCHANT` et `ROLE_CUSTOMER` interdits ;
- `name` obligatoire à la création ;
- `slug` généré automatiquement à la création depuis `name`, avec suffixe si le slug existe déjà ;
- `qrCodeToken` généré automatiquement à la création ;
- `PATCH` remplace uniquement les champs fournis ;
- `PATCH` ne régénère ni slug ni QR code ;
- `ownerId` peut assigner un marchand existant ou être `null` pour retirer le propriétaire ;
- supérette absente : `404`.

Contrat QR S5-005 :

```json
{
  "store_id": "store-uuid",
  "store_name": "Supérette El Amal",
  "slug": "superette-el-amal",
  "qr_code_token": "qr-token-opaque",
  "target_url": "/api/stores/by-qr/qr-token-opaque",
  "qr_payload": "/api/stores/by-qr/qr-token-opaque"
}
```

Règles S5-005 :

- réservé à `ROLE_ADMIN` ;
- JWT obligatoire ;
- `ROLE_MERCHANT` et `ROLE_CUSTOMER` interdits ;
- `GET /qr-code` retourne le contrat nécessaire pour afficher ou télécharger un QR côté client/admin UI ;
- le payload scannable reste l'URL publique existante `GET /api/stores/by-qr/{qrCodeToken}` ;
- aucune image QR n'est générée côté backend dans cette PR ;
- `POST /regenerate-qr` remplace uniquement `qrCodeToken` par un nouveau token opaque ;
- l'ancien token devient invalide immédiatement ;
- la régénération ne modifie ni catalogue, ni commande, ni propriétaire, ni slug ;
- supérette absente : `404`.

### Admin Merchants (comptes marchands)

```http
GET    /api/admin/merchants
GET    /api/admin/merchants/{merchantId}
POST   /api/admin/merchants      { "name", "email", "phone", "shopIds": [] }
PATCH  /api/admin/merchants/{id}/suspend
PATCH  /api/admin/merchants/{id}/activate
```

Statut S5-001 : `GET /api/admin/merchants` et `GET /api/admin/merchants/{merchantId}` livrés par PR #103.

Contrat lecture :

```json
{
  "items": [
    {
      "id": "merchant-uuid",
      "email": "merchant@example.test",
      "first_name": "Ali",
      "last_name": "Ben Salah",
      "phone": "+21600000000",
      "is_active": true,
      "created_at": "2026-05-18T10:00:00+00:00",
      "stores_count": 2
    }
  ],
  "page": 1,
  "limit": 20,
  "total": 1
}
```

Règles S5-001 :

- réservé à `ROLE_ADMIN` ;
- JWT obligatoire ;
- `ROLE_MERCHANT` et `ROLE_CUSTOMER` interdits ;
- pagination `page` / `limit`, `limit` plafonné à 50 ;
- tri stable par `created_at DESC`, puis `id DESC` ;
- ne retourne ni mot de passe, ni hash, ni token, ni donnée sensible.

Hors périmètre S5-001 et S5-002 :

- création de compte marchand ;
- suspension / activation ;
- association marchand-supérette ;
- création ou modification de supérette ;
- régénération QR ;
- invitation email ;
- onboarding marchand.

## Hors périmètre Sprint 5

- Inscription et authentification client (Sprint Auth — US-034, US-035, US-046).
- Inscription publique marchands (admin only dans le MVP).
- Analytics et reporting commandes (Sprint 7).
- Observabilité et audit logs (Sprint 7).
- Fermeture définitive supérette, export CSV, audit trail admin (Sprint 7).
- Billing, facturation, relances et paiements.

## Définition de fini globale

Le Sprint 5 sera cohérent lorsque :

1. L'administrateur peut créer une supérette et télécharger son QR code.
2. L'administrateur peut créer un compte marchand et l'associer à une supérette.
3. Le marchand créé peut se connecter et accéder à son backoffice.
4. L'administrateur peut gérer les marques, catégories et le référentiel produit.
5. L'administrateur peut valider ou rejeter les propositions de nouveaux produits.
6. Les accès admin restent protégés par JWT + `ROLE_ADMIN` uniquement.
