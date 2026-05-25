# Sprint Frontend — Backoffice admin, parcours client et front marchand

## Objectif

Livrer les interfaces frontend Kadhia nécessaires au MVP : parcours client mobile-first, backoffice admin et front marchand opérationnel, sans accès direct à la base de données.

Ce sprint frontend consume les API backend livrées en Sprint 4 (commandes, suivi, retrait), Sprint 5 (admin CRUD) et Sprint 7 (audit trail), puis prépare les prochains chantiers marchand autour du catalogue, des créneaux et des paramètres.

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

- **Front marchand — socle opérationnel commandes** — livré. PRs #134, #135, #136, #138, #139.
  - Connexion marchand, contexte marchand, shell, dashboard et commandes actives
  - Détail commande et actions jusqu'à `ready`
  - Retrait sécurisé par token QR, confirmation marchand et force completion
  - Historique commandes avec filtres "À retirer" / "Clôturées" et pagination
  - Notifications marchand avec badge non lu, filtres, rafraîchissement manuel et marquage lu

## Fonctionnalités prévues

- Gestion catalogue marchand
- Créneaux, horaires et fermetures marchand
- Onboarding marchand guidé
- QR code magasin marchand
- Paramètres et thème supérette
- Export CSV commandes côté UI
- PWA installable et mode hors ligne
- Accessibilité WCAG 2.1 AA
- Localisation FR/AR avec RTL

## Documents détaillés

- [Parcours client — état actuel + roadmap](parcours-client.md)
- [Admin backoffice — PRs #130, #131, #132](admin-backoffice.md)
- [Front marchand — prochains chantiers](merchant-next-chantiers.md)
