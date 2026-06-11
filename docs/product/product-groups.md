# Groupements de produits référentiel

Statut : cadrage produit Sprint 15 — #466 et #467 livrés
Issues : #464, #465, #466, #467  
Document détaillé : `docs/Sprint15/README.md`

## Définition

Un groupement de produits est une sélection admin de `ProductReference` proposée au marchand pour accélérer l'ajout de produits à son catalogue.

Exemple : `Premières nécessités` peut contenir sel, sucre en vrac, farine, pain, lait, eau, huile, riz, pâtes, harissa, thon et sardines.

## Règles métier obligatoires

- Un `ProductReference` peut appartenir à plusieurs groupements.
- Le même `ProductReference` ne peut pas être ajouté deux fois au même groupement.
- Le groupement ne porte jamais de prix, stock, disponibilité ou visibilité client.
- L'import d'un groupement dans le catalogue marchand doit être idempotent.
- Si le produit est déjà présent dans le catalogue marchand, il n'est pas réinséré.
- Un produit importé par #466 est créé avec `price_tnd = 0.000`, reste à compléter et non visible côté client.
- #467 permet au marchand de retrouver ces produits via `completion=needs_price`, de saisir un prix positif et de publier uniquement les produits complets.
- La première saisie d'un vrai prix après import crée l'historique de prix marchand ; le référentiel global ne porte toujours aucun prix.

## Modèle cible

```text
product_group
- id
- name_fr
- name_ar nullable
- slug
- description_fr nullable
- market_country default TN
- status draft|published|archived
- sort_order
- created_at
- updated_at
```

```text
product_group_item
- id
- product_group_id
- product_reference_id
- sort_order
- importance required|recommended|optional
- created_at
- updated_at
```

Contrainte obligatoire :

```text
UNIQUE(product_group_id, product_reference_id)
```

## Import marchand

Contrôle anti-doublon minimum :

```text
store_id + product_reference_id
```

Réponse d'import attendue :

```json
{
  "created": 42,
  "alreadyInCatalog": 8,
  "skipped": 0,
  "requiresPriceCompletion": 42,
  "errors": []
}
```

Décision #466 : `defaultVisibility` est accepté dans le payload pour compatibilité, mais les produits créés restent `is_visible = false` tant que le prix n'est pas complété.

Décision #467 : le backend refuse de publier un produit si son prix final reste `0.000`. Le catalogue client filtre les produits sans prix positif, même si une donnée incohérente les marque visibles.

## Endpoints cibles

```http
GET    /api/admin/product-groups
POST   /api/admin/product-groups
PATCH  /api/admin/product-groups/{groupId}
PATCH  /api/admin/product-groups/{groupId}/publish
PATCH  /api/admin/product-groups/{groupId}/archive
POST   /api/admin/product-groups/{groupId}/items
DELETE /api/admin/product-groups/{groupId}/items/{itemId}

GET  /api/merchant/stores/{storeId}/product-groups
GET  /api/merchant/stores/{storeId}/product-groups/{groupId}
POST /api/merchant/stores/{storeId}/catalog/import-from-product-group  # #466
GET  /api/merchant/stores/{storeId}/catalog?completion=needs_price      # #467
```

#465 expose seulement les lectures marchand par supérette et la sélection.
#466 livre l'import effectif, idempotent et sans doublon catalogue.
#467 livre la complétion des prix après import.

## Groupements MVP recommandés

- Premières nécessités
- Petit déjeuner
- Boissons froides
- Épicerie sèche
- Hygiène maison

## Critère de conformité

L'implémentation est conforme si un produit peut être dans plusieurs groupements, si un import relancé ne crée pas de doublon, et si les données marchand restent uniquement dans le catalogue marchand.
