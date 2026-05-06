# Modèle catalogue marchand

## Objectif

Séparer clairement le référentiel produit global du catalogue propre à chaque supérette.

Le référentiel produit décrit les produits existants. Le catalogue marchand décrit ce qu'une supérette vend réellement, à quel prix et avec quelle disponibilité.

## ProductReference

Produit normalisé global, partagé par toute la plateforme.

Exemple :

```yaml
id: product_ref_001
brand: Vitalait
name_fr: Lait demi-écrémé
volume: 1
unit: litre
category: Lait & produits laitiers
variant: Demi-écrémé
status: approved
```

## MerchantProduct

Produit activé par une supérette dans son propre catalogue.

Exemple :

```yaml
id: merchant_product_001
store: store_001
product_reference: product_ref_001
price_tnd: 1.650
is_available: true
is_visible: true
merchant_note: null
```

## Responsabilités

### Référentiel global

- Nom produit.
- Marque.
- Volume.
- Unité.
- Catégorie.
- Variante.
- Alias de recherche.
- Statut de validation.

### Catalogue marchand

- Prix.
- Disponibilité.
- Visibilité.
- Rupture éventuelle.
- Note interne marchand.
- Ordre d'affichage éventuel.

## Règles métier

- Un marchand ne doit pas modifier directement les données normalisées d'un produit approuvé.
- Un marchand peut proposer une correction ou un nouveau produit.
- Un même ProductReference peut être utilisé par plusieurs supérettes.
- Chaque supérette peut avoir son propre prix pour le même ProductReference.
- Un produit non disponible ne doit pas être commandable.
- Un produit invisible ne doit pas apparaître côté client.

## Cas produit manquant

1. Le marchand cherche un produit.
2. Le produit n'existe pas dans le référentiel.
3. Le marchand propose un nouveau produit.
4. Le produit passe en `pending_review`.
5. L'administrateur valide, corrige ou refuse.
6. Une fois approuvé, le produit devient réutilisable par d'autres marchands.

## Implication UX

Côté marchand, l'ajout au catalogue doit commencer par une recherche dans le référentiel, pas par un formulaire vide.
