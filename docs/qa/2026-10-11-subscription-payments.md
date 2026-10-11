# Vérification du gestionnaire de règlements — 11 octobre 2026

## Périmètre vérifié

Page admin `/admin/reglements`, versements partiels et complets sur documents mensuels/annuels, solde à trois décimales, historique, filtres et liens depuis Abonnements et Facturation. Les paiements manuels existants sont conservés.

## Frontend

Commande Docker `npm run test:run` sur les 11 fichiers suivants : **47 tests réussis**.

- `admin.subscription-payments-page.test.tsx`
- `subscription-payment-form.test.tsx`
- `subscription-pricing-form.test.tsx`
- `subscription-pricing-summary.test.tsx`
- `subscription-document.test.tsx`
- `subscription-dialog.test.tsx`
- `admin.subscriptions-page.test.tsx`
- `admin.billing-documents-page.test.tsx`
- `merchant.subscription-page.test.tsx`
- `billing-documents.service.test.ts`
- `subscription-payments.service.test.ts`

`docker compose exec -T frontend npx tsc --noEmit` : réussi.
ESLint ciblé sur les composants, pages, services et types modifiés : réussi.

Les tests de soumission utilisent `fireEvent.submit` pour contourner un défaut reproduit de happy-dom : `input type=number min=0.001 step=0.001 value=10.000` est déclaré à tort `stepMismatch`. La validation native a été vérifiée dans Chromium.

## Backend

Exécution dans le conteneur `cc_backend` (`php bin/phpunit <classe>`), 18 classes abonnements/facturation/règlements : **85 tests, 593 assertions, tous réussis**, dont `AdminPartialSubscriptionPaymentApiTest` (6 tests, 167 assertions) et `AdminSubscriptionPaymentCreateInputTest`.

`vendor/bin/phpstan analyse --memory-limit=1G` : aucune erreur. `php-cs-fixer fix --dry-run --diff` : 0 fichier à corriger sur 1127. `bin/console lint:container` : réussi.

## Navigateur

Script local `/tmp/subscription-payments-browser.cjs`, Chromium en 1440×1000 et 390×844 :

- connexion réelle admin et lectures réelles des deux collections API : HTTP 200 ;
- versement simulé `20.001` sur document `50.000` : confirmation, solde `29.999`, statut partiel et historique corrects ;
- refus natif d’un montant `50.001` supérieur au solde ;
- fenêtre utilisable sur mobile, sans débordement horizontal de page ;
- aucune erreur JavaScript ;
- mutations interceptées dans le navigateur : aucun règlement fictif écrit en base locale.

Captures locales : `/tmp/subscription-payments-desktop.png`, `/tmp/subscription-payments-mobile.png`.

## Migration et qualité

Migration `Version20261011134412` exécutée localement en montée, descente puis remontée avec succès. `doctrine:migrations:up-to-date` confirme la base à jour ; `composer validate --no-check-publish` et `git diff --check` réussissent.

La descente refuse de rétablir l’unicité d’un règlement par document si plusieurs versements existent déjà. Aucun changement automatique des écarts de schéma historiques.

## Revues

Revues de conformité et de qualité frontend/backend closes :

- conservation de la clé et du contenu après erreur réseau, expiration de session et conflit d’identité admin ;
- actualisation du montant d’une relance en attente après un versement partiel ;
- ordre de verrouillage commun abonnement puis document pour règlement, email et WhatsApp ;
- rejet d’une clé d’idempotence vide.

## Limites

Pas de test de concurrence PostgreSQL avec deux connexions réelles. La couverture transactionnelle combine revue du verrouillage et tests fonctionnels. Pas de suite backend globale ni build production exécutés localement ; les contrôles GitHub de la PR complètent les vérifications locales. Aucun déploiement distant.

Les modifications préexistantes de `.env` et Docker Compose sont exclues de la livraison.
