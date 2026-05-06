# Taxonomie produit — MVP

## Objectif

Définir une classification simple pour permettre la navigation dans le catalogue et la recherche produit.

La taxonomie MVP doit rester compréhensible par un client de supérette et exploitable par le marchand.

## Principes

- La taxonomie doit être simple au démarrage.
- Une catégorie doit correspondre à un usage client clair.
- Les catégories trop fines sont à éviter au début.
- La taxonomie doit pouvoir évoluer sans casser le référentiel.

## Catégories MVP

### Alimentation

- Lait & produits laitiers.
- Boissons.
- Eau.
- Jus.
- Pâtes, riz, semoule.
- Conserves.
- Épicerie salée.
- Épicerie sucrée.
- Biscuits & gâteaux.
- Café, thé & infusions.
- Petit déjeuner.
- Huiles & sauces.
- Farine, sucre & ingrédients.

### Frais

- Fromage.
- Yaourts.
- Beurre & margarine.
- Oeufs.
- Charcuterie.

### Hygiène

- Savon & gel douche.
- Shampooing.
- Dentifrice.
- Déodorant.
- Papier & mouchoirs.
- Hygiène bébé.

### Maison

- Lessive.
- Nettoyage sol & surfaces.
- Vaisselle.
- Sacs poubelle.
- Désodorisants.

### Bébé

- Couches.
- Lingettes.
- Alimentation bébé.

### Animaux

- Nourriture chat.
- Nourriture chien.

## Champs de catégorie

```yaml
id: category_001
parent: alimentation
name_fr: Lait & produits laitiers
name_ar: null
slug: lait-produits-laitiers
is_active: true
sort_order: 10
```

## Règles

- Une catégorie peut avoir un parent.
- Un produit doit avoir une catégorie principale.
- Un produit peut avoir des tags de recherche complémentaires.
- Les noms français et arabes doivent être prévus dans le modèle, même si la traduction est complétée progressivement.

## Priorité MVP

Commencer avec peu de catégories, mais propres. Ajouter des sous-catégories uniquement lorsque la recherche ou la navigation devient confuse.
