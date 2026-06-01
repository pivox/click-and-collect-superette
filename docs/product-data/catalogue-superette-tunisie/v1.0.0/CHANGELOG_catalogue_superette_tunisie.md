# Changelog — catalogue_superette_tunisie

## [1.0.0] - 2026-05-30

### Ajouté
- Version stable draft du catalogue avec 5000 produits.
- Bloc `meta.versioning`.
- `catalog_version`.
- `schema_version`.
- Fingerprint SHA-256 sur le tableau `products`.
- Historique des versions précédentes.
- Compatibilité préparée pour import Symfony/BDD.
- Champs de vérification :
  - `commercial_identity`
  - `brand_verification`
  - `price_verification`
  - `data_quality`

### Important
- `brand` reste `null` tant qu'une marque n'est pas confirmée.
- Les marques proposées restent dans `brand_candidates`.
- Le catalogue est prêt pour import en mode `draft`, pas encore en mode `commerce_ready`.

## [0.4.0] - 2026-05-30

### Ajouté
- Marques candidates par catégorie/type produit.
- Statuts `candidate_only` et `category_level`.

## [0.3.0] - 2026-05-30

### Ajouté
- Extension du catalogue à 5000 produits.

## [0.2.0] - 2026-05-30

### Ajouté
- Extension du catalogue à 3000 produits.

## [0.1.0] - 2026-05-30

### Ajouté
- Première base de 1000 produits.
