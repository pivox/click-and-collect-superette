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

## Sprint 7 — Production et localisation ✅ Backend livré / Frontend partiel

### Objectif

Préparer la mise en production avec observabilité, localisation FR/AR et outils de support.

### Bilan (audit S7-008 — 2026-05-27)

| US | Titre | Statut |
|---|---|---|
| US-058 | Fermeture définitive d'une supérette | ✅ Backend livré (S7-001) |
| US-061 | Export CSV commandes marchand | ✅ Backend livré (S7-002) |
| US-062 | Conservation et suppression des données | ✅ Backend livré (S7-003) |
| US-063 | Audit trail des actions admin | ✅ Backend livré (S7-004) |
| US-065 | Observabilité production | ✅ Backend livré (S7-005) |
| US-059 | PWA installable et mode hors ligne | ❌ Non livré — Sprint 14 (#374, #375) |
| US-060 | Accessibilité WCAG 2.1 AA | ❌ Non livré — Sprint 14 (#379) |
| US-008 | Localisation FR/AR câblée dans l'app | ✅ Livré côté client via S14-004 / #401 (#377 fermée) |

Frontend admin backoffice livré hors sprint officiel (PRs #130–#132) : auth admin, référentiel produits, marchands, supérettes, audit logs, dashboard KPI.

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

### Entités / migrations livrées

- `Shop` : `archivedAt`, `archiveReason` (migration `Version20260521100000`).
- `User` : `deletedAt`, `lastLoginAt` (migration `Version20260521110000`).
- `AdminAuditLog` (migration `Version20260521120000`).

### Critère de sortie ⚠️ Partiel

Backend : ✅ — 5 US livrées, 81 tests fonctionnels, PHPStan niveau 8 clean, CS Fixer clean.
Frontend : ✅ — parcours client, backoffice admin et front marchand avancés livrés. PWA, push et WCAG restent ouverts Sprint 14. i18n client FR/AR + RTL livré via S14-004 / #401.
Production : ⚠️ — risques bloquants documentés (extension `unaccent`, transport Messenger async, drift schéma) à résoudre avant déploiement.

Rapport de clôture complet : `docs/Sprint7/completion-report.md`.

---

> **Sprints 8 et 9 — livrés sur `main`** (voir `CLAUDE.md`) : catalogue marchand (produits locaux, bulk multi-format, `ProductFamily`, `pack_quantity`) et Kadhia multi + UX. Le cœur MVP (Sprints 0–9) est en place. Les sprints suivants couvrent la **mise sur le marché** : bêta terrain, monétisation, support, catalogue scalable, mobile, croissance, natif.
>
> **État produit au 4 juin 2026** : **EPIC-015 — Fiabilité & observabilité production** est livré (#352, #353, #354). Dans EPIC-016, le QR magasin imprimable (#355) et la checklist d'activation supérette (#356 via PR #412) sont livrés. Sprint 10 est clôturable ; #357 est à reporter et #358 à fermer comme non nécessaire.
>
> **Note suivi GitHub** : `#381` est la PR de consolidation de cette roadmap, pas une user story. La séquence des tickets US passe donc de `#380` à `#382`.

---

## Sprint 10 — Durcissement bêta + observabilité

### Objectif

Rendre l'application fiable pour une bêta avec 3 à 5 supérettes réelles.

### Fonctionnalités

- **US-067** ([#352](https://github.com/pivox/click-and-collect-superette/issues/352)) · EPIC-015 · *Must* · Livré — Validation production du worker async : runbook et checklist d'exploitation livrés, en complément de la config Supervisor S7-009.
- **US-068** ([#353](https://github.com/pivox/click-and-collect-superette/issues/353)) · EPIC-015 · *Should* · Livré — Monitoring des jobs asynchrones livré via `GET /api/admin/ops/messenger` : santé worker, file Messenger, échecs, ancienneté du plus vieux message et dernier traitement.
- **US-069** ([#354](https://github.com/pivox/click-and-collect-superette/issues/354)) · EPIC-015 · *Must* · Livré — Métriques bêta livrées via `GET /api/admin/beta-metrics` : commandes, taux d'acceptation, complétion, annulation et activation par supérette.
- **US-070** ([#355](https://github.com/pivox/click-and-collect-superette/issues/355)) · EPIC-016 · *Must* · Livré — QR magasin imprimable livré côté marchand : aperçu, téléchargement PNG et PDF.
- **US-071** ([#356](https://github.com/pivox/click-and-collect-superette/issues/356)) · EPIC-016 · *Must* · Livré — Checklist d'activation supérette livrée via PR #412 : endpoint admin `GET /api/admin/stores/{storeId}/activation-checklist` et badge admin `Prête` / `Incomplète`.
- **US-072** ([#357](https://github.com/pivox/click-and-collect-superette/issues/357)) · EPIC-016 · *Could* · À reporter — Journal opérationnel marchand (vue minimale), non bloquant pour Sprint 10 ; à rattacher au sprint support/exploitation terrain où il complète incidents, notes internes et vue santé.
- **US-073** ([#358](https://github.com/pivox/click-and-collect-superette/issues/358)) · EPIC-016 · *Must initial* · À fermer comme non nécessaire — La décision bêta est tranchée par les livraisons FR/AR : i18n client + RTL (#401) et préférence langue marchand FR/AR (#395). La bêta peut partir en FR+AR sur les parcours livrés ; aucun développement Sprint 10 supplémentaire n'est requis.

### Critère de sortie

Sprint 10 est clôturable : worker async supervisé et monitoré, KPI mesurés, QR imprimable livré, supérettes validées par checklist avant activation. Les tickets encore ouverts ne bloquent pas la clôture : #357 relève du support terrain ultérieur ; #358 doit être fermé comme décision devenue obsolète.

---

## Sprint 11 — Activation commerciale + abonnement

### Objectif

Transformer l'application en produit monétisable. Modèle : 3 mois gratuits → 3 mois à 10 DT/mois → 50 DT/mois (saut ×5 à valider produit). La règle « pas de paiement en ligne » du MVP concerne le paiement **client de la commande**, pas l'abonnement **plateforme marchand**.

### Fonctionnalités

- **US-074** ([#359](https://github.com/pivox/click-and-collect-superette/issues/359)) · EPIC-017 · *Must* · Fondation backend livrée — Module abonnement marchand (entité `Subscription`) avec lecture admin/marchand.
- **US-075** ([#360](https://github.com/pivox/click-and-collect-superette/issues/360)) · EPIC-017 · *Must* · Fondation backend livrée — Statuts séparés : **lifecycle** (`active`/`payment_due`/`grace_period`/`suspended`/`cancelled`) et **phase tarifaire** (`trial`/`promo`/`standard`).
- **US-076** ([#361](https://github.com/pivox/click-and-collect-superette/issues/361)) · EPIC-017 · *Must* — Reçu / facture mensuelle — **cadrage fiscal préalable** documenté dans `docs/Sprint11/US-076-recu-facture-mensuelle-marchand.md` (matricule, TVA, timbre) avant le modèle de données.
- **US-077** ([#362](https://github.com/pivox/click-and-collect-superette/issues/362)) · EPIC-017 · *Must* — Paiement manuel (espèces / virement) + validation admin.
- **US-078** ([#363](https://github.com/pivox/click-and-collect-superette/issues/363)) · EPIC-018 · *Must* — Relances paiement par email — **inclut l'infra email** (`symfony/mailer` à installer ; l'envoi actuel est `@mail()` natif).
- **US-079** ([#364](https://github.com/pivox/click-and-collect-superette/issues/364)) · EPIC-018 · *Should* — Suspension douce et réactivation (conserve catalogue/historique/images, bloque les nouvelles commandes).
- **US-080** ([#365](https://github.com/pivox/click-and-collect-superette/issues/365)) · EPIC-019 · *Must* — Import CSV + scan code-barres (onboarding catalogue minimum) — **remonté du Sprint 13** car levier de conversion essai → payant.

### Critère de sortie

Fondation US-074/US-075 livrée : un marchand peut porter un abonnement lisible avec phase tarifaire calculée gratuit → promo → standard et lifecycle séparé. Le critère complet Sprint 11 reste ouvert tant que facturation, paiement manuel, relances et suspension douce ne sont pas livrés.

---

## Sprint 12 — Support & exploitation terrain

### Objectif

Donner à l'admin les outils pour gérer les problèmes réels sans toucher à la base.

### Fonctionnalités

- **US-085** ([#366](https://github.com/pivox/click-and-collect-superette/issues/366)) · EPIC-021 · *Should* — Incidents commande (module structuré, entité `Incident`).
- **US-086** ([#367](https://github.com/pivox/click-and-collect-superette/issues/367)) · EPIC-021 · *Should* — Backoffice support (consultation, filtres, note interne, clôture).
- **US-087** ([#368](https://github.com/pivox/click-and-collect-superette/issues/368)) · EPIC-021 · *Could* — Journal opérationnel marchand complet + vue santé ; cible naturelle du report de #357.
- **US-088** ([#369](https://github.com/pivox/click-and-collect-superette/issues/369)) · EPIC-021 · *Could* — Process manuel d'exploitation terrain (runbook support).

### Critère de sortie

L'équipe gère les problèmes terrain (incidents, retards, suspensions) avec des outils dédiés et des procédures écrites.

---

## Sprint 13 — Catalogue intelligent & qualité

### Objectif

Accélérer l'onboarding produit au-delà du CSV/scan (livré en Sprint 11) et garder un référentiel illustré, propre et gouverné. Socle déjà présent : images produits web/mobile (S13-005 / #391), bulk multi-format (Sprint 8), infra IA `ProductAiEnrichment*`, merge proposition→référence (PR #203).

### Fonctionnalités

- **US-041** ([#391](https://github.com/pivox/click-and-collect-superette/issues/391)) · EPIC-011 · *Must* — Gestion optimisée des images produits web/mobile : upload admin, original conservé, variantes WebP 200/400/800/1200, fallback JPEG, placeholder catégorie, exposition API responsive. **Livré S13-005 côté code/docs** ; l'issue GitHub #391 est encore ouverte au 4 juin 2026 et doit être triée manuellement. Détails dans `docs/roadmap/product-images-web-mobile.md`.
- **US-081** ([#370](https://github.com/pivox/click-and-collect-superette/issues/370)) · EPIC-019 · *Could* — Import catalogue par photo assisté IA (réutilise l'infra `ProductAiEnrichment*`).
- **US-082** ([#371](https://github.com/pivox/click-and-collect-superette/issues/371)) · EPIC-020 · *Should* — Déduplication du référentiel (workflow admin, priorité code-barres).
- **US-083** ([#372](https://github.com/pivox/click-and-collect-superette/issues/372)) · EPIC-020 · *Could* — Score de qualité des références produit.
- **US-084** ([#373](https://github.com/pivox/click-and-collect-superette/issues/373)) · EPIC-020 · *Should* — Gouvernance du référentiel (rôles, workflow, droits).

### Critère de sortie

Un marchand crée un catalogue exploitable sans saisir produit par produit ; le référentiel reste propre, gouverné et illustré avec des images produit optimisées pour le web/mobile.

---

## Sprint 14 — PWA + arabe/RTL + notifications

### Objectif

Transformer le web responsive en vraie expérience mobile installable. PWA (US-059), WCAG (US-060) et i18n AR (US-008) étaient reportés post-Sprint 7.

### Fonctionnalités

- **US-089** ([#374](https://github.com/pivox/click-and-collect-superette/issues/374)) · EPIC-022 · *Must* — PWA client (installable, mobile-first). **Ouvert.**
- **US-090** ([#375](https://github.com/pivox/click-and-collect-superette/issues/375)) · EPIC-022 · *Must* — PWA marchand (installable, terrain). **Ouvert.**
- **US-091** ([#376](https://github.com/pivox/click-and-collect-superette/issues/376)) · EPIC-022 · *Should* — Push notifications (client + marchand). **Ouvert.** *Limite : Web Push iOS seulement sur Safari 16.4+ et PWA installée → peut justifier l'iOS natif plus tôt.*
- **US-093** ([#377](https://github.com/pivox/click-and-collect-superette/issues/377)) · EPIC-008 · *Must* — **Arabe / RTL câblé** dans l'application (dette MVP, étend US-008). **Livré via #401 ; #377 fermée.**
- **US-094** ([#378](https://github.com/pivox/click-and-collect-superette/issues/378)) · EPIC-018 · *Could* — WhatsApp semi-manuel (client + marchand).
- **US-092** ([#379](https://github.com/pivox/click-and-collect-superette/issues/379)) · EPIC-022 · *Should* — Accessibilité minimum (WCAG de base). **Ouvert.**

### Critère de sortie

Le client utilise l'application en français comme en arabe (RTL). Le reste du sprint reste ouvert : PWA client, PWA marchand, push notifications et accessibilité de base.

---

## Sprint 15 — Croissance commerciale

### Objectif

Augmenter usage, rétention et valeur pour les marchands.

### Fonctionnalités

- **US-095** ([#380](https://github.com/pivox/click-and-collect-superette/issues/380)) · EPIC-023 · *Should* — Statistiques marchand avancées.
- **US-096** ([#382](https://github.com/pivox/click-and-collect-superette/issues/382)) · EPIC-023 · *Could* — Packs produits.
- **US-097** ([#383](https://github.com/pivox/click-and-collect-superette/issues/383)) · EPIC-023 · *Could* — Suggestions de Kadhia (souvent achetés / récents / favoris / remplacement).
- **US-098** ([#384](https://github.com/pivox/click-and-collect-superette/issues/384)) · EPIC-023 · *Should* — Promotions simples (prix barré, échéance).
- **US-099** ([#385](https://github.com/pivox/click-and-collect-superette/issues/385)) · EPIC-023 · *Should* — Suivi commercial (CRM léger des marchands).

### Critère de sortie

La plateforme aide les marchands à vendre plus et l'équipe à les retenir.

---

## Sprint 16 — Apps natives

### Objectif

Industrialiser **après preuve terrain** (clients commandent, marchands utilisent, catalogue gérable, facturation OK, limites PWA constatées). Ordre : Android marchand → Android client → iOS client → iOS marchand (conditionnel).

### Fonctionnalités

- **US-100** ([#386](https://github.com/pivox/click-and-collect-superette/issues/386)) · EPIC-024 · *Should* — App native Android marchand.
- **US-101** ([#387](https://github.com/pivox/click-and-collect-superette/issues/387)) · EPIC-024 · *Should* — App native Android client.
- **US-102** ([#388](https://github.com/pivox/click-and-collect-superette/issues/388)) · EPIC-024 · *Could* — App native iOS client.
- **US-103** ([#389](https://github.com/pivox/click-and-collect-superette/issues/389)) · EPIC-024 · *Could* — App native iOS marchand (si besoin confirmé).

### Critère de sortie

Les apps natives reprennent les parcours validés par la PWA, sans réinventer le produit, une fois la traction terrain prouvée.

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
| Sprint 1 | US-013 à US-016, US-041 | P0 | ✅ Complet (US-041 livrée S13-005) |
| Sprint 2 | US-001 à US-004, US-017 à US-021, US-031 à US-033, US-042, US-044, US-048 | P0 | ✅ Partiel (3 US manquantes) |
| Sprint 3 | US-005, US-006, US-022, US-023, US-024, US-036, US-037, US-040, US-045, US-051 | P0 | ✅ Backend terminé |
| Sprint 3b | US-043, US-047, US-049, US-052, US-053, US-056, US-057 | P1 | ✅ Backend terminé |
| Sprint 4 | US-007, US-025, US-026, US-038, US-039, US-064 | P1 | ✅ Backend terminé |
| Sprint 5 | US-009, US-028, US-029, US-030, US-050, US-054, US-055 | P1 | ✅ Backend terminé |
| Sprint 6 | US-010, US-011, US-012 | P1 | ✅ Complet |
| Sprint 7 | US-008, US-058, US-059, US-060, US-061, US-062, US-063 | P2 | ✅ Backend livré ; PWA/WCAG reportées Sprint 14 ; i18n client livrée S14-004 |
| Sprint 10 | US-067 à US-073 (EPIC-015, EPIC-016) | P0 | ✅ Clôturable : #352-#356 livrées ; #357 à reporter, #358 à fermer comme non nécessaire |
| Sprint 11 | US-074 à US-080 (EPIC-017, EPIC-018, EPIC-019) | P0 | 🟡 Partiel : fondation US-074/US-075 livrée |
| Sprint 12 | US-085 à US-088 (EPIC-021) | P1 | 🔵 Planifié (support) |
| Sprint 13 | US-041 (#391 / S13-005), US-081 à US-084 (EPIC-011, EPIC-019, EPIC-020) | P1 | 🟢 Partiel : US-041 livrée, reste catalogue scalable planifié |
| Sprint 14 | US-089 à US-094 (EPIC-022, EPIC-008, EPIC-018) | P1 | 🟡 Partiel : US-093 livrée ; PWA/push/WCAG ouverts |
| Sprint 15 | US-095 à US-099 (EPIC-023) | P2 | 🔵 Planifié (croissance) |
| Sprint 16 | US-100 à US-103 (EPIC-024) | P2 | 🔵 Planifié (natif) |

### Sprint 13 — Catalogue intelligent & qualité

- **#391 / S13-005 — Gestion optimisée des images produits web/mobile** : réactivation
  d'US-041. Upload admin (`POST /api/admin/product-references/{id}/image`), original
  conservé, variantes WebP 200/400/800/1200, fallback JPEG, placeholder catégorie,
  exposition API responsive (catalogue public + détail admin). Pipeline image commun
  réutilisable (`ProductImageApplicationService`) appelable par l'enrichissement IA
  comme image `candidate` / `needs_review` sans jamais devenir officielle automatiquement.
  Détails : `docs/roadmap/product-images-web-mobile.md`.
