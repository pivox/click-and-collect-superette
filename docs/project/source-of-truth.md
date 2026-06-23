# Source de vérité projet — Click & Collect Supérette Tunisie

Dernière vérification documentaire : 2026-06-23

## Vision

Kadhia est une application de click & collect pour les supérettes tunisiennes.
Le client scanne le QR code d'une supérette, consulte le catalogue, prépare sa
Kadhia, choisit un rendez-vous de retrait, soumet sa commande, puis récupère ses
courses après validation du marchand.

Le produit reste mobile-first, bilingue français / arabe, localisé pour la
Tunisie et exprimé en TND.

## Rôles

- Client : consulte une supérette, prépare une Kadhia, choisit un rendez-vous,
  suit la commande et valide le retrait.
- Marchand : gère sa supérette, son catalogue, ses créneaux, les commandes, la
  préparation et la remise au client.
- Admin : gère la plateforme, les marchands, les supérettes, le référentiel
  produit, l'exploitation, le support, l'abonnement et le suivi commercial.

## Parcours métier principal

1. Le client scanne le QR code magasin.
2. L'application ouvre l'espace de la supérette.
3. Le client consulte le catalogue et ajoute des produits à sa Kadhia.
4. Le client choisit un rendez-vous de retrait.
5. Le client soumet la commande.
6. Le marchand valide, refuse ou accepte partiellement.
7. Le marchand prépare la commande et la marque prête.
8. Le client présente le QR code ou le code de retrait.
9. Le client et le marchand valident la remise.
10. La commande est finalisée.

## Architecture générale

- `apps/frontend/` : application Next.js unique pour les espaces client,
  marchand et admin. Les expériences client et marchand sont PWA web
  installables ; les apps natives restent post-lancement et soumises à une gate
  terrain.
- `apps/backend/` : API Symfony 7 / API Platform, logique métier, sécurité par
  rôle, Doctrine/PostgreSQL, Messenger pour l'asynchrone.
- `docs/` : documentation produit, architecture, QA, roadmap et sprints.

## Domaines fonctionnels existants

- Authentification et profils client, marchand, admin.
- Accès supérette par QR code magasin.
- Catalogue public, référentiel produit et catalogue marchand.
- Kadhia, soumission, suivi commande et historique.
- Créneaux, horaires et fermetures exceptionnelles.
- Traitement marchand : acceptation, refus, acceptation partielle, préparation,
  commande prête.
- Retrait sécurisé par QR/code et double validation.
- Notifications in-app et Web Push.
- Backoffice admin : marchands, supérettes, référentiel, audit, ops, métriques,
  activation.
- Monétisation marchand : abonnement, document mensuel interne non fiscal,
  paiement manuel, relances, suspension douce et réactivation.
- Onboarding catalogue : import CSV, recherche code-barres, groupements produit.
- Valeur commerciale : statistiques marchand, promotions simples, CRM léger.

## Hiérarchie documentaire officielle

1. [Source de vérité projet](./source-of-truth.md) : point d'entrée général et
   règles de précédence.
2. [Roadmap active Sprint 14+](../Sprint14/README.md) : planification active.
3. [Synthèse stratégique lancement](../roadmap/launch-readiness-reorganization.md)
   : lecture PO / Tech Lead et gates de lancement.
4. [Audit fonctionnel MVP](../product/mvp-functional-audit.md) : état
   Documenté / Codé / Testé / Contrat API / Statut.
5. [Contrat API](../architecture/api-contract.md) : routes publiques et
   protégées documentées.
6. Audits QA datés, dont [audit #527](../qa/mvp-audit-527.md) : photographies
   historiques à ne pas transformer en roadmap.
7. README et documents de sprint : contexte, décisions et historique.

`docs/product/mvp-roadmap.md` est uniquement un index court vers la roadmap
active. Ne pas recréer `docs/roadmap/mvp-roadmap.md`.

## Règle de précédence

En cas de contradiction :

1. Code et tests présents sur la branche courante pour l'état technique réel.
2. Contrat API pour les routes publiques documentées.
3. Roadmap active pour la planification.
4. Audit fonctionnel pour l'état de couverture.
5. Audits QA datés comme photographies historiques.
6. Anciennes documentations de sprint uniquement pour l'historique.

La fermeture d'une issue GitHub n'est pas une preuve technique suffisante : elle
doit être croisée avec le code, les tests, les PR ou une documentation de
clôture.

## Emplacements de référence

- Roadmap active : [docs/Sprint14/README.md](../Sprint14/README.md).
- Synthèse stratégique : [docs/roadmap/launch-readiness-reorganization.md](../roadmap/launch-readiness-reorganization.md).
- Contrat API : [docs/architecture/api-contract.md](../architecture/api-contract.md).
- Audit fonctionnel : [docs/product/mvp-functional-audit.md](../product/mvp-functional-audit.md).
- Audit QA #527 : [docs/qa/mvp-audit-527.md](../qa/mvp-audit-527.md).

## Limites produit importantes

- Pas de paiement en ligne dans le MVP.
- Pas de livraison dans le MVP.
- Pas de programme de fidélité avancé dans le MVP.
- Pas de marketplace multi-marchands avec panier partagé dans le MVP.
- Pas d'app native avant preuve terrain et limites PWA constatées.
- Facebook Messenger est optionnel, best-effort et post-lancement ou
  conditionnel tant qu'aucune décision PO explicite ne le rend bloquant.
- WhatsApp semi-manuel reste un fallback opérationnel possible, sans API
  WhatsApp Business automatisée.
- Le document mensuel marchand est interne non fiscal tant que le cadrage fiscal
  tunisien n'est pas validé.

## Maintenance documentaire

Pour toute évolution de périmètre :

1. Vérifier le code et les tests sur la branche courante.
2. Vérifier les issues GitHub en lecture seule si leur statut est mentionné.
3. Mettre à jour le contrat API si une route publique ou protégée change.
4. Mettre à jour la roadmap active pour la planification.
5. Mettre à jour l'audit fonctionnel si le statut Documenté / Codé / Testé /
   Contrat API change.
6. Préserver les audits datés : ajouter un suivi post-audit plutôt que réécrire
   l'observation historique.
7. Garder `docs/product/mvp-roadmap.md` comme index et ne pas recréer
   `docs/roadmap/mvp-roadmap.md`.
