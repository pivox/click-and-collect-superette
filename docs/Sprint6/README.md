# Sprint 6 — Personnalisation visuelle

## Objectif du sprint

Sprint 6 permet de personnaliser l'identité visuelle de la plateforme et de chaque supérette.

L'objectif est que la vitrine client puisse refléter le thème actif d'une supérette via l'API publique, tout en conservant un thème global administrable.

## État actuel

Sprint 6 est indiqué comme complet dans la roadmap MVP.

Ce document sert de point de référence documentaire, car le dossier `docs/Sprint6/` n'était pas présent sur `main` alors que la roadmap mentionne le sprint comme implémenté.

## Fonctionnalités couvertes

- Thème global admin.
- Thème spécifique par supérette.
- Surcharge du thème global par le thème marchand/supérette.
- Variables CSS exposées via une API publique.
- Lecture du thème actif d'une supérette.
- Avertissement ou contrôle de contraste WCAG lorsque disponible.

## User stories concernées

| US | Sujet | Statut |
|---|---|---|
| US-010 | Configurer le thème global admin | À vérifier dans le code / docs existantes |
| US-011 | Personnaliser le thème de la supérette | À vérifier dans le code / docs existantes |
| US-012 | Afficher le storefront avec le thème actif | Indiqué livré par la roadmap |

## Endpoint public attendu

```http
GET /api/stores/{storeId}/theme
```

Cet endpoint doit permettre au frontend/PWA client de récupérer le thème actif d'une supérette et de générer les variables CSS nécessaires.

## Points à vérifier lors d'un audit futur

- Le thème global existe côté admin.
- Une supérette peut surcharger le thème global.
- L'API publique retourne le thème actif réel.
- Le payload ne contient aucune donnée sensible.
- Les valeurs retournées sont directement exploitables côté frontend.
- Les règles de contraste sont documentées ou testées.

## Hors périmètre

- Refonte frontend complète.
- Système de thèmes avancé multi-layout.
- Upload d'assets visuels lourds.
- Gestion marketing avancée.
- Personnalisation par client final.

## Décision documentaire

Ce fichier est ajouté pour aligner la structure `docs/` avec la roadmap MVP.

Aucune fonctionnalité applicative n'est ajoutée par cette documentation.
