# Roadmap MVP — Click & Collect Supérette Tunisie

## Objectif

Structurer le démarrage du MVP autour d'un socle produit clair, versionné et directement exploitable par le développement.

Le MVP doit permettre à une supérette tunisienne de proposer un parcours click & collect simple : accès par QR code, consultation du catalogue marchand, composition de la Kadhia, choix d'un rendez-vous, validation par le marchand, préparation puis retrait sécurisé.

## Principes MVP

- Le client accède à une supérette via un QR code magasin.
- Le client commande dans l'espace d'une supérette donnée.
- Le produit utilise le vocabulaire local, notamment la Kadhia.
- Le produit est conçu pour la Tunisie : TND, français, arabe, usages locaux.
- Le paiement en ligne et la livraison sont exclus du MVP initial.
- Le catalogue marchand s'appuie sur un référentiel produit global.

---

## Sprint 0 — Cadrage produit et socle documentaire ✅

### Objectif

Transformer la vision produit en documentation opérationnelle pour commencer le développement sans ambiguïté.

### Livrables

- PRD MVP.
- Règles métier MVP.
- Positionnement marché Tunisie.
- Modèle référentiel produit / catalogue marchand.
- Taxonomie produit initiale.
- Règles de normalisation produit.
- Sources de données produit.
- Modèle de données fonctionnel.
- Contrat API initial.
- UX de recherche produit.

### Critère de sortie

Le développement peut commencer lorsque les entités principales, les parcours MVP, les règles de commande et le modèle produit sont compréhensibles sans discussion orale supplémentaire.

---

## Sprint Auth — Authentification et compte client ✅ Backend terminé

### Objectif

Permettre à un client de créer un compte, se connecter et récupérer son mot de passe. Prérequis absolu de tout parcours client.

### Fonctionnalités

- Inscription client (`POST /api/auth/register/customer`).
- Connexion JWT (existant).
- Profil client : consultation et modification (`GET/PATCH /api/me/profile`).
- Réinitialisation de mot de passe par token opaque (`POST /api/auth/password-reset/request`, `POST /api/auth/password-reset/confirm`) avec alias documentés `forgot-password` / `reset-password`.

### User stories

- **US-034** — S'inscrire en tant que client
- **US-035** — Consulter et modifier son profil client
- **US-046** — Réinitialiser son mot de passe oublié

### Entités / migrations

- `User.firstName` et `User.lastName` ajoutés pour le profil client.
- Nouvelle entité `PasswordResetToken`.

### Critère de sortie

Un visiteur peut créer un compte client, se connecter, consulter et modifier son profil, puis retrouver l'accès à son compte après un mot de passe oublié. Les tokens de reset sont hashés, expirables et à usage unique.

---

## Sprint 1 — Référentiel produit et catalogue marchand ✅ (partiel)

### Objectif

Permettre au marchand de retrouver des produits existants et de construire son catalogue sans tout ressaisir.

### Fonctionnalités

- Créer le référentiel produit global.
- Créer les catégories produit.
- Créer les marques.
- Gérer les unités et volumes.
- Ajouter un produit du référentiel au catalogue marchand.
- Définir le prix marchand.
- Définir la disponibilité produit.
- Importer un seed CSV initial.
- **[NEW] Photos des produits** — champ `imageUrl` sur `ProductReference`, upload admin.

### User stories

- US-013 — Rechercher un produit dans le référentiel global
- US-014 — Ajouter un produit du référentiel à son catalogue
- US-015 — Définir le prix et la disponibilité d'un produit
- US-016 — Proposer un nouveau produit au référentiel
- **US-041** — Afficher les photos des produits dans le catalogue *(NEW)*

### Entités principales

- ProductReference (+ `imageUrl`).
- Brand.
- Category.
- ProductUnit (enum).
- MerchantProduct.
- Shop.

### Critère de sortie

Un marchand peut rechercher « Lait Vitalait 1L », voir sa photo, l'ajouter, fixer son prix à 2,800 TND et le rendre visible aux clients.

---

## Sprint 2 — Parcours client ✅ (partiel)

### Objectif

Permettre au client de scanner un QR code, consulter les produits d'une supérette et composer sa Kadhia.

### Fonctionnalités

