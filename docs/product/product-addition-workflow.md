# Workflow d'ajout d'un produit manquant

## Objectif

Permettre au référentiel produit de s'enrichir progressivement sans créer de doublons ni dégrader la qualité des données.

## Principe

Le marchand ne doit pas commencer par créer un produit depuis zéro. Il doit d'abord rechercher le produit dans le référentiel global.

Si le produit existe, il l'ajoute à son catalogue marchand. Si le produit n'existe pas, il peut proposer un nouveau produit à valider.

## Parcours nominal

1. Le marchand ouvre la recherche produit.
2. Il saisit un nom, une marque ou un volume.
3. La plateforme recherche dans le référentiel global.
4. Le marchand sélectionne le produit existant.
5. Il ajoute le produit à son catalogue.
6. Il définit le prix, la disponibilité et la visibilité.
7. Le produit devient visible côté client si `is_visible = true` et `is_available = true`.

## Parcours produit manquant

1. Le marchand recherche un produit.
2. Aucun résultat satisfaisant n'est trouvé.
3. Le marchand clique sur `Proposer un produit`.
4. Il renseigne les champs minimaux.
5. Le produit est créé avec le statut `pending_review`.
6. L'administrateur vérifie, corrige ou fusionne.
7. Si le produit est valide, il passe en `approved`.
8. Le marchand peut l'ajouter à son catalogue.

## Champs minimaux pour proposer un produit

- Nom français.
- Marque.
- Catégorie.
- Volume ou quantité.
- Unité.
- Variante si nécessaire.
- Photo optionnelle uniquement si elle est fournie par le marchand.
- Code-barres optionnel.

## Contrôles anti-doublons

Avant création, la plateforme doit rechercher des produits similaires selon :

- marque ;
- nom de base ;
- variante ;
- volume ;
- unité ;
- catégorie.

Si un produit proche existe, le marchand doit être invité à le sélectionner plutôt qu'à créer un doublon.

## Décisions admin

| Décision | Effet |
|---|---|
| Valider | Le produit devient utilisable dans le référentiel. |
| Corriger | Les champs sont normalisés avant validation. |
| Fusionner | La proposition est rattachée à un produit existant. |
| Refuser | Le produit n'est pas utilisable. |

## Règles MVP

- La validation admin peut être simple au départ.
- Les propositions de produits doivent être historisées.
- Une proposition refusée doit conserver une raison.
- Les marchands ne modifient pas directement un produit approuvé globalement.

## Critère de succès

Le référentiel s'enrichit progressivement tout en restant propre, réutilisable et adapté aux produits vendus en Tunisie.
