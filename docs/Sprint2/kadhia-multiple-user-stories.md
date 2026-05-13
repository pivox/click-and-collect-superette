# Sprint 2 — User stories Kadhia multiple

## Contexte

Le parcours client doit permettre à un client connecté de créer plusieurs Kadhia pour une même supérette.

Une Kadhia est une ressource métier explicite : elle ne doit pas être créée par un `GET`. La création se fait par `POST`, puis les opérations de consultation, modification, ajout de lignes, suppression de lignes et soumission ciblent une Kadhia précise via son identifiant.

## Décisions produit

- Un client peut créer plusieurs Kadhia pour une même supérette.
- Une Kadhia est créée explicitement avec `POST /api/me/stores/{storeId}/kadhias`.
- Un `GET` ne crée jamais de Kadhia.
- Une Kadhia en statut `draft` est modifiable.
- Une Kadhia en statut `submitted` est consultable mais non modifiable.
- Les opérations sur les lignes ciblent toujours une Kadhia précise via `kadhiaId`.
- La soumission transforme une Kadhia précise en commande.
- Après soumission, la Kadhia reste listable et consultable dans l'historique client.

## Endpoints cibles

```http
POST   /api/me/stores/{storeId}/kadhias
GET    /api/me/kadhias
GET    /api/me/kadhias/{kadhiaId}
PATCH  /api/me/kadhias/{kadhiaId}
PUT    /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
POST   /api/me/kadhias/{kadhiaId}/submit
```

Filtres de liste attendus :

```http
GET /api/me/kadhias?status=draft
GET /api/me/kadhias?status=submitted
GET /api/me/kadhias?store_id={storeId}
```

## US-003-A — Créer une Kadhia

### En tant que

Client connecté.

### Je veux

Créer une nouvelle Kadhia pour une supérette.

### Afin de

Préparer un panier de courses sans être limité à une seule Kadhia active.

### Endpoint cible

```http
POST /api/me/stores/{storeId}/kadhias
```

### Body

```json
{
  "notes": "Courses pour samedi matin"
}
```

### Réponse 201

```json
{
  "id": "kadhia-uuid",
  "store_id": "store-uuid",
  "status": "draft",
  "notes": "Courses pour samedi matin",
  "lines": [],
  "total_tnd": "0.000",
  "created_at": "2026-05-13T08:00:00+00:00",
  "updated_at": "2026-05-13T08:00:00+00:00"
}
```

### Critères d'acceptation

- Le client doit être authentifié avec `ROLE_CUSTOMER`.
- La supérette doit exister.
- La supérette doit être active.
- Une nouvelle Kadhia est créée en statut `draft`.
- Le client peut créer plusieurs Kadhia pour la même supérette.
- Le `GET` ne crée jamais implicitement une Kadhia.
- La création se fait uniquement par `POST`.

## US-003-B — Lister mes Kadhia

### En tant que

Client connecté.

### Je veux

Lister mes Kadhia.

### Afin de

Retrouver mes paniers en cours ou déjà soumis.

### Endpoint cible

```http
GET /api/me/kadhias
```

### Filtres

```http
GET /api/me/kadhias?status=draft
GET /api/me/kadhias?status=submitted
GET /api/me/kadhias?store_id={storeId}
```

### Réponse 200

```json
{
  "items": [
    {
      "id": "kadhia-uuid",
      "store_id": "store-uuid",
      "store_name": "Supérette Amen",
      "status": "draft",
      "lines_count": 3,
      "total_tnd": "18.500",
      "created_at": "2026-05-13T08:00:00+00:00",
      "updated_at": "2026-05-13T08:10:00+00:00"
    }
  ]
}
```

### Critères d'acceptation

- Le client ne voit que ses propres Kadhia.
- Le client peut filtrer par statut.
- Le client peut filtrer par supérette.
- Les statuts minimum sont `draft` et `submitted`.
- Une Kadhia soumise reste consultable.
- La liste peut être paginée si le volume le nécessite.

## US-003-C — Consulter le détail d'une Kadhia

### En tant que

Client connecté.

### Je veux

Voir le détail d'une Kadhia précise.

### Afin de

Consulter son contenu, son total et son état.

### Endpoint cible

```http
GET /api/me/kadhias/{kadhiaId}
```

### Réponse 200

```json
{
  "id": "kadhia-uuid",
  "store_id": "store-uuid",
  "status": "draft",
  "notes": "Courses pour samedi matin",
  "lines": [
    {
      "id": "line-uuid",
      "merchant_product_id": "product-uuid",
      "name_fr": "Lait demi-écrémé 1L",
      "name_ar": null,
      "brand": "Vitalait",
      "quantity": 2,
      "unit_price_tnd": "1.700",
      "line_total_tnd": "3.400"
    }
  ],
  "total_tnd": "3.400",
  "created_at": "2026-05-13T08:00:00+00:00",
  "updated_at": "2026-05-13T08:10:00+00:00"
}
```

### Critères d'acceptation

- Le client ne peut consulter que ses propres Kadhia.
- Si la Kadhia n'existe pas ou ne lui appartient pas, l'API retourne `404`.
- Le détail inclut les lignes.
- Le détail inclut les prix snapshotés.
- Le détail inclut le total global en TND.
- Le statut de la Kadhia est visible.

## US-019-A — Modifier les notes d'une Kadhia draft

### En tant que

Client connecté.

### Je veux

Modifier les notes d'une Kadhia en brouillon.

### Afin de

Ajouter des indications avant la soumission.

### Endpoint cible

```http
PATCH /api/me/kadhias/{kadhiaId}
```

### Body

```json
{
  "notes": "Merci de remplacer si produit indisponible"
}
```

