# Sources de données produit — MVP

## Objectif

Identifier les sources possibles pour constituer un référentiel produit initial adapté aux supérettes tunisiennes.

Le besoin prioritaire n'est pas de récupérer des images, des descriptions longues ou des catalogues complets, mais de constituer une base structurée de noms produits, marques, volumes, unités, variantes et catégories.

## Données recherchées

- Nom du produit.
- Marque.
- Volume ou quantité.
- Unité.
- Variante.
- Catégorie.
- Pays ou marché cible.
- Alias de recherche.
- Code-barres si disponible.

## Sources possibles pour le développement

### Saisie manuelle initiale

Créer un premier seed court et maîtrisé avec les produits les plus courants en supérette.

Avantages :

- qualité élevée ;
- pas de dépendance externe ;
- meilleur contrôle de la normalisation.

Inconvénient :

- plus lent au démarrage.

### CSV préparé

Construire un fichier CSV importable contenant les produits MVP.

Champs recommandés :

```csv
brand,name_fr,name_ar,volume,unit,category,variant,country,barcode
```

### Collecte terrain

Collecter les produits courants depuis des supérettes pilotes, tickets de caisse, listes de prix ou saisie marchand.

### Catalogues fournisseurs ou marques

Utiliser les informations publiques ou fournies par les marques et distributeurs lorsque les droits d'utilisation sont clairs.

### Scraping pour seed de développement

Le scraping peut être utilisé uniquement comme aide temporaire en environnement de développement pour identifier des noms, marques, volumes et catégories.

Les données doivent ensuite être nettoyées, normalisées et validées avant une utilisation produit.

## Données à éviter sans autorisation

- Images propriétaires.
- Descriptions marketing longues.
- Fiches produits complètes copiées.
- Prix concurrents utilisés comme promesse commerciale.
- Catalogue complet répliqué depuis un acteur tiers.

## Stratégie recommandée MVP

1. Créer un seed manuel de 200 à 500 produits fréquents.
2. Structurer la taxonomie.
3. Prévoir un import CSV.
4. Permettre aux marchands de proposer les produits manquants.
5. Valider les propositions côté admin.
6. Enrichir progressivement le référentiel.

## Priorité

Le MVP doit privilégier un référentiel propre et utile plutôt qu'une base massive difficile à maintenir.
