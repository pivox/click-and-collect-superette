# US-002 — Consulter le catalogue marchand

## Sprint

Sprint 2 — Parcours client.

## Epic rattaché

EPIC-002 — Catalogue produits.

## Objectif produit

Permettre au client de consulter les produits visibles et disponibles d'une supérette après avoir accédé à son espace public.

Cette user story transforme le catalogue construit par le marchand au Sprint 1 en expérience client : seuls les produits que le marchand a ajoutés, rendus visibles et marqués comme disponibles doivent apparaître côté client.

## Récit utilisateur

En tant que client,
je veux consulter le catalogue de la supérette,
afin de voir les produits que je peux ajouter à ma Kadhia.

## Acteurs

- Client final.
- Supérette active.
- Marchand propriétaire du catalogue.
- Plateforme Click & Collect.

## Préconditions

- La supérette existe et est active.
- La supérette possède un catalogue marchand.
- Les produits du catalogue sont liés à des références produit approuvées.
- Les produits à afficher sont `is_visible = true` et `is_available = true`.

## Parcours nominal

1. Le client ouvre la page publique de la supérette.
2. Le frontend appelle l'endpoint public du catalogue.
3. Le backend retourne la liste des produits visibles et disponibles.
4. Le client voit les produits avec leur nom, marque, format, catégorie et prix en TND.
5. Le client peut sélectionner un produit pour l'ajouter à sa Kadhia.

## Données produit attendues

Chaque produit affiché doit contenir au minimum :

- identifiant du produit marchand ;
- identifiant de la référence produit ;
- nom en français ;
- nom en arabe si disponible ;
- marque ;
- catégorie ;
- volume ;
- unité ;
- prix en TND avec trois décimales ;
- disponibilité ;
- visibilité.

## Règles métier

- Le catalogue client est public.
- Le catalogue client ne retourne que les produits du magasin courant.
- Les produits invisibles ne doivent jamais apparaître.
- Les produits indisponibles ne doivent pas apparaître dans le MVP client, sauf décision produit contraire ultérieure.
- Le prix affiché est le prix marchand, pas un prix global du référentiel.
- Le prix doit être formaté en TND avec trois décimales.
- Le client ne doit pas voir les informations internes du référentiel non utiles au parcours.
- Le client ne doit pas voir les notes internes marchand si elles sont privées.

## API existante / cible

Endpoint public :

```http
GET /api/stores/{storeId}/catalog
```

Réponse attendue minimale :

```json
{
  "store_id": "uuid",
  "items": [
    {
      "id": "merchant-product-uuid",
      "product_reference_id": "product-reference-uuid",
      "name_fr": "Lait demi-écrémé Vitalait 1L",
      "name_ar": null,
      "brand": "Vitalait",
      "category": "lait",
      "volume": "1",
      "unit": "L",
      "price_tnd": "1.750",
      "is_available": true,
      "is_visible": true
    }
  ]
}
```

## Critères d'acceptation

### Catalogue visible

Étant donné une supérette active avec des produits visibles et disponibles,
quand le client ouvre le catalogue,
alors il voit la liste des produits du magasin.

### Produit invisible

Étant donné un produit marchand `is_visible = false`,
quand le client consulte le catalogue,
alors ce produit n'est pas retourné.

### Produit indisponible

Étant donné un produit marchand `is_available = false`,
quand le client consulte le catalogue MVP,
alors ce produit n'est pas proposé à l'ajout dans la Kadhia.

### Prix TND

Étant donné un produit visible,
quand le client le consulte,
alors le prix est affiché en dinars tunisiens avec trois décimales.

### Isolation magasin

Étant donné deux supérettes différentes,
quand le client consulte le catalogue de la supérette A,
alors aucun produit propre à la supérette B ne doit être retourné.

## Tests attendus

- Test fonctionnel catalogue public avec produits visibles.
- Test d'exclusion des produits invisibles.
- Test d'exclusion des produits indisponibles.
- Test d'isolation par `storeId`.
- Test du format de sortie `price_tnd`.
- Test d'accès public sans JWT.

## Hors périmètre

- Pagination avancée.
- Tri personnalisé.
- Images produits.
- Promotions.
- Gestion du stock temps réel.
- Substitution de produits.

## Dépendances

- Sprint 1 : référentiel produit et catalogue marchand.
- Seed de démonstration pour disposer d'une supérette avec catalogue.
- US-001 ou accès direct à une supérette.

## Définition de fini

La story est terminée lorsque le client peut ouvrir le catalogue public d'une supérette active, voir uniquement les produits vendables du marchand et préparer l'ajout à la Kadhia.