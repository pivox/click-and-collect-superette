# Sprint Frontend — Backoffice admin, parcours client et front marchand

## Objectif

Livrer les interfaces frontend Kadhia nécessaires au MVP : parcours client mobile-first, backoffice admin et front marchand opérationnel, sans accès direct à la base de données.

Ce sprint frontend consume les API backend livrées en Sprint 4 (commandes, suivi, retrait), Sprint 5 (admin CRUD) et Sprint 7 (audit trail), puis prépare les prochains chantiers marchand autour du catalogue, des créneaux et des paramètres.

## État actuel au 4 juin 2026

- **Design system + parcours client** — livré. PR #126.
  - Foundations Tailwind (couleurs, typographie, spacing, shadows)
  - Composants `Button`, `Input`, badge statut
  - Parcours client mobile-first, ensuite branché sur les services réels : auth, supérettes, catalogue, Kadhia, rendez-vous, commandes, notifications et retrait

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

- **Front marchand — autonomie opérationnelle** — livré.
  - Catalogue marchand
  - Créneaux ponctuels, règles récurrentes, fermetures exceptionnelles et horaires
  - Onboarding marchand guidé
  - QR code magasin avec rendu et téléchargement SVG / impression
  - Paramètres, profil supérette, compte, langue FR/AR et thème/apparence
  - Export CSV commandes côté UI

- **i18n client FR/AR + RTL** — livré via S14-004 / #401.
  - `ClientLocaleProvider`, `LanguageToggle`, persistance `client:lang`
  - `dir="rtl"` appliqué sur l'espace client quand la langue arabe est sélectionnée
  - Traductions client dans `src/messages/fr.json` et `src/messages/ar.json`

## Fonctionnalités ouvertes

- PWA installable et mode hors ligne
- Accessibilité WCAG 2.1 AA
- Push notifications client + marchand
- Durcissement terrain : il reste surtout #356 checklist d'activation supérette ; worker async production, monitoring jobs, KPI bêta et QR PNG/PDF imprimable sont livrés.

## Documents détaillés

- [Parcours client — état actuel](parcours-client.md)
- [Admin backoffice — PRs #130, #131, #132](admin-backoffice.md)
- [Front marchand — état livré + chantiers restants](merchant-next-chantiers.md)
