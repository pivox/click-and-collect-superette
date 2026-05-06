# Workflows Codex

## Workflow 1 — Implémentation Symfony/API Platform

1. Lire `AGENTS.md`, `AI_CONTEXT.md` et la documentation métier concernée.
2. Identifier les entités, DTO, opérations API et règles de sécurité.
3. Implémenter par petits commits logiques.
4. Ajouter ou mettre à jour les migrations Doctrine.
5. Ajouter tests unitaires ou fonctionnels.
6. Lancer les vérifications disponibles.
7. Résumer les changements et limites.

## Workflow 2 — Nouvelle ressource API

Checklist de création :

- Entity Doctrine ;
- migration ;
- API Resource ;
- groupes de sérialisation ;
- DTO d'entrée si nécessaire ;
- Processor si logique métier ;
- Provider si lecture spécifique ;
- validation ;
- sécurité ;
- tests ;
- documentation API ou produit.

## Workflow 3 — Route avec représentation différente

Quand deux routes exposent deux jeux de données différents :

1. Créer deux opérations API Platform distinctes.
2. Définir des groupes de normalisation dédiés.
3. Utiliser un Provider si les données doivent être préparées autrement.
4. Éviter de contourner la conception avec `Ignore` ou eager loading global.
5. Tester les deux payloads.

## Workflow 4 — Référentiel produit Tunisie

1. Définir le produit référentiel : nom, marque, format, volume, unité, catégorie.
2. Définir les variantes si nécessaire.
3. Définir l'offre marchand : prix, disponibilité, visibilité, shop.
4. Ajouter la source externe si la donnée vient d'un import ou scraping dev.
5. Ne pas importer images ou descriptions protégées sans autorisation.
6. Prévoir la recherche client par nom, marque et format.

## Workflow 5 — Correction de bug

1. Reproduire ou décrire précisément le bug.
2. Identifier la cause racine.
3. Modifier le minimum nécessaire.
4. Ajouter un test de non-régression.
5. Lancer les vérifications disponibles.
6. Expliquer le risque restant.

## Workflow 6 — Revue de PR

1. Lire le contexte métier.
2. Vérifier MVP scope.
3. Vérifier sécurité par rôle.
4. Vérifier cohérence API.
5. Vérifier migrations et tests.
6. Signaler les problèmes bloquants en premier.
7. Proposer corrections concrètes.