### Critères d'acceptation

- Le client ne peut modifier que ses propres Kadhia.
- Seule une Kadhia `draft` est modifiable.
- Une Kadhia `submitted` ne peut plus être modifiée.
- Les notes sont optionnelles.
- Les notes sont limitées à 500 caractères.
- Si la Kadhia n'est plus modifiable, l'API retourne une erreur métier, par exemple `KADHIA_NOT_EDITABLE`.

## US-003-D — Ajouter un produit à une Kadhia draft

### En tant que

Client connecté.

### Je veux

Ajouter un produit à une Kadhia existante.

### Afin de

Composer mon panier.

### Endpoint cible

```http
PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
```

### Body

```json
{
  "quantity": 2
}
```

### Critères d'acceptation

- La Kadhia doit appartenir au client.
- La Kadhia doit être en statut `draft`.
- Le produit doit appartenir à la même supérette que la Kadhia.
- Le produit doit être visible.
- Le produit doit être disponible.
- La quantité doit être strictement positive.
- Le prix du produit est snapshoté au moment de l'ajout.
- Si la ligne existe déjà, la quantité est remplacée.
- Le total de ligne et le total global sont recalculés.

## US-019-B — Modifier la quantité d'un produit dans une Kadhia draft

### En tant que

Client connecté.

### Je veux

Modifier la quantité d'un produit déjà présent.

### Afin de

Ajuster ma Kadhia avant soumission.

### Endpoint cible

```http
PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
```

### Body

```json
{
  "quantity": 5
}
```

### Critères d'acceptation

- La Kadhia doit appartenir au client.
- La Kadhia doit être en statut `draft`.
- Le produit doit déjà être présent ou l'endpoint peut se comporter comme un upsert documenté.
- La quantité doit être strictement positive.
- Le total de ligne est recalculé.
- Le total global de la Kadhia est recalculé.
- Une Kadhia soumise retourne une erreur métier, par exemple `KADHIA_NOT_EDITABLE`.

## US-019-C — Retirer un produit d'une Kadhia draft

### En tant que

Client connecté.

### Je veux

Supprimer un produit de ma Kadhia.

### Afin de

Corriger mon panier avant validation.

### Endpoint cible

```http
DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
```

### Critères d'acceptation

- La Kadhia doit appartenir au client.
- La Kadhia doit être en statut `draft`.
- Si la ligne n'existe pas, l'API retourne `404`.
- Après suppression, le total global est recalculé.
- Une Kadhia `submitted` ne peut pas être modifiée.

## US-020 — Voir le récapitulatif d'une Kadhia

### En tant que

Client connecté.

### Je veux

Voir le total et les lignes de ma Kadhia.

### Afin de

Valider mon panier avant soumission.

### Endpoint cible

```http
GET /api/me/kadhias/{kadhiaId}
```

### Critères d'acceptation

- Le total est exprimé en TND.
- Chaque ligne affiche la quantité.
- Chaque ligne affiche le prix unitaire snapshoté.
- Chaque ligne affiche le total de ligne.
- Le total global est cohérent avec les lignes.
- Le statut de la Kadhia est visible.

## US-021-A — Soumettre une Kadhia précise

### En tant que

Client connecté.

### Je veux

Soumettre une Kadhia précise avec un créneau de retrait.

### Afin de

Transformer cette Kadhia en commande.

### Endpoint cible

```http
POST /api/me/kadhias/{kadhiaId}/submit
```

### Body

```json
{
  "pickup_slot_id": "slot-uuid",
  "notes": "Optionnel"
}
```

### Réponse 201

```json
{
  "id": "order-uuid",
  "kadhia_id": "kadhia-uuid",
  "store_id": "store-uuid",
  "status": "submitted",
  "total_tnd": "18.500",
  "pickup_slot_id": "slot-uuid"
}
```

### Critères d'acceptation

- La Kadhia doit appartenir au client.
- La Kadhia doit être en statut `draft`.
- La Kadhia ne doit pas être vide.
- Le créneau doit appartenir à la même supérette que la Kadhia.
- Le créneau doit être actif.
- Le créneau doit être futur.
- Le créneau doit être disponible.
- La soumission crée une commande en statut `submitted`.
- La Kadhia passe en statut `submitted`.
- La capacité du créneau est décrémentée transactionnellement.
- Après soumission, la Kadhia n'est plus modifiable.

## US-021-B — Lister mes Kadhia soumises

### En tant que

Client connecté.

### Je veux

Voir mes Kadhia déjà soumises.

### Afin de

Suivre mes anciennes demandes de commande.

### Endpoint cible

```http
GET /api/me/kadhias?status=submitted
```

### Critères d'acceptation

- Le client voit ses propres Kadhia soumises.
- Les Kadhia soumises sont consultables.
- Les Kadhia soumises ne sont pas modifiables.
- Chaque Kadhia soumise peut être reliée à une commande.
- Le statut de commande peut être inclus dans la liste ou accessible via le détail commande.

## Impact sur le découpage technique

Ces user stories impliquent un correctif de modèle par rapport à une API mono-Kadhia implicite :

| Sujet | Cible produit |
| --- | --- |
| Création | `POST /api/me/stores/{storeId}/kadhias` |
| Liste | `GET /api/me/kadhias` avec filtres |
| Consultation | `GET /api/me/kadhias/{kadhiaId}` |
| Modification | uniquement via `kadhiaId` et seulement si `draft` |
| Lignes | ajout, modification et suppression sur une Kadhia précise |
| Soumission | `POST /api/me/kadhias/{kadhiaId}/submit` |
| Historique | Kadhia `submitted` listable et consultable |
