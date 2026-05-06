# UX — Recherche produit

## Objectif

Définir l'expérience de recherche produit pour le client et pour le marchand.

La recherche est centrale dans le MVP, car le client et le marchand doivent retrouver des produits déjà existants au lieu de recréer ou parcourir un catalogue trop long.

## Recherche côté client

### Objectif

Permettre au client de trouver rapidement un produit disponible dans la supérette ouverte via QR code.

### Entrées possibles

- Nom produit : `lait`, `yaourt`, `eau`.
- Marque : `Vitalait`, `Délice`, `Safia`.
- Volume : `1L`, `500g`, `1.5L`.
- Variante : `demi-écrémé`, `sans sucre`, `gazeuse`.
- Catégorie : `Boissons`, `Lait & produits laitiers`.

### Résultat affiché

Chaque résultat doit afficher :

- nom produit ;
- marque ;
- volume / unité ;
- prix TND ;
- disponibilité ;
- bouton d'ajout à la Kadhia.

### Règles

- Seuls les produits visibles du catalogue marchand sont affichés.
- Un produit indisponible ne doit pas être commandable.
- Le résultat doit rester compréhensible sans image produit.
- La recherche doit fonctionner avec les alias simples.

## Recherche côté marchand

### Objectif

Permettre au marchand d'ajouter rapidement des produits à son catalogue depuis le référentiel global.

### Parcours

1. Le marchand ouvre `Ajouter un produit`.
2. Il recherche par nom, marque, volume ou catégorie.
3. La plateforme affiche les produits du référentiel global.
4. Le marchand sélectionne un produit.
5. Il définit son prix, sa disponibilité et sa visibilité.
6. Le produit est ajouté à son catalogue.

### Cas aucun résultat

Si aucun produit n'est trouvé, le marchand peut proposer un nouveau produit.

## Recherche admin

L'administrateur doit pouvoir rechercher :

- les produits de référence ;
- les propositions en attente ;
- les doublons potentiels ;
- les produits par marque ou catégorie.

## Filtres MVP

- Catégorie.
- Marque.
- Disponibilité.
- Supérette.

## Améliorations futures

- Recherche tolérante aux fautes.
- Recherche arabe/français croisée.
- Suggestions automatiques.
- Scan code-barres.
- Produits populaires.
- Historique de recherche.

## Critère de réussite MVP

Un client ou un marchand doit pouvoir retrouver un produit courant en moins de quelques secondes avec une recherche simple.
