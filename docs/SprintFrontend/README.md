# Sprint Frontend — Backoffice admin et design system

## Objectif

Livrer l'interface d'administration Kadhia permettant aux opérateurs plateforme de gérer les marchands, supérettes, le référentiel produits et de consulter l'audit trail, sans accès direct à la base de données.

Ce sprint frontend consume les API backend livrées en Sprint 5 (admin CRUD) et Sprint 7 (audit trail).

## État actuel

- **Design system + parcours client** — livré. PR #126.
  - Foundations Tailwind (couleurs, typographie, spacing, shadows)
  - Composants `Button`, `Input`, badge statut
  - Parcours client mobile-first (scan QR, catalogue, Kadhia, rendez-vous)

- **Auth admin + layout** — livré. PRs #130, #131.
  - Middleware Next.js, `AdminAuthContext`, `AdminShell`, `AdminSidebar`
  - Référentiel produits : Catégories, Marques, Produits, Propositions

- **Marchands, supérettes, audit, dashboard** — livré. PR #132.
  - CRUD marchands (suspend / réactiver)
  - CRUD supérettes (archiver)
  - Audit logs (lecture seule, filtre UUID admin)
  - Dashboard 4 KPI réels

## Fonctionnalités prévues

- Parcours client complet (scan QR, commande, suivi, retrait)
- Interface marchand (commandes reçues, préparation, validation retrait)
- PWA installable et mode hors ligne
- Accessibilité WCAG 2.1 AA
- Localisation FR/AR avec RTL

## Documents détaillés

- [Admin backoffice — PRs #130, #131, #132](admin-backoffice.md)
