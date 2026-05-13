# US-003 — Ajouter un produit à la Kadhia

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-003 — Gestion Kadhia.

## Objectif produit

Permettre au client d'ajouter un produit du catalogue public d'une supérette à sa Kadhia.

La **Kadhia** est la liste de courses que le client prépare auprès d'un marchand avant de venir la récupérer. Elle est liée à une seule supérette et contient des lignes de produits avec quantités et prix figés au moment de l'ajout. Un client peut avoir plusieurs Kadhia pour la même supérette.

## Récit utilisateur

En tant que client connecté,
je veux ajouter un produit du catalogue à ma Kadhia,
afin de préparer ma commande de retrait.

## Acteurs

- Client connecté.
- Supérette active.
- Produit marchand visible et disponible.
- Plateforme Click & Collect.

## Préconditions

- Le client est authentifié avec `ROLE_CUSTOMER`.
- Le client a déjà créé une Kadhia `draft` pour la supérette (via US-003-A).
- Le produit sélectionné est un `MerchantProduct` visible et disponible.
- Le produit appartient à la même supérette que la Kadhia.

## Parcours nominal

1. Le client sélectionne une Kadhia `draft` existante.
2. Il parcourt le catalogue et clique sur `Ajouter` sur un produit.
3. Le frontend appelle l'API d'ajout avec l'identifiant de la Kadhia et du produit marchand.
4. Le backend vérifie que la Kadhia appartient au client et est en `draft`.
5. Le backend effectue un upsert de la ligne : création si absente, remplacement de la quantité si présente.
6. Le backend fige le prix et les informations produit utiles (snapshot).
7. Le backend recalcule le total de la Kadhia.
8. Le client voit la Kadhia mise à jour.

## Règles métier

- Une Kadhia appartient à un seul client.
- Une Kadhia est liée à une seule supérette.
- Un client peut avoir plusieurs Kadhia pour la même supérette.
- La création d'une Kadhia est explicite (US-003-A) ; un `GET` ou un ajout de ligne ne crée jamais de Kadhia.
- Un produit ajouté à la Kadhia doit être un produit marchand (`MerchantProduct`), pas une référence produit globale.
- Le prix doit être copié depuis le `MerchantProduct` au moment de l'ajout (snapshot).
- Les informations produit utiles doivent être snapshotées pour garder l'historique même si le catalogue change.
- L'endpoint est un upsert : si la ligne existe déjà, la quantité est remplacée ; sinon, une nouvelle ligne est créée.
- Une Kadhia `submitted` ne peut plus être modifiée ; l'API retourne `KADHIA_NOT_EDITABLE`.
- Un produit invisible ou indisponible ne peut pas être ajouté.

## Données snapshot recommandées

Chaque ligne de Kadhia doit conserver au minimum :

- `merchant_product_id` ;
- `product_reference_id` ;
- `name_fr` ;
- `name_ar` si disponible ;
- `brand` ;
- `category` ;
- `volume` ;
- `unit` ;
- `unit_price_tnd` ;
- `quantity` ;
- `line_total_tnd`.

## API cible

Endpoint protégé client (upsert de ligne) :

```http
PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
Authorization: Bearer <client_jwt>
Content-Type: application/json
```

Payload :

```json
{
  "quantity": 1
}
```

Réponse attendue (200 ou 201 selon création/mise à jour) :

```json
{
  "id": "kadhia-uuid",
  "store_id": "store-uuid",
  "status": "draft",
  "lines": [
    {
      "id": "line-uuid",
      "merchant_product_id": "merchant-product-uuid",
      "name_fr": "Lait demi-écrémé Vitalait 1L",
      "brand": "Vitalait",
      "quantity": 1,
      "unit_price_tnd": "1.700",
      "line_total_tnd": "1.700"
    }
  ],
  "total_tnd": "1.700"
}
```

## Critères d'acceptation

### Ajout simple

Étant donné un client avec une Kadhia `draft` et un produit visible,
quand le client appelle `PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}`,
alors une nouvelle ligne est créée avec la quantité indiquée.

### Upsert d'une ligne existante

Étant donné une Kadhia contenant déjà le produit,
quand le client envoie une nouvelle quantité,
alors la quantité de la ligne existante est remplacée (pas incrémentée).

### Produit indisponible

Étant donné un produit marchand indisponible,
quand le client tente de l'ajouter,
alors l'API refuse l'action avec une erreur métier claire.

### Produit hors supérette

Étant donné un produit appartenant à une autre supérette,
quand le client tente de l'ajouter à la Kadhia courante,
alors l'API refuse l'action.

### Client non connecté

Étant donné un client non authentifié,
quand il tente d'ajouter un produit,
alors l'API retourne `401 Unauthorized`.

## Tests attendus

- Test ajout d'un produit visible et disponible.
- Test upsert : remplacement de la quantité si ligne existante.
- Test refus produit invisible.
- Test refus produit indisponible.
- Test refus produit d'une autre supérette.
- Test refus si Kadhia appartient à un autre client (404).
- Test refus si Kadhia est `submitted` (KADHIA_NOT_EDITABLE).
- Test refus sans authentification (401).
- Test snapshot du prix et des informations produit.

## Hors périmètre

- Créneau de retrait.
- Soumission de commande.
- Paiement en ligne.
- Gestion de stock temps réel.
- Substitution produit.
- Kadhia multi-supérettes.

## Dépendances

- US-003-A — Créer une Kadhia (la Kadhia doit exister avant l'ajout).
- US-002 — Consulter le catalogue marchand.
- US-017 — Rechercher un produit.
- Authentification client.
- Modèle `Kadhia` et `KadhiaLine`.

## Définition de fini

La story est terminée lorsque le client connecté peut ajouter un produit vendable du catalogue à une Kadhia `draft` existante, avec prix snapshoté, comportement upsert documenté, et total recalculé côté serveur.