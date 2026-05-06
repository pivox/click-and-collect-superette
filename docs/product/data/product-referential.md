# Référentiel produit — MVP

## Objectif

Le référentiel produit global permet au client et au marchand de trouver des produits déjà existants dans l'application.

L'objectif est d'éviter que chaque supérette recrée manuellement les mêmes produits et de proposer une base de produits adaptée au marché tunisien.

## Principe

Un produit de référence décrit un produit réel de manière normalisée, indépendamment du prix et de la disponibilité dans une supérette donnée.

Exemple :

```yaml
brand: Vitalait
name_fr: Lait demi-écrémé
name_ar: null
volume: 1
unit: litre
category: Lait & produits laitiers
country: Tunisie
variant: Demi-écrémé
```

## Données minimales

Chaque produit de référence doit contenir :

- un nom français ;
- une marque ;
- une catégorie ;
- un volume ou une quantité ;
- une unité ;
- une variante si nécessaire ;
- un pays de marché ;
- un statut de validation.

## Données optionnelles

- Nom arabe.
- Alias de recherche.
- Code-barres EAN si disponible.
- Image, uniquement si elle est fournie légalement ou par le marchand.
- Description courte, uniquement si elle est rédigée par la plateforme ou fournie avec autorisation.

## Ce que le référentiel ne contient pas

- Prix marchand.
- Stock marchand.
- Disponibilité en supérette.
- Promotion propre à une supérette.
- Description marketing copiée depuis un autre site.

Ces informations appartiennent au catalogue marchand.

## Exemples de produits

| Marque | Nom | Volume | Unité | Catégorie | Variante |
|---|---|---:|---|---|---|
| Vitalait | Lait demi-écrémé | 1 | litre | Lait & produits laitiers | Demi-écrémé |
| Délice | Yaourt nature | 110 | g | Lait & produits laitiers | Nature |
| Boga | Boisson gazeuse | 1 | litre | Boissons | Classique |
| Safia | Eau minérale | 1.5 | litre | Boissons | Plate |
| Randa | Pâtes | 500 | g | Pâtes, riz, semoule | Spaghetti |

## Statuts produit

| Statut | Description |
|---|---|
| `draft` | Produit proposé mais incomplet. |
| `pending_review` | Produit proposé par un marchand et en attente de validation. |
| `approved` | Produit validé et utilisable. |
| `rejected` | Produit refusé. |
| `archived` | Produit ancien ou non utilisé. |

## Règle MVP

Pour le MVP, le référentiel peut démarrer avec un seed limité mais propre, par exemple 200 à 500 produits fréquents de supérette.

La priorité est la qualité de normalisation plutôt que le volume massif.
