# Instructions Codex — Click & Collect Supérette Tunisie

## Mission

Aider à transformer le cadrage produit en code maintenable, testé et compatible Symfony/API Platform.

## Règles générales

- Répondre en français par défaut.
- Lire `AGENTS.md` et `AI_CONTEXT.md` avant d'agir.
- Favoriser des changements petits et faciles à relire.
- Ne pas inventer de dépendances, commandes ou résultats de tests.
- Ne pas modifier plusieurs domaines métier sans raison claire.
- Toujours préserver le périmètre MVP.

## Règles de développement

- Les contrôleurs doivent rester fins.
- La logique métier doit être placée dans des services, processors, providers ou classes domaine.
- Les changements de base doivent passer par des migrations Doctrine.
- Les entrées API complexes doivent utiliser des DTO.
- Les sorties API doivent utiliser des groupes de sérialisation adaptés.
- Les règles de sécurité doivent distinguer client, marchand et administrateur.
- Les tests doivent couvrir les règles métier importantes.

## API Platform

- Utiliser des opérations dédiées quand deux routes exposent deux jeux de données différents.
- Préférer les groupes de normalisation/dénormalisation explicites.
- Ne pas exposer plus de données que nécessaire.
- Éviter d'utiliser `eager` comme solution principale à un problème de représentation API.
- Préférer Provider/Processor quand le comportement dépasse un CRUD simple.

## Doctrine/PostgreSQL

- Utiliser des noms explicites.
- Garder les relations lisibles et justifiées.
- Indexer les champs de recherche : shop, product, status, createdAt, pickupSlot.
- Ne pas créer une gestion de stock complexe dans le MVP.
- Garder une traçabilité minimale sur les commandes et changements de statut.

## Produit

Toujours garder en tête :

- client mobile-first ;
- marchand rapide et pragmatique ;
- admin plateforme sobre ;
- produits tunisiens avec marque, format, volume, catégorie ;
- prix et disponibilité propres à chaque marchand.
