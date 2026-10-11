# Subscription payments implementation plan

> **For agentic workers:** Use superpowers:subagent-driven-development to execute the implementation and review.

**Goal:** Enregistrer plusieurs règlements manuels par période facturée et suivre leur solde depuis l’admin.

**Architecture:** Étendre les entités et routes existantes, avec service transactionnel, filtres serveur et formulaire React partagé. Conserver les montants décimaux TND et les autorisations existantes.

**Tech Stack:** Symfony, API Platform, Doctrine, PostgreSQL, Next.js, Vitest.

## 1. Backend — propriété apps/backend

- [ ] Ajouter les tests de versements 20 + 30 sur document 50, refus 30.001 après 20, répétition de clé, filtres, accès et réactivation avec plusieurs dettes dans `tests/Functional/SubscriptionPaymentApiTest.php` ou une nouvelle classe dédiée ; lancer PHPUnit dans Docker et constater l’échec.
- [ ] Étendre `SubscriptionPayment`, `BillingDocument`, DTO et migration ; extraire `Service/SubscriptionPaymentRecorder.php`. Transaction/verrous abonnement puis document ; addition/soustraction BCMath ; solde nul seul déclenche paid ; rejet du trop-perçu.
- [ ] Implémenter les filtres `subscription_id`, `q`, `outstanding` pour les documents et `subscription_id`, `q`, `billing_document_id` pour les règlements, cohérents avec total. Ajouter `document_number` au DTO de sortie.
- [ ] Lancer les tests Subscription/BillingDocument, PHPStan et CS Fixer via Docker.

## 2. Frontend — propriété apps/frontend

- [ ] Écrire les tests Vitest du formulaire partagé : montant partiel, refus au-delà du solde, erreur réseau avec clé conservée. Écrire les tests de page : documents et historique filtrés.
- [ ] Créer `components/admin/subscriptions/SubscriptionPaymentForm.tsx`, `app/admin/reglements/page.tsx` et composant gestionnaire si nécessaire. Étendre services/types pour les filtres et clé. Garder une pagination indépendante pour chaque vue.
- [ ] Ajouter le menu Règlements, les liens avec `subscription_id` et `billing_document_id`, et réutiliser le formulaire dans Facturation.
- [ ] Exécuter Vitest ciblé, TypeScript et ESLint. Vérifier un navigateur desktop/mobile.

## 3. Intégration et livraison

- [ ] Revue de conformité puis qualité ; corriger les observations.
- [ ] Appliquer la migration locale et vérifier le statut des migrations, sans synchronisation forcée du schéma.
- [ ] Mettre à jour contrat API et rapport QA avec les résultats réels.
- [ ] Commit limité aux fichiers de la fonctionnalité, push et ouverture d’une PR demandée par l’utilisateur ; ne pas inclure `.env` et les modifications Compose préexistantes.
