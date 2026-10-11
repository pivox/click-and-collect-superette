# Subscription Pricing Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task.

**Goal:** Gérer les abonnements payants, gratuits et avec remise, mensuels ou annuels, depuis l'admin.

**Architecture:** Étendre l'abonnement existant avec des conditions tarifaires versionnées. Centraliser les calculs et exposer des DTO additifs ; conserver les anciens abonnements et documents.

**Tech Stack:** Symfony, API Platform, Doctrine, PostgreSQL, BCMath, Next.js, Vitest.

## 1. Référence et contrat

- [x] Lire le contexte et explorer calcul, API, paiement manuel et interfaces.
- [x] Rédiger les règles dans `docs/superpowers/specs/2026-10-11-subscription-pricing-design.md`.
- [x] Travailler sur `feat/subscription-pricing-monthly-annual`, conserver les modifications locales de configuration.

## 2. Backend — domaine, API et migration

Responsabilité : `apps/backend/src/{Entity,Service,Dto,Processor,ApiResource,Provider,Repository}`, `apps/backend/migrations`, tests backend.

- [x] Écrire puis exécuter les tests de calcul et d'API en échec.
- [x] Ajouter les conditions versionnées et la migration additive.
- [x] Étendre `SubscriptionPricingSchedule` et `SubscriptionOutputFactory` sans casser `calculate(startedAt, now)` historique.
- [x] Ajouter création admin, programmation et simulation ; 403 hors admin, 404 si absent, 409 pour doublon/abonnement annulé, 422 pour conditions invalides.
- [x] Intégrer le rafraîchissement et les documents de période, avec protection du zéro et sans mutation des documents historiques.
- [x] Exécuter les tests ciblés et vérifier le style et PHPStan.

Cas numérique de référence : base annuelle `600.000`, remise 100 % mois 1–3,
50 % mois 4–6 → montant annuel `375.000`. Base annuelle `100.000` sans remise
→ somme exacte `100.000`, jamais `99.996`. À l'année 2, les remises expirées ne
se répètent pas. Une gratuité de 3 mois en annuel 600 TND donne 450 TND la
première année, puis 600 TND.

Commande : `docker compose exec -T backend php bin/phpunit --filter 'Subscription|BillingDocument'`.

## 3. Frontend — gestion admin et lecture marchand

Responsabilité : `apps/frontend/src/lib/{types,services}/subscriptions*`,
`apps/frontend/src/components/admin/subscriptions/`,
`apps/frontend/src/app/admin/abonnements/page.tsx`,
`apps/frontend/src/app/merchant/parametres/abonnement/page.tsx`, tests associés.

- [x] Écrire les tests de formulaire (annuel + remises), erreurs et affichage marchand avant le code.
- [x] Ajouter les types tarifaires et les méthodes create/update/preview au service.
- [x] Ajouter un formulaire réutilisable, avec tarif de période, gratuité et plages mensuelles ; invalider toute simulation après édition.
- [x] Ajouter sélection de marchand pour créer et détail/simulation/programmer pour modifier.
- [x] Afficher total annuel, détail des mois et changement futur en FR/AR côté marchand.
- [x] Exécuter Vitest ciblé, TypeScript et lint des fichiers modifiés.

Commandes : `docker compose exec -T frontend npm run test:run -- src/tests/admin.subscriptions-page.test.tsx src/tests/subscription.service.test.ts src/tests/merchant.subscription-page.test.tsx` ; `docker compose exec -T frontend npx tsc --noEmit`.

## 4. Revue, intégration et livraison locale

- [x] Revue indépendante de conformité puis de qualité, corriger les anomalies.
- [x] Mettre à jour `docs/architecture/api-contract.md` et le suivi de réalisation.
- [x] Exécuter migration locale, `lint:container`, validation Doctrine et tests ciblés élargis.
- [x] Vérifier l'API réelle et l'écran admin, sans modifier les conditions de marchands réels pour la démonstration.
- [x] Rapporter fichiers, contrôles réellement exécutés, hypothèses et limites ; aucun déploiement distant.

## Résultat

Voir le [rapport de vérification locale](../../qa/2026-10-11-subscription-pricing.md).
La validation Doctrine confirme le mapping mais signale des écarts de schéma
globaux préexistants, conservés hors périmètre.
