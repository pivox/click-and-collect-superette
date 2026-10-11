# Vérification locale — abonnements et remises

Date : 11 octobre 2026. Branche : `feat/subscription-pricing-monthly-annual`.

## Périmètre

Implémentation du [cadrage validé](../superpowers/specs/2026-10-11-subscription-pricing-design.md) :
abonnements payants, avec remise ou gratuits ; périodicité mensuelle/annuelle ;
remises par mois ; mêmes fonctionnalités ; paiement manuel. Les changements
existants de configuration `.env` et Docker Compose ne font pas partie de cette
livraison.

## Vérifications exécutées

| Contrôle | Résultat |
|---|---|
| PHPUnit, filtre `Subscription\|BillingDocument` via Docker | 77 tests, 425 assertions, réussite |
| Unités calcul et validation après ajout du dernier cas de liste mal formée | 26 tests, 39 assertions, réussite |
| Vitest ciblé élargi abonnements, facturation et marchands | 12 fichiers, 66 tests, réussite |
| TypeScript `npx tsc --noEmit` via Docker | Réussite |
| ESLint des composants, écrans, types et service modifiés | Réussite |
| PHPStan global, limite mémoire 512 Mo | Réussite |
| PHP-CS-Fixer dry-run global | Aucun changement requis |
| `lint:container` et `composer validate` via Docker | Réussite |
| `git diff --check` | Réussite |
| Migration `Version20261011120000` et migrations à jour | Appliquée localement, aucune migration en attente |
| Validation Doctrine | Mapping valide ; schéma global divergent, voir limites |

Commande frontend finale :

```bash
docker compose exec -T frontend npm run test:run -- \
  src/tests/admin.subscriptions-page.test.tsx \
  src/tests/subscription.service.test.ts \
  src/tests/subscription-pricing-form.test.tsx \
  src/tests/subscription-pricing-summary.test.tsx \
  src/tests/subscription-document.test.tsx \
  src/tests/subscription-dialog.test.tsx \
  src/tests/merchant.subscription-page.test.tsx \
  src/tests/admin.billing-documents-page.test.tsx \
  src/tests/billing-documents.service.test.ts \
  src/tests/subscription-payments.service.test.ts \
  src/tests/admin.marchands-page.test.tsx \
  src/tests/admin.merchant-drawer.test.tsx
```

## Parcours navigateur réel

Chromium headless / Playwright, frontend et API Docker locaux :

1. Connexion admin par le formulaire.
2. Ouverture des abonnements et création d'un aperçu pour un marchand existant.
3. Tarif annuel 600 TND, mois 1–3 à −100 %, mois 4–6 à −50 %.
4. Réponse serveur et écran : **375.000 TND**, détail de 12 mois.
5. Vérification sur ordinateur 1440×1000 et mobile 390×844 : dialogue défilant,
   action accessible, aucun débordement horizontal de la page.
6. Fermeture sans enregistrer : nombre d'abonnements inchangé, aucune erreur JS.

Le navigateur a permis de détecter puis de corriger la sélection de marchand
lorsque l'API omet `subscription_lifecycle` nul. Le cas est couvert par un test
qui a échoué avant la correction puis réussi après.

## Revues

Revue indépendante frontend : conformité validée ; corrections du défilement
des documents annuels, du verrou de fermeture pendant mutation, du parcours
clavier du lien de paiement et du focus lorsque tous les boutons sont désactivés.
Tous ces points sont clos.

Revue indépendante backend : aucun défaut bloquant identifié. Huit requêtes
isolées de types d'entrée invalides n'ont produit aucun 500. Le contrat distingue
400 pour désérialisation et 422 pour validation des règles commerciales.

## Hypothèses et limites

- Tarif annuel librement configurable ; pas de remise annuelle implicite
  supplémentaire. Les plages mensuelles ne se répètent pas chaque année.
- Nouvelles conditions au prochain renouvellement ; aucune migration forcée des
  contrats historiques, aucun abonnement réel changé pour le test navigateur.
- Émission des documents depuis l'admin, sans nouvelle facturation automatique.
- Création/programmation/émission/paiement couverts par tests API et composants ;
  le navigateur réel a volontairement testé l'aperçu sans créer de créance.
- La suite complète du dépôt et le build production n'ont pas été relancés.
- Concurrence PostgreSQL revue dans le code (verrou transactionnel à l'émission),
  sans test de charge simultané réel.
- `doctrine:schema:validate` échoue sur des écarts globaux préexistants : index et
  commentaires Doctrine, valeurs par défaut d'autres domaines, table de suivi
  Messenger et séquence documentaire hors mapping. Le dump SQL ne propose
  aucune correction de `pricing_versions` ni de `pricing_snapshot`. Aucun
  `schema:update --force` n'a été exécuté.
- Aucun déploiement distant effectué.
