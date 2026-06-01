# Catalogue supérette Tunisie — v1.0.0

Version du catalogue produit destiné au projet Click & Collect Supérette Tunisie.

## Contenu

- **5000 produits** en statut `draft`
- Structure `verification-ready`
- Marques exactes non inventées : `brand` reste `null` tant que la marque n'est pas confirmée
- Marques proposées conservées dans `brand_candidates`
- Champs de vérification :
  - `commercial_identity`
  - `brand_verification`
  - `price_verification`
  - `data_quality`

## Organisation des fichiers

Le catalogue complet local existe sous `catalogue_superette_tunisie_v1.0.0.json`.

Dans GitHub, cette version pose le dossier de version, le manifest, le changelog, le schéma et le workflow de vérification. Le gros fichier JSON complet pèse environ 13 Mo ; il peut être ajouté ensuite comme fichier monolithique ou découpé en shards selon la stratégie d'import retenue.

## Import recommandé

La commande Symfony pourra lire un fichier JSON versionné et mapper les produits vers le référentiel métier.

Pseudo-code :

```php
$payload = json_decode(file_get_contents('catalogue_superette_tunisie_v1.0.0.json'), true);

foreach ($payload['products'] as $product) {
    // mapper vers ProductReference / BrandCandidate / data_quality
}
```

## Statut

Cette version est une **stable draft** : elle est exploitable pour construire le référentiel, mais pas encore `commerce_ready`.

Avant usage production, confirmer au minimum :

1. marque exacte ;
2. GTIN/EAN ou référence fournisseur ;
3. prix TND ;
4. statut fiscal / TVA si nécessaire.
