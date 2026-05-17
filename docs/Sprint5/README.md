# Sprint 5 — Administration minimale

## Objectif du sprint

Sprint 5 donne à l'opérateur plateforme les outils nécessaires pour onboarder des supérettes, créer des comptes marchands, gérer le référentiel produit et maintenir les données structurantes (marques, catégories).

C'est le sprint qui rend la plateforme opérable sans accès direct à la base de données.

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
| US-009 | Créer et gérer les supérettes (admin) | EPIC-009 | Complétée |
| US-028 | Gérer les comptes marchands | EPIC-009 | En cours — S5-001 livre la lecture admin |
| US-029 | Superviser le référentiel produit global | EPIC-009 | Existante |
| US-030 | Valider les propositions de nouveaux produits | EPIC-009 | Existante |

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
POST   /api/admin/stores
PATCH  /api/admin/stores/{storeId}
PATCH  /api/admin/stores/{storeId}/activate
PATCH  /api/admin/stores/{storeId}/deactivate
PATCH  /api/admin/stores/{storeId}/owner       { "merchantId": "<uuid>" }
POST   /api/admin/stores/{storeId}/regenerate-qr
```

### Admin Merchants (comptes marchands)

```http
GET    /api/admin/merchants
GET    /api/admin/merchants/{merchantId}
POST   /api/admin/merchants      { "name", "email", "phone", "shopIds": [] }
PATCH  /api/admin/merchants/{id}/suspend
PATCH  /api/admin/merchants/{id}/activate
```

Statut S5-001 : `GET /api/admin/merchants` et `GET /api/admin/merchants/{merchantId}` livrés.

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

Hors périmètre S5-001 :

- création de compte marchand ;
- suspension / activation ;
- association marchand-supérette ;
- invitation email ;
- onboarding marchand.

## Hors périmètre Sprint 5

- Inscription et authentification client (Sprint Auth — US-034, US-035, US-046).
- Inscription publique marchands (admin only dans le MVP).
- Analytics et reporting commandes (Sprint 7).
- Observabilité et audit logs (Sprint 7).
- Fermeture définitive supérette, export CSV, audit trail admin (Sprint 7).

## Définition de fini globale

Le Sprint 5 est cohérent lorsque :

1. L'administrateur peut créer une supérette et télécharger son QR code.
2. L'administrateur peut créer un compte marchand et l'associer à une supérette.
3. Le marchand créé peut se connecter et accéder à son backoffice.
4. L'administrateur peut gérer les marques, catégories et le référentiel produit.
5. L'administrateur peut valider ou rejeter les propositions de nouveaux produits.
6. Un client peut s'inscrire et consulter/modifier son profil.
