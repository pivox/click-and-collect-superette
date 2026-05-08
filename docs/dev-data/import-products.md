# Import de produits — données de développement

Ce document explique comment alimenter la base de développement avec ~50 000 produits réels issus des bases ouvertes Open Food Facts, Open Beauty Facts et Open Products Facts.

## Contexte

`OpenDataProduct` est une entité **de données dev uniquement**, distincte des entités métier `ProductReference` et `MerchantProduct`. Elle n'est pas exposée via l'API Platform. Son but est de fournir un catalogue réaliste pour développer et tester le parcours client (recherche, catalogue, Kadhia).

Les prix sont fictifs, générés aléatoirement en TND. Les données produit (nom, marque, image, nutriscore…) sont réelles et proviennent des bases open data.

## Sources utilisées

| Clé   | Source                 | Contenu                          | URL de base                            |
|-------|------------------------|----------------------------------|----------------------------------------|
| `off` | Open Food Facts        | Alimentaire                      | world.openfoodfacts.org                |
| `obf` | Open Beauty Facts      | Cosmétique / hygiène             | world.openbeautyfacts.org              |
| `opf` | Open Products Facts    | Ménager / divers                 | world.openproductsfacts.org            |

La source `off` interroge d'abord les produits tunisiens (préfixe GS1 `619`). Si la réponse contient moins de 100 produits, un fallback sur le catalogue mondial est activé automatiquement.

## Commandes

### Importer les produits

```bash
# Toutes les sources, 30 pages chacune (~90 000 appels max)
php bin/console app:products:import --source=all --pages=30

# Une seule source
php bin/console app:products:import --source=off --pages=10

# Test sans écrire en base
php bin/console app:products:import --source=off --pages=1 --dry-run
```

Options :

| Option      | Valeur par défaut | Description                               |
|-------------|-------------------|-------------------------------------------|
| `--source`  | `all`             | `off` \| `obf` \| `opf` \| `all`         |
| `--pages`   | `30`              | Pages à récupérer par source (1 page ≈ 1000 produits) |
| `--dry-run` | —                 | Parse et affiche les stats sans écrire    |

À l'import, tous les produits sont créés avec `active = false`. Ils ne seront pas exposés dans le catalogue tant que `seed-prices` n'a pas été lancé.

### Assigner des prix fictifs

```bash
php bin/console app:products:seed-prices
php bin/console app:products:seed-prices --min=0.5 --max=20.0
```

Cette commande :
- assigne un prix aléatoire en TND (entre `min` et `max`) à tous les produits sans prix ;
- assigne un stock aléatoire entre 5 et 50 ;
- passe `active = true` pour rendre les produits visibles.

Les prix sont **uniquement fictifs** et destinés au développement. Ne jamais utiliser ces données en production.

### Afficher les statistiques

```bash
php bin/console app:products:stats
```

Affiche un tableau récapitulatif par source :

```
Source       | Total  | Avec image | Avec prix | Actifs
-------------|--------|------------|-----------|-------
off (food)   | 28 450 |     24 100 |    28 450 |  28 450
obf (beauty) |  9 200 |      7 800 |     9 200 |   9 200
opf (other)  |  6 100 |      4 200 |     6 100 |   6 100
TOTAL        | 43 750 |     36 100 |    43 750 |  43 750
```

## One-liner recommandé

```bash
make setup-dev-data
```

Équivalent à :

```bash
make import-products   # importe ~50k produits (active = false)
make seed-prices       # assigne prix + stock + active = true
make products-stats    # affiche le récapitulatif
```

## Préfixe GS1 Tunisie

Les vrais produits tunisiens ont un barcode commençant par `619` (préfixe GS1 attribué à la Tunisie). La recherche prioritaire sur `tag_0=tunisia` dans l'API Open Food Facts cible ces produits en priorité.

## Séparation des entités

```
OpenDataProduct      ← données open data, dev uniquement
     ≠
ProductReference     ← référentiel métier validé, exposé en API
     ≠
MerchantProduct      ← offre marchand avec prix, dispo et visibilité propres
```

`OpenDataProduct` peut servir de source d'inspiration pour alimenter `ProductReference` manuellement, mais il n'y a pas de lien Doctrine entre ces entités.