- Accès à la supérette via QR code.
- **[NEW] Parcours client non connecté** — affichage catalogue sans login, invite à la connexion au moment de l'ajout à la Kadhia.
- Affichage des informations de la supérette.
- Consultation du catalogue marchand.
- Recherche produit.
- Filtrage par catégorie.
- Ajout au panier / Kadhia.
- Modification des quantités.
- Suppression d'un produit du panier.
- Choix d'un créneau de retrait.
- **[NEW] Message explicite si aucun créneau disponible.**
- Soumission de la commande.
- **[NEW] Numéro de commande lisible** (#0042).

### User stories

- US-001 — Scanner le QR code d'une supérette
- US-031 — Voir les informations de la supérette
- US-032 — Associer un client à une supérette
- US-033 — Rechercher une supérette
- US-002 — Consulter le catalogue marchand
- US-017 — Rechercher un produit par nom ou marque
- US-018 — Filtrer le catalogue par catégorie
- US-003 — Ajouter un produit à la Kadhia
- US-019 — Modifier la quantité ou retirer un produit
- US-020 — Récapitulatif de la Kadhia avec total TND
- US-004 — Choisir un créneau de retrait
- US-021 — Soumettre la commande
- **US-042** — Numéro de commande lisible *(NEW)*
- **US-044** — Parcours client non connecté *(NEW)*
- **US-048** — Message si aucun créneau disponible *(NEW)*

### Entités / migrations

- `Order` : ajouter `order_number` (séquentiel par supérette, UNIQUE).
- `Order` : ajouter `submitted_at`.
- `PickupSlot` : ajouter `timezone` (défaut `Africa/Tunis`).
- `KadhiaLine` : ajouter `name_fr`, `name_ar`, `brand` (snapshots).

### Critère de sortie

Un client peut scanner un QR code, voir les produits avec photos, composer une Kadhia et envoyer une demande de commande identifiée `#0042` au marchand — qu'il soit connecté dès le départ ou après.

---

## Sprint 3 — Parcours marchand core ✅ Backend terminé

### Objectif

Permettre au marchand de traiter les commandes depuis la réception jusqu'à la commande prête à retirer. Le retrait sécurisé et la finalisation restent Sprint 4.

### Fonctionnalités

- **Dashboard journalier** — vue synthétique des commandes du jour par statut.
- Liste des commandes soumises.
- Consultation du détail d'une commande avec coordonnées client.
- Acceptation d'une commande.
- Refus d'une commande avec raison.
- **Acceptation partielle** — sélection des lignes honorées.
- **Annulation par le client** — statut `submitted` uniquement.
- Passage en préparation.
- Passage en prêt à retirer.
- Traçabilité — entité `OrderStatusLog` avec horodatage à chaque transition.
- CRUD manuel des créneaux de retrait.

### User stories

- **US-051** — Dashboard journalier marchand
- US-022 — Consulter la liste des commandes soumises
- US-005 — Accepter ou refuser une commande
- **US-037** — Accepter partiellement une commande
- **US-036** — Annuler une commande (client)
- US-006 — Préparer une commande
- US-023 — Déclarer une commande prête
- **US-024** — Configurer les créneaux de retrait ponctuels
- **US-045** — Coordonnées client dans la commande marchand
- **US-040** — Historique des transitions de statut

### Entités / migrations

- `OrderStatusLog`.
- `OrderLine.prepared`.
- `PickupSlot` administrable côté marchand sur des créneaux ponctuels.

### Critère de sortie

Le marchand reçoit une commande, la traite depuis son dashboard (acceptation, refus, acceptation partielle, préparation, prêt), et le client peut annuler avant acceptation. Chaque transition de statut Sprint 3 est horodatée.

---

## Sprint 3b — Maturité opérationnelle marchand ✅ Backend terminé

### Objectif

Outiller le marchand pour gérer son activité quotidienne de façon autonome : créneaux, disponibilité catalogue, historique, gestion des délais automatiques et des fermetures.

> **Prérequis :** Sprint 3 core terminé.
> **Prérequis :** Sprint 3 core et Sprint 4 clôturés côté backend.
> La fondation documentaire est disponible dans `docs/Sprint3b/README.md` et `docs/Sprint3b/technical-readiness-report.md`.

### Fonctionnalités

- **Créneaux récurrents** — génération automatique sur 4 semaines.
- **Délai de réponse marchand** — annulation automatique si non traité avant 2h du créneau.
- **Expiration d'une acceptation partielle** — annulation si le client ne re-soumet pas avant 2h du créneau.
- **Ruptures de stock en masse** — action groupée sur le catalogue.
- **Historique complet des commandes** — tous statuts, filtres, pagination.
- **Fermeture exceptionnelle** — bloquer une plage sans supprimer les créneaux récurrents.
- **Heures d'ouverture** — affichage hebdomadaire sur la vitrine publique.

### User stories

- **US-047** — Créneaux récurrents
- **US-043** — Délai de réponse marchand
- **US-049** — Expiration d'une acceptation partielle
- **US-052** — Ruptures de stock en masse
- **US-053** — Historique complet marchand
- **US-056** — Fermeture exceptionnelle de la supérette
- **US-057** — Heures d'ouverture de la supérette

### Entités / migrations

- `PickupSlotRule` (nouvelle entité — créneaux récurrents).
- `ExceptionalClosure` (nouvelle entité — fermetures exceptionnelles).
- `Shop` : ajouter `openingHours` (JSONB).

### Note infrastructure

US-043 et US-049 reposent sur **Symfony Messenger avec workers persistants** (DelayStamp). Valider que l'infrastructure Messenger (transport, worker supervisé) est opérationnelle en début de sprint — si les workers ne tournent pas, les annulations automatiques échouent silencieusement.

### Découpage recommandé

1. S3B-001 — Créneaux récurrents foundation.
2. S3B-002 — Fermetures exceptionnelles.
3. S3B-003 — Heures d'ouverture supérette.
4. S3B-004 — Historique complet commandes marchand.
5. S3B-005 — Ruptures stock en masse.
6. S3B-006 — Délai réponse marchand automatique.
7. S3B-007 — Expiration acceptation partielle.
8. S3B-008 — Audit + clôture Sprint 3b ✅ Terminé — voir `docs/Sprint3b/README.md` pour le bilan.

### Critère de sortie

Le marchand complète la gestion opérationnelle au-delà des créneaux ponctuels déjà livrés : récurrence, fermeture exceptionnelle, catalogue en masse, historique complet. Les délais de réponse et d'expiration sont automatisés. Les heures d'ouverture sont visibles sur la vitrine client.

---

## Sprint 4 — Retrait sécurisé ✅ Backend terminé

### Objectif

Finaliser la remise avec un QR code de retrait, une double validation et des notifications aux deux parties.

### Fonctionnalités

- Génération du QR code de retrait (token `PickupSession`) lors du passage en `ready`.
- Affichage du QR code côté client via API.
- Scan marchand → passage en `pickup_pending`.
- Double validation client + marchand → `completed`.
- Force complétion marchand si le client ne répond pas dans les 5 minutes.
- **Notifications client in-app** — transitions clés (acceptée, prête, retirée, rappel, etc.).
- **Notifications marchand in-app** — nouvelle commande soumise.
- **Rappel de retrait** — notification 1 heure avant le créneau si commande `ready` ; planification livrée, contenu détaillé à enrichir.
- Suivi statut commande côté client par polling.

### User stories

- US-025 — Afficher le QR code de retrait (client)
- US-007 — Double validation retrait
- US-026 — Suivre le statut de sa commande
- US-038 — Notifications client *(NEW)*
- US-039 — Notifications marchand *(NEW)*
- **US-064** — Rappel de retrait avant expiration du créneau *(NEW)*

### Entités / migrations

- `PickupSession` (nouvelle entité).
- `Notification` (nouvelle entité).

### Critère de sortie ✅

Une commande `ready` peut être retirée avec un QR code, validée des deux côtés et finalisée. Le client reçoit un rappel 1 heure avant son créneau, avec un contenu encore générique à enrichir. Les notifications sont envoyées à chaque transition clé.

Limites : les notifications restent in-app uniquement, sans push/SMS/email/Mercure/WebSocket. Le rappel différé repose sur Symfony Messenger ; en production, il nécessite un transport async persistant et un worker actif. `sync://` ne suffit pas pour garantir un différé réel.

---

## Sprint 5 — Administration minimale ✅ Backend terminé

### Objectif

Permettre à l'opérateur de créer et gérer supérettes et marchands, et maintenir le référentiel produit.

### Fonctionnalités

- CRUD supérettes (admin) avec génération et téléchargement du QR code.
- **Admin store listing + detail** — lecture paginée des supérettes (`GET /api/admin/stores`, `GET /api/admin/stores/{storeId}`). ✅ S5-002
- **Admin store create + update** — création/modification de supérette (`POST /api/admin/stores`, `PATCH /api/admin/stores/{storeId}`), slug et QR générés automatiquement à la création. ✅ S5-003
- **Admin store QR regenerate + download contract** — lecture du contrat QR et régénération du token opaque (`GET /api/admin/stores/{storeId}/qr-code`, `POST /api/admin/stores/{storeId}/regenerate-qr`). ✅ S5-005
- **Photo et logo de la supérette** (admin et marchand).
- CRUD comptes marchands (admin) — création, suspension, activation. ✅ S5-004 livré
- **Foundation admin access + Merchant admin listing** — lecture paginée des marchands (`GET /api/admin/merchants`, `GET /api/admin/merchants/{merchantId}`). ✅ S5-001
- **Merchant admin mutations** — création, mise à jour, suspension et activation (`POST /api/admin/merchants`, `PATCH /api/admin/merchants/{id}`, `PATCH .../suspend`, `PATCH .../activate`). ✅ S5-004
- CRUD Brand et Category (admin).
- CRUD ProductReference (admin) — création directe, correction, archivage.
- Validation des propositions de produits des marchands (existant).
- **QR code téléchargeable par le marchand** depuis son backoffice.
- **Onboarding guidé** à la première connexion du marchand (thème → catalogue → créneaux → QR).

### User stories

- US-009 — Créer et gérer les supérettes (admin) *(complétée)*
- US-028 — Gérer les comptes marchands *(mutations livrées S5-004 — lecture + création + PATCH + suspension + activation)* ✅
- US-029 — Superviser le référentiel produit global
- US-030 — Valider les propositions de nouveaux produits
- **US-050** — Photo et logo de la supérette
- **US-054** — Onboarding marchand guidé
- **US-055** — QR code téléchargeable par le marchand

### Entités / migrations

- `Shop` : ajouter `logoUrl`, `coverUrl`.
- `User` : ajouter `onboardingCompletedAt`.

### Critère de sortie

L'admin crée une supérette avec son QR code et son logo, active un marchand. Le marchand se connecte, complète l'onboarding et télécharge son QR code pour l'imprimer.

---

## Sprint 6 — Personnalisation visuelle ✅ (implémenté)

### Objectif

Permettre à l'administrateur de définir le thème visuel par défaut, et à chaque marchand de personnaliser l'identité visuelle de sa supérette.

### Fonctionnalités

- Thème global admin (5 couleurs + police).
- Thème supérette marchand (surcharge du thème global).
- Variables CSS exposées via API publique.
- Avertissement contraste WCAG 2.1 AA.

### User stories

- US-010 — Configurer le thème global (admin)
- US-011 — Personnaliser le thème de la supérette
- US-012 — Afficher le storefront avec le thème actif

### Critère de sortie ✅

La PWA client reflète le thème de la supérette via `GET /api/stores/{storeId}/theme`.

---

## Sprint 7 — Production et localisation 🟡 P2

### Objectif

Préparer la mise en production avec observabilité, localisation FR/AR et outils de support.

### Fonctionnalités

- **Localisation FR/AR/RTL** — sélecteur de langue, support RTL, persistance préférence.
- **PWA installable et mode hors ligne** — manifest, service worker, cache catalogue, Kadhia hors ligne.
- **Accessibilité WCAG 2.1 AA** — navigation clavier, lecteurs d'écran, contraste, cibles tactiles. *(contrainte transversale : à intégrer dès le début du développement frontend, pas uniquement en Sprint 7)*
- **Conservation des données / RGPD** — suppression de compte client livrée côté backend (`DELETE /api/me/account`), politique de rétention documentée ; purge automatique reportée hors PR S7-003.
- **Fermeture définitive d'une supérette** — archivage, annulation commandes actives, révocation QR code.
- **Export CSV des commandes** par le marchand.
- **Audit trail admin** — journal des actions critiques de l'administrateur.
- Observabilité (logs structurés, métriques, alertes).
- Analytics MVP (commandes/jour, taux d'acceptation, créneaux utilisés).
- Outils de support opérateur (recherche commande admin, log d'activité).

### User stories

- **US-008** — Basculer la langue de l'interface FR/AR *(complétée)*
- **US-058** — Fermeture définitive d'une supérette
- **US-059** — PWA installable et mode hors ligne
- **US-060** — Accessibilité WCAG 2.1 AA
- **US-061** — Export données commandes marchand (CSV)
- **US-062** — Politique de conservation et suppression des données *(backend S7-003 livré : suppression compte client + anonymisation minimale)*
- **US-063** — Audit trail des actions admin

### Entités / migrations

- `Shop` : ajouter `archivedAt`, `archiveReason`.
- `User` : `deletedAt`, `lastLoginAt` ajoutés par S7-003.
- `AdminAuditLog` (nouvelle entité).

### Critère de sortie

La plateforme est opérable et supervisée en production par une équipe réduite. L'interface bascule entre français et arabe avec support RTL complet. L'application est installable sur mobile, accessible WCAG 2.1 AA et conforme aux exigences minimales de protection des données. L'admin peut archiver une supérette, le marchand exporter ses commandes, chaque action admin critique est tracée.

---

## Qualité — Catalogue des scénarios à tester

> **TODO owner :** Détailler et implémenter tous les scénarios ci-dessous dans `tests/Functional/Scenario/` avant de considérer le backend comme stabilisé.
>
> Les tests d'endpoint existants (`tests/Functional/Api/`) vérifient chaque route isolément.
> Les tests de scénario enchaînent plusieurs appels HTTP réels sans setup BDD artificiel pour les transitions de statut — ils détectent les régressions transversales que les tests unitaires ratent.

### Parcours CLIENT — 22 scénarios

| # | Scénario | Statuts traversés |
|---|---|---|
| C-01 | Première découverte supérette via QR code → relation CustomerShop créée | — |
| C-02 | Retour sur supérette connue → `lastSeenAt` mis à jour | — |
| C-03 | Recherche supérette par nom | — |
| C-04 | Recherche supérette par ville | — |
| C-05 | Marquer supérette en favori | — |
| C-06 | Masquer une supérette | — |
| C-07 | Composer une Kadhia : ajout, modification quantité, suppression ligne | `draft` |
| C-08 | Soumission nominale → commande créée | `draft → submitted` |
| C-09 | Tentative soumission Kadhia vide → 422 `KADHIA_EMPTY` | — |
| C-10 | Tentative soumission produit indisponible → 422 `PRODUCT_UNAVAILABLE` | — |
| C-11 | Tentative soumission créneau plein → 422 `PICKUP_SLOT_FULL` | — |
| C-12 | Tentative soumission créneau expiré → 422 `PICKUP_SLOT_EXPIRED` | — |
| C-13 | Tentative soumission créneau couvert par fermeture exceptionnelle → 422 `PICKUP_SLOT_CLOSED` | — |
| C-14 | Annuler commande avant acceptation | `submitted → cancelled` |
| C-15 | Tentative annulation après acceptation → 409 | — |
| C-16 | Suivi statut commande par polling | — |
| C-17 | Commande acceptée → notification in-app reçue | — |
| C-18 | Commande refusée → notification in-app reçue | — |
| C-19 | Commande prête → notification + QR de retrait disponible | — |
| C-20 | Acceptation partielle → Kadhia repasse `draft` → client re-soumet | `submitted → partially_accepted → submitted` |
| C-21 | Présenter QR retrait → double validation → commande finalisée | `ready → pickup_pending → completed` |
| C-22 | Rappel retrait 1h avant créneau si commande `ready` | — |

### Parcours MARCHAND — 18 scénarios

| # | Scénario | Statuts traversés |
|---|---|---|
| M-01 | Dashboard journalier → comptage par statut | — |
| M-02 | Accepter une commande soumise | `submitted → accepted` |
| M-03 | Refuser une commande avec raison | `submitted → rejected` |
| M-04 | Accepter partiellement une commande | `submitted → partially_accepted` |
| M-05 | Passer en préparation + marquer lignes préparées | `accepted → preparing` |
| M-06 | Marquer commande prête | `preparing → ready` |
| M-07 | Scanner QR retrait client | `ready → pickup_pending` |
| M-08 | Confirmer la remise côté marchand (client déjà confirmé) | `pickup_pending → completed` |
| M-09 | Force completion si client ne répond pas dans 5 min | `pickup_pending → completed` |
| M-10 | CRUD créneaux ponctuels | — |
| M-11 | Créer règle créneau récurrent → génération automatique 4 semaines | — |
| M-12 | Créer fermeture exceptionnelle → slots désactivés dans la plage | — |
| M-13 | Ruptures de stock en masse | — |
| M-14 | Consulter historique commandes (filtres statut, date, pagination) | — |
| M-15 | Personnaliser le thème visuel supérette | — |
| M-16 | Configurer heures d'ouverture | — |
| M-17 | Délai réponse automatique : commande annulée si non traitée avant 2h du créneau | `submitted → cancelled` |
| M-18 | Expiration acceptation partielle : annulée si client ne re-soumet pas avant 2h du créneau | `partially_accepted → cancelled` |

### Parcours ADMIN — 5 scénarios

| # | Scénario |
|---|---|
| A-01 | CRUD supérettes + génération QR code |
| A-02 | CRUD marchands (création, suspension, activation) |
| A-03 | CRUD Brand, Category, ProductReference |
| A-04 | Valider une proposition produit marchand → ProductReference créé |
| A-05 | Configurer le thème global de la plateforme |

### Contrôle d'accès — 5 scénarios

| # | Scénario | Code attendu |
|---|---|---|
| AC-01 | Client tente une route marchand | 403 |
| AC-02 | Marchand tente une route admin | 403 |
| AC-03 | Marchand accède à une commande d'une autre supérette | 403 / 404 |
| AC-04 | Requête non authentifiée sur toute route protégée | 401 |
| AC-05 | Ressource inexistante (UUID inconnu) | 404 |

**Total : 50 scénarios.** Chaque fichier de scénario dans `tests/Functional/Scenario/` doit : (1) créer les acteurs via les helpers `FunctionalApiTestCase`, (2) enchaîner les appels HTTP réels, (3) récupérer les IDs depuis les réponses précédentes, (4) vérifier l'état final en base.

---

## Hors périmètre MVP

- Paiement en ligne.
- Livraison à domicile.
- Programme de fidélité avancé.
- Gestion multi-entrepôts.
- Marketplace multi-marchands avec panier partagé.
- Géolocalisation obligatoire.
- Application mobile native.
- Notation / avis sur les supérettes.
- Push mobile / SMS / email / Mercure-WebSocket (notifications via polling dans le MVP).
- Réouverture d'une session de retrait expirée (admin).

---

## Synthèse des user stories par sprint

| Sprint | US | Priorité | Statut |
|---|---|---|---|
| Sprint 0 | Documentation | — | ✅ Complet |
| Sprint Auth | US-034, US-035, US-046 | P0 | ✅ Backend terminé |
| Sprint 1 | US-013 à US-016, US-041 | P0 | ✅ Partiel (US-041 manquante) |
| Sprint 2 | US-001 à US-004, US-017 à US-021, US-031 à US-033, US-042, US-044, US-048 | P0 | ✅ Partiel (3 US manquantes) |
| Sprint 3 | US-005, US-006, US-022, US-023, US-024, US-036, US-037, US-040, US-045, US-051 | P0 | ✅ Backend terminé |
| Sprint 3b | US-043, US-047, US-049, US-052, US-053, US-056, US-057 | P1 | ✅ Backend terminé |
| Sprint 4 | US-007, US-025, US-026, US-038, US-039, US-064 | P1 | ✅ Backend terminé |
| Sprint 5 | US-009, US-028, US-029, US-030, US-050, US-054, US-055 | P1 | ✅ Backend terminé |
| Sprint 6 | US-010, US-011, US-012 | P1 | ✅ Complet |
| Sprint 7 | US-008, US-058, US-059, US-060, US-061, US-062, US-063 | P2 | 🟡 À implémenter |
