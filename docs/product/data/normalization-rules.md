# Règles de normalisation produit

## Objectif

Éviter les doublons et rendre les produits faciles à rechercher par les clients et les marchands.

Exemple de problème à éviter :

- `Vitalait lait 1L`
- `Lait Vitalait 1 litre`
- `Vitalait demi écrémé 1000ml`

Ces libellés doivent pointer vers un seul produit normalisé si le produit réel est le même.

## Structure recommandée du nom

Format d'affichage :

```text
{Nom produit} {Variante} {Marque} {Volume}{Unité}
```

Exemple :

```text
Lait demi-écrémé Vitalait 1L
```

## Marques

- La marque doit être stockée dans une entité dédiée.
- La casse doit être normalisée.
- Les variantes d'écriture doivent être stockées comme alias.

Exemple :

```yaml
canonical_name: Vitalait
aliases:
  - VITALAIT
  - vita lait
```

## Unités

Unités MVP recommandées :

| Unité canonique | Alias possibles |
|---|---|
| litre | L, l, litre, litres |
| millilitre | ml, mL, millilitre |
| kilogramme | kg, kilo, kilogramme |
| gramme | g, gr, gramme |
| pièce | pc, pcs, unité, pièce |
| paquet | paquet, pack |

## Volumes et conversions

- `1L` peut être stocké comme `volume = 1`, `unit = litre`.
- `1000ml` peut être converti en `1 litre` si cela simplifie la comparaison.
- Pour l'affichage, garder le format le plus compréhensible localement.

## Variantes

Exemples de variantes :

- Demi-écrémé.
- Entier.
- Sans sucre.
- Light.
- Nature.
- Vanille.
- Chocolat.
- Plate.
- Gazeuse.

La variante doit être séparée du nom de base lorsque c'est possible.

## Alias de recherche

Chaque produit peut avoir des alias pour améliorer la recherche :

```yaml
name_fr: Lait demi-écrémé
brand: Vitalait
aliases:
  - lait vitalait
  - vitalait 1l
  - lait demi ecreme vitalait
```

## Langues

- Le modèle doit prévoir `name_fr` et `name_ar`.
- La traduction arabe peut être progressive.
- L'interface arabe doit tenir compte du RTL lorsque nécessaire.

## Détection de doublons

Deux produits sont probablement identiques si les champs suivants sont équivalents :

- marque ;
- nom de base ;
- variante ;
- volume ;
- unité ;
- catégorie.

## Règle MVP

Le seed produit initial doit être vérifié manuellement. Il vaut mieux avoir peu de produits propres qu'un grand référentiel rempli de doublons.
