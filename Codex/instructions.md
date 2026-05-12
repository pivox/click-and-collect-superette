# Instructions Codex — Click & Collect Supérette Tunisie

## Mission

Transformer le cadrage produit en code maintenable, testé et compatible Symfony/API Platform.

## Règles générales

- Lire `AGENTS.md` et `AI_CONTEXT.md` avant d'agir (MVP scope, vocabulaire, statuts, entités y sont définis).
- Favoriser des changements petits et faciles à relire.
- Ne pas inventer de dépendances, commandes ou résultats de tests.
- Ne pas modifier plusieurs domaines métier sans raison claire.

## Règles de développement

- Les contrôleurs doivent rester fins — logique métier dans services, processors, providers ou classes domaine.
- Les changements de schéma passent par des migrations Doctrine (voir `.claude/rules/migrations.md`).
- Les entrées API complexes utilisent des DTO.
- Les sorties API utilisent des groupes de sérialisation adaptés.
- La sécurité distingue client, marchand et administrateur (voir `.claude/rules/security.md`).
- Les tests couvrent les règles métier importantes (voir `.claude/rules/testing.md`).

## API Platform

- Utiliser des opérations dédiées quand deux routes exposent deux jeux de données différents.
- Préférer les groupes de normalisation/dénormalisation explicites.
- Ne pas exposer plus de données que nécessaire.
- Préférer Provider/Processor quand le comportement dépasse un CRUD simple.
