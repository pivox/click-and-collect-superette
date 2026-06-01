# Integration Symfony - catalogue superette Tunisie v1.0.0

## Objectif

Importer le catalogue complet comme donnees de reference draft.

## Fichier source

`docs/product-data/catalogue-superette-tunisie/v1.0.0/catalogue_superette_tunisie_v1.0.0.json`

## Commande cible proposee

Creer une commande Symfony de type `app:catalogue:import` avec deux modes :

- dry-run : validation sans ecriture en base ;
- import : creation ou mise a jour des references produit.

## Mapping recommande

| Champ JSON | Cible Symfony |
|---|---|
| sku | identifiant externe |
| name_fr | nom FR |
| name_tn_latin | nom tunisien latinise nullable |
| name_ar | nom arabe nullable |
| category | categorie |
| subcategory | sous-categorie |
| unit | unite ou format |
| brand | marque confirmee nullable |
| brand_candidates | JSON ou table associee |
| commercial_identity.gtin | GTIN/EAN nullable |
| commercial_identity.gtin_type | type GTIN nullable |
| brand_verification.evidence_level | niveau de preuve |
| price_verification.estimated_price_tnd | prix estime nullable |
| data_quality.import_status | statut d'import |
| data_quality.commerce_ready | pret commerce |

## Regles d'import

- Importer en draft par defaut.
- Ne jamais activer automatiquement un produit.
- Ne jamais copier une valeur de brand_candidates dans brand sans preuve.
- Garder les prix a null si aucune source fiable n'est renseignee.
- Refuser ou isoler les doublons de sku.

## Sortie dry-run attendue

- Catalogue : catalogue_superette_tunisie
- Version : 1.0.0
- Produits : 5000
- Statut : OK

## Evolution proposee

- v1.1.0 : ajout name_tn_latin et name_ar.
- v1.2.0 : ajout GTIN/EAN et references fournisseurs.
- v1.3.0 : ajout prix TND verifies.
- v2.0.0 : catalogue majoritairement commerce_ready.
