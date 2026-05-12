# US-003 — Ajouter un produit à la Kadhia

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-003 — Gestion Kadhia.

## Objectif produit

Permettre au client d'ajouter un produit du catalogue public d'une supérette à sa Kadhia.

La Kadhia représente le panier de courses du client. Elle est liée à une seule supérette et contient des lignes de produits avec quantités et prix figés au moment de l'ajout.

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

- Le client est authentifié avec un rôle client `ROLE_USER`.
- Le client consulte le catalogue public d'une supérette active.
- Le produit sélectionné est un `MerchantProduct` visible et disponible.
- Le produit appartient à la supérette courante.

## Parcours nominal

1. Le client recherche ou parcourt le catalogue d'une supérette.
2. Il clique sur `Ajouter` sur un produit.
3. Le frontend appelle l'API d'ajout à la Kadhia avec l'identifiant du produit marchand.
4. Le backend crée une Kadhia `draft` si le client n'en a pas encore pour cette supérette.
5. Le backend ajoute une ligne avec quantité `1` par défaut.
6. Le backend fige le prix et les informations produit utiles.
7. Le client voit la Kadhia mise à jour.

## Règles métier

- Une Kadhia appartient à un seul client.
- Une Kadhia `draft` appartient à une seule supérette.
- Un client peut avoir une Kadhia `draft` active pour une supérette.
- Un produit ajouté à la Kadhia doit être un produit marchand, pas une référence produit globale.
- Le prix doit être copié depuis le `MerchantProduct` au moment de l'ajout.
- Les informations produit utiles doivent être snapshotées pour garder l'historique même si le catalogue change ensuite.
- Si le produit existe déjà dans la Kadhia, la quantité est incrémentée au lieu de créer un doublon.
- Une Kadhia soumise ne peut plus être modifiée.
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

Endpoint protégé client :

```http
POST /api/kadhia/lines
Authorization: Bearer <client_jwt>
Content-Type: application/json
```

Payload :

```json
{
  "store_id": "store-uuid",
  "merchant_product_id": "merchant-product-uuid",
  "quantity": 1
}
```

Réponse attendue :

```json
{
  "id": "kadhia-uuid",
  "store_id": "store-uuid",
  "status": "draft",
  "items": [
    {
      "id": "line-uuid",
      "merchant_product_id": "merchant-product-uuid",
      "name_fr": "Lait demi-écrémé Vitalait 1L",
      "quantity": 1,
      "unit_price_tnd": "1.750",
      "line_total_tnd": "1.750"
    }
  ],
  "total_tnd": "1.750"
}
```

## Critères d'acceptation

### Ajout simple

Étant donné un client connecté et un produit visible,
quand le client ajoute le produit à sa Kadhia,
alors une Kadhia `draft` est créée si nécessaire,
et une ligne est ajoutée avec quantité `1`.

### Incrément d'une ligne existante

Étant donné une Kadhia contenant déjà le produit,
quand le client ajoute à nouveau ce produit,
alors la quantité de la ligne existante est incrémentée.

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
- Test création automatique d'une Kadhia `draft`.
- Test incrément si ligne existante.
- Test refus produit invisible.
- Test refus produit indisponible.
- Test refus produit d'une autre supérette.
- Test refus sans authentification.
- Test snapshot du prix et des informations produit.

## Hors périmètre

- Créneau de retrait.
- Soumission de commande.
- Paiement en ligne.
- Gestion de stock temps réel.
- Substitution produit.
- Kadhia multi-supérettes.

## Dépendances

- US-002 — Consulter le catalogue marchand.
- US-017 — Rechercher un produit.
- Authentification client.
- Modèle `Kadhia` et `KadhiaLine`.

## Définition de fini

La story est terminée lorsque le client connecté peut ajouter un produit vendable du catalogue à une Kadhia `draft`, avec prix snapshoté, sans doublon de ligne, et avec total recalculé.