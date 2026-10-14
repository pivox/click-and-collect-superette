# AI Context — Click & Collect Supérette Tunisie

Ce fichier est la base commune pour Claude, Codex et tout autre agent IA utilisé sur le projet.

Point d'entrée documentaire courant : `docs/project/source-of-truth.md`.
Roadmap active : `docs/Sprint14/README.md`.
Contrat API : `docs/architecture/api-contract.md`.
Audit fonctionnel : `docs/product/mvp-functional-audit.md`.

> **À arbitrer — le pointeur « roadmap active » ne couvre plus le plus gros chantier ouvert.**
> La chaîne documentaire est cohérente avec elle-même : `docs/roadmap/launch-readiness-reorganization.md`
> et `docs/project/source-of-truth.md` désignent tous deux `docs/Sprint14/README.md`. Mais ce cadrage
> est daté du 2026-06-07 (dernière révision de contenu 2026-06-23), alors que l'épic **CATALOG-AI
> #637-#649** (13 issues, import photo IA marchand + enrichissement du référentiel) est aujourd'hui
> le plus gros chantier ouvert. Sa documentation existe — `docs/architecture/catalog-photo-import.md`
> et `docs/product/catalog-photo-import-multi-ai.md`, tous deux de 2026-10-02 — mais n'est référencée
> ni ici, ni dans la chaîne ci-dessus. Décider si la roadmap active reste Sprint 14 ou bascule sur
> CATALOG-AI relève du PO, pas d'une passe d'audit : le pointeur est donc laissé tel quel.
>
> À noter aussi : `docs/` ne contient **pas** de dossier `Sprint8`, `Sprint9`, `Sprint10`, `Sprint12`
> ni `Sprint16`, alors que ce fichier déclare Sprint 10 et Sprint 16 livrés. Les livraisons sont
> étayées par les issues GitHub fermées, pas par un dossier de sprint.

## Produit

Application de click & collect pour les supérettes en Tunisie.

Le client scanne le QR code d'une supérette, accède au catalogue du magasin, prépare sa **Kadhia**, choisit un créneau de retrait, puis récupère sa commande après validation du marchand.

## Langues, devise et localisation

- Langues produit : français et arabe.
- L'arabe doit pouvoir être affiché en RTL.
- Devise : TND, dinar tunisien.
- Les libellés, dates, heures et montants doivent rester cohérents avec un usage tunisien.

## Périmètre MVP strict

Inclus :

- QR code magasin ;
- catalogue produit simple ;
- Kadhia / panier ;
- choix de rendez-vous ;
- soumission de commande ;
- validation ou refus par le marchand ;
- préparation ;
- commande prête ;
- QR code de retrait ;
- double validation client + marchand ;
- personnalisation visuelle par supérette (couleurs + police, Sprint 6) ;
- historique simple ;
- interface français / arabe ;
- montants en TND.

Exclus du MVP :

- paiement en ligne ;
- livraison ;
- programme de fidélité avancé ;
- marketplace multi-marchands avec panier partagé ;
- gestion de stock complexe multi-entrepôts.

## Parcours métier principal

1. Le client scanne le QR code de la supérette.
2. L'application ajoute ou ouvre la supérette dans l'espace client.
3. Le client consulte le catalogue.
4. Le client ajoute des produits à sa Kadhia.
5. Le client choisit un créneau de retrait.
6. Le client soumet la commande.
7. Le marchand valide, refuse ou accepte partiellement.
8. Le marchand prépare.
9. La commande passe à prête.
10. Le client présente le QR code de retrait.
11. Le marchand et le client valident la remise.
12. La commande est finalisée.

## État backend livré

- Sprint Auth : terminé côté backend (inscription client, login JWT, profil client, reset password).
- Sprint 3 : terminé côté backend (traitement marchand core, créneaux ponctuels, historique de statuts, dashboard journalier).
- Sprint 4 : terminé côté backend (QR de retrait, `PickupSession`, scan marchand, `pickup_pending`, double validation, force completion, notifications in-app, suivi statut client, rappel retrait 1h).
- Sprint 3b : terminé côté backend. PRs #91–#102 livrées ; PR #102 clôture officiellement le sprint (audit + documentation). Endpoints : pickup-slot-rules (CRUD + génération), exceptional-closures (CRUD), opening-hours (public + marchand), orders/history (filtres + pagination), products/bulk-availability. Automatisations Messenger : expiration délai réponse marchand (→ cancelled si submitted avant startsAt-2h), rappel acceptation partielle (notification à startsAt-4h), expiration acceptation partielle (→ cancelled si partially_accepted avant startsAt-2h). Limites : notifications in-app uniquement, transport async persistant requis en production.
- Sprint 5 : terminé côté backend (S5-001 à S5-011). S5-001 PR #103 (lecture admin marchands). S5-002 PR #104 (lecture admin supérettes). S5-004 PR #106 (mutations admin marchands : POST, PATCH, suspend, activate). S5-003 PR #107 (création/modification admin supérettes). S5-005 (contrat QR admin + régénération token). S5-006 PR #112 (CRUD admin catégories, 5 endpoints `/api/admin/categories`). S5-006b PR #115 (CRUD admin marques : `Brand`, 5 endpoints `/api/admin/brands`). S5-007 PR #116 (CRUD admin référentiel produit : `ProductReference`, 5 endpoints `/api/admin/product-references` dont archive). S5-008 PR #117 (validation admin propositions : `GET /api/admin/product-proposals`, `GET /{id}`, `PATCH /{id}/approve`, `PATCH /{id}/reject`, avec 409 sur doublon de traitement). S5-009 PR #118 (logo et cover URL sur `Shop` : `logoUrl`/`coverUrl` nullable max 2048 chars, exposés en lecture admin + publique via `StoreByQrOutput` et `StorePublicOutput`, modifiables via PATCH admin). S5-010 (QR marchand : `GET /api/merchant/stores/{storeId}/qr-code`, lecture seule propriétaire). S5-011 (onboarding marchand guidé : `GET /api/merchant/onboarding`, `PATCH /api/merchant/onboarding/complete`, champ `User.onboardingCompletedAt`). Restent hors Sprint 5 : génération image/PDF QR côté interface, email d'invitation (reportés Sprint 6+).

## Avancement global

- État au 23 juin 2026 : cœur MVP livré sur `main`; la roadmap active à partir de Sprint 14 prépare une V1 de lancement officiel, pas une bêta publique fragile.
- **Audit du 14 octobre 2026 — ce qui a été revérifié, et ce qui ne l'a pas été.**
  Vérifié contre le dépôt et contre GitHub : l'état des issues ouvertes et fermées ; la liste
  des entités Doctrine (24 documentées sur 61 réelles, corrigé) ; les 10 statuts de commande
  (exacts) ; le niveau PHPStan déclaré (exact) ; l'existence de tous les endpoints, commandes
  console, classes, migrations, codes d'erreur et routes frontend cités par ce fichier — aucun
  n'est introuvable ; et les 17 limites de la section « Limites connues », confrontées une par
  une au code. Résultat sur ces 17 : 6 étaient fausses, 3 partiellement, 1 l'était depuis sa
  rédaction, 7 restent exactes.
  **Non revérifié, à ne pas prendre pour acquis** : « Backend MVP : 100 % » n'a aucune base de
  calcul explicitée et n'est pas vérifiable par lecture de code ; les résultats d'exécution
  (« 0 erreur », « 0 diff », `lint:container` OK, `doctrine:schema:validate`) n'ont pas été
  relancés, faute de démon Docker et parce qu'AGENTS.md interdit les vérifications projet via
  `php`/`composer` hôte ; les numéros de PR cités (#91-#102, #130-#132, etc.) n'ont pas été
  confrontés à l'historique git ; les comptages de tests sont statiques, pas des exécutions ;
  les mentions « livré » côté frontend n'ont pas été réauditées écran par écran.
- Backend MVP : 100 % (Sprint 7 audité et clôturé — S7-008 livré ; S7-009 transport Messenger persistant livré).
- Produit terrain testable : très avancé — parcours client complet validé par simulation Playwright historique (rapport `docs/qa/client-journey-simulation-report-v2.md`) et frontend marchand avancé livré. Les parcours live client/marchand/admin restent à rejouer avant go/no-go.
- Sprint 6 (personnalisation visuelle) : implémenté côté backend (`PlatformTheme`, `ShopTheme`, thème public par supérette).
- Sprint 7 terminé côté backend : S7-001 (archive supérette), S7-002 (export CSV marchand), S7-003 (suppression compte client), S7-004 (audit trail admin), S7-005 (observabilité production / healthcheck). PHPStan clean, CS Fixer clean. « 81 tests Sprint 7 » était un chiffre historique : le comptage statique des 5 classes concernées donne **78** au 14 octobre 2026 (`AdminAuditLogApiTest` 28, `StoreArchiveAdminApiTest` 21, `MerchantOrderExportApiTest` 20, `CustomerDeleteAccountApiTest` 7, `HealthCheckApiTest` 2). Pour information, la suite backend compte **1 865** méthodes `public function test` au total.
- Frontend admin backoffice livré : PRs #130–#132 — auth admin, référentiel produits, marchands, supérettes, audit logs, dashboard KPI.
- Frontend client livré : parcours inscription → catalogue → Kadhia → suivi commande fonctionnel, notifications client, QR de retrait, confirmation client, thèmes supérette, liste/sélection supérettes, i18n FR/AR avec RTL client (S14-004 / #401).
- Frontend marchand livré : login, dashboard, commandes actives, détail et actions jusqu'à `ready`, retrait sécurisé, historique, notifications, catalogue, créneaux/règles/fermetures/horaires, onboarding, QR magasin, thème/apparence, paramètres profil/compte/langue et export CSV.
- Images produits web/mobile livrées : S13-005 / #391 (upload admin, original conservé, variantes WebP responsive, fallback JPEG, placeholder catégorie, exposition API).
- Sprint 10 livré : #352 validation worker async en production (runbook + checklist), #353 monitoring jobs (`GET /api/admin/ops/messenger`), #354 KPI terrain (`GET /api/admin/beta-metrics`), #355 QR magasin PNG/PDF côté marchand, #356 checklist d'activation supérette (`GET /api/admin/stores/{storeId}/activation-checklist` + badge admin), #357 journal opérationnel marchand minimal sur la fiche marchand admin (`ops_journal`).
- Sprint 14 mobile : #374 PWA client, #375 PWA marchand, #376 Web Push et #379 accessibilité minimum sont fermées GitHub et représentées dans le dépôt. Validation terrain installée / Lighthouse / audit WCAG complet non relancés dans la passe documentaire du 23 juin.
- Sprint 15 business/exploitation : #361 document mensuel interne non fiscal, #364 suspension douce/réactivation et #365 import CSV + recherche code-barres fermées GitHub et représentées dans le dépôt. Le scan caméra mobile n'est pas retenu comme preuve livrée par cette passe.
- Sprint 16 valeur commerciale : #380 statistiques marchand, #384 promotions simples et #385 CRM léger marchand fermées GitHub et représentées dans le dépôt.
- Parmi les issues historiquement listées ici comme ouvertes, seule **#527** (suivi global épinglé) l'est encore. Les autres ont été tranchées le 30 septembre 2026 : **#378** WhatsApp semi-manuel fermée complétée (PR #609) ; **#543** sécurisation `FRONTEND_URL` fermée complétée (PR #596, comportement documenté par environnement dans `docs/ops/frontend-url.md`) ; **#490** spike Facebook Messenger fermé sur un **NO-GO MVP** (PR #631), ce qui a entraîné le report puis la fermeture de #491-#494. Le canal Messenger n'est donc pas « optionnel en attente » : il est écarté du MVP.
- Chantiers réellement ouverts à date (19 issues ouvertes) : épic **#637-#649** CATALOG-AI (import photo IA marchand + enrichissement du référentiel admin), **#636** recherche produit instantanée FR/AR avec Meilisearch, **#386-#389** applications natives Android/iOS client et marchand, et **#527** le suivi global.
- **La liste faisant foi des issues ouvertes est `docs/project/open-issues.yml`**, vérifiée en CI contre l'API GitHub par `scripts/ci/check-declared-state.php` (workflow `declared-state.yml`). Ouvrir ou fermer une issue implique de mettre ce fichier à jour, sinon la CI échoue — dans les deux sens : une issue déclarée ouverte mais fermée, comme une issue ouverte non déclarée. La ligne ci-dessus est la version narrative, elle peut dériver ; le fichier YAML, non. Procédure : `docs/project/declared-state-guardrail.md`.
- Épic ORDER-LEAD #569 (délai minimal configurable avant retrait) : livrée fin septembre 2026 via PRs empilées #586 (entité `ShopOrderingPolicy` + API marchand `GET/PATCH /api/merchant/stores/{storeId}/ordering-policy`), #587 (`PickupSlotEligibilityChecker`, filtrage listing public + `booking_policy` additif + revalidation à la soumission `PICKUP_SLOT_MINIMUM_LEAD_TIME_NOT_MET`, exemption resoumission partielle même créneau), #588 (écran marchand `/merchant/parametres/delai-retrait`, FR/AR) et #589 (UX client créneaux : info délai, état vide expliqué, écran périmé avec Kadhia conservée, télémétrie `clientLog`). Défaut 0 = comportement historique inchangé.
- Épic MERCHANT-TEAM #568 (plusieurs comptes par organisation marchande) : codée fin septembre 2026 via PRs empilées #590 (socle `MerchantOrganization`/`MerchantMembership`, backfill idempotent `app:merchant-organizations:backfill`, audit `app:merchant-organizations:audit`), #591 (abonnement/CRM/facturation portés par l'organisation, route additive `GET /api/admin/merchant-organizations`), #592 (autorisation par membership active avec révocation effective à la requête suivante, `/api/merchant/me` additif), #593 (invitations/renvoi/révocation des comptes secondaires réservés au compte principal, quota 10), #594 (notifications multi-comptes avec état lu par compte, auteur des transitions `OrderStatusLog.actor_*`) et #595 (écran Équipe `/merchant/parametres/equipe` FR/AR, bloc organisation dans le détail admin marchand, auteur dans l'historique marchand). Ordre de déploiement : migrations → backfill → audit. Le fallback owner reste actif pour les boutiques non backfillées.
- S7-009 livré : transport Messenger persistant (`doctrine://default`), `failure_transport`, `retry_strategy`, migration `messenger_messages`, config Supervisor — R1 et R2 résolus.
- Prochaine priorité recommandée : rejouer les parcours terrain client, marchand et admin avant go/no-go, maintenir #527, et arbitrer l'épic CATALOG-AI #637-#649 qui est désormais le plus gros chantier ouvert. #378 et le canal Messenger sont tranchés : il n'y a plus rien à reporter de ce côté.
- Documentation API OpenAPI exposée publiquement : `docs_formats` configuré dans `api_platform.yaml` (`json`, `html`, `jsonopenapi`, `yamlopenapi`) — endpoints accessibles sans auth sur `/api/docs.json`, `/api/docs.html`, `/api/docs.jsonopenapi`, `/api/docs.yamlopenapi`.
- PHPStan **niveau 6** — vérifié, `apps/backend/phpstan.neon` ligne 2 déclare bien `level: 6`. Attention, les appels sur types nullable ne sont pas détectés à ce niveau : revue manuelle nécessaire lors d'un passage nullable. En revanche « 0 erreur », « CS Fixer : 0 diff » et « `lint:container` : OK » sont des résultats d'exécution, non revérifiés par la passe du 14 octobre 2026 — à relancer avant de s'y fier (`make lint-backend`).
- **Contradiction documentaire à trancher** : `docs/Sprint7/completion-report.md` (lignes 124 et 227) annonce « PHPStan niveau 8 clean », alors que `phpstan.neon` est à `level: 6`. Soit le niveau a été abaissé sans trace, soit le rapport Sprint 7 est faux. `docs/production/readiness-checklist.md` demande d'ailleurs le niveau 8 avant déploiement.

## Limites connues

Limites Sprint 4 : le rappel de retrait 1h utilise Symfony Messenger et `DelayStamp`. ~~Un vrai différé en production nécessite un transport async persistant et un worker actif~~ — résolu S7-009 : transport Doctrine persistant + config Supervisor. ~~Les notifications restent in-app, sans push mobile, SMS, email ni Mercure/WebSocket~~ — partiellement résolu : le Web Push est livré et câblé (`WebPushService` injecté dans `NotificationService:63`) et le push mobile l'est aussi (`SendMobilePushMessage` routé en `async` dans `messenger.yaml:29`, handler + `ExpoPushSender`, `dispatchMobilePush()` appelé sur chaque notification). Restent réellement absents : SMS, Mercure/WebSocket, et l'email sur le cycle de vie commande — l'email n'est utilisé que pour l'invitation marchand, le reset de mot de passe et les relances de paiement. Attention : sans les variables `VAPID_*`, `WebPushService` ignore silencieusement l'envoi. Le rappel US-064 intègre désormais le nom de la supérette et l'heure du créneau (fuseau `Africa/Tunis`), en FR et en AR (`NotificationService::notifyCustomerPickupReminder`). Il n'intègre toujours **pas** le numéro de commande, alors que `Order::orderNumber` existe. Après scan, la confirmation client et la force completion ne bloquent plus sur le TTL, mais la confirmation marchand conserve encore un contrôle d'expiration côté processor. Le MVP ne prévoit pas de réouverture admin d'une session expirée et les confirmations simultanées ne sont pas sérialisées par un `SELECT FOR UPDATE` dédié.

Limites Sprint 5 : ~~slug pas protégé contre race condition ; une contrainte `UNIQUE` en base est recommandée~~ — la contrainte existait déjà quand cette phrase a été écrite : `Version20260507120000:23` crée `UNIQUE INDEX UNIQ_6F6A974989D9B62 ON shops (slug)`. La race condition subsiste en revanche, et l'index la transforme en **500 au lieu de 409** : `CreateAdminStoreProcessor:88` génère le slug en lire-puis-écrire et ne capture pas `UniqueConstraintViolationException`, alors que 9 autres processors du projet le font. Risque faible (admin-only), correctif simple. S5-005 retourne `target_url` en chemin relatif côté **admin** (`AdminStoreQrOutputFactory`, `/api/stores/by-qr/%s`) : un QR imprimé depuis l'admin nécessite une URL absolue composée côté interface. Côté **marchand** c'est résolu, S5-010 compose une URL absolue depuis `FRONTEND_URL` (`MerchantStoreQrTargetUrlFactory`). À arbitrer : les deux chemins divergent, `/api/stores/by-qr/` côté admin contre `/stores/by-qr/` côté marchand, donc un QR admin et un QR marchand ne pointent pas vers la même page. S5-008 n'expose pas `created_product_reference_id` quand null (propriété nullable exclue de la sérialisation JSON par API Platform par défaut). ~~S5-008 : le statut `merged` de `ProductReferenceProposalStatus` est orphelin — l'endpoint `/merge` a été supprimé ; prévoir un script one-shot de migration (`merged` → `approved`) avant passage en production~~ — **FAUX, corrigé le 14 octobre 2026 : ne pas exécuter ce script.** L'endpoint existe, en `PATCH` et non en `POST` : `PATCH /api/admin/product-proposals/{proposalId}/merge` (`src/ApiResource/AdminProductProposalOutput.php:61`, avec `AdminMergeProductProposalInput` et `AdminMergeProductProposalProcessor`), couvert par `tests/Functional/Api/AdminProductProposalValidationApiTest.php`. `case Merged = 'merged'` est bien présent dans `src/Enum/ProductReferenceProposalStatus.php:12` et reste atteignable. Migrer `merged` → `approved` écraserait donc des données valides et produites par un endpoint actif. ~~S5-008 : la collection `/api/admin/product-proposals` est paginée mais ne retourne pas de total~~ — résolu : `AdminProductProposalListOutput:54` expose `public int $total`, alimenté par `ProductReferenceProposalRepository::countFiltered()` (`AdminProductProposalCollectionProvider:61`). C'est un champ propre au DTO, ni `X-Total-Count` ni `hydra:totalItems`. S5-011 : le critère `qr_code` est toujours satisfait dès qu'un shop actif existe (`qrCodeToken` non-nullable) — redondant avec `store_profile` ; le label a été renommé "Accéder au QR code" pour refléter cette réalité. Le critère `theme` ne prend pas en compte le `PlatformTheme` (singleton toujours présent) : seul un `ShopTheme` explicitement configuré satisfait l'étape. `PATCH /complete` est idempotent (200) ; aucun prérequis de complétion des étapes n'est vérifié avant de l'accepter. Le calculateur exécute 1 + 4N requêtes pour un marchand avec N shops actifs ; impact négligeable dans le MVP (quasi-tous les marchands ont 1 shop). Les labels `step.label` sont en français uniquement — le frontend doit utiliser `step.key` comme clé i18n pour le support de l'arabe. ~~Le backend ne couvre pas encore l'email d'invitation, le billing, l'analytics, l'upload ou le CSV export~~ — **faux sur les cinq points**, c'était la phrase la plus trompeuse de cette section. Email d'invitation : `MerchantInvitationEmailSender`, injecté dans 4 processors, entité `MerchantInvitationToken`. Billing : entités `Subscription`, `BillingDocument`, `SubscriptionPayment`, `SubscriptionPaymentReminder`, namespace `src/Billing/`, endpoints `GET /api/admin/subscriptions` et `GET /api/admin/billing-documents`. Analytics : `MerchantStatisticsProvider`, `AdminBetaMetricsProvider`. Upload : `Service/ProductImage/*` avec deux contrôleurs d'entrée. Export CSV : `GET /api/merchant/stores/{storeId}/orders/export.csv`.

Limites Sprint 7 : S7-004 — `ip_address` dans `admin_audit_logs` n'est fiable que si `trusted_proxies` est configuré côté Symfony ; sinon peut contenir l'IP du proxy. ~~Extension PostgreSQL `unaccent` non migrée~~ — résolu PR #152 + migration `Version20260526100000`. ~~Transport Messenger `sync://`~~ — résolu S7-009 : transport `doctrine://default` persistant, `failure_transport`, `retry_strategy`, table `messenger_messages`, config Supervisor. ~~Table `product_import_raw` orpheline en base, à nettoyer manuellement avant production~~ — résolu : la migration `Version20260528072120` (2026-09-30) supprime la contrainte FK, l'index, la colonne `product_references.source_import_raw_id` puis la table, dans l'ordre imposé par PostgreSQL et avec gardes `IF EXISTS`. Aucune action manuelle requise, il suffit que les migrations soient appliquées. ~~PWA, push et accessibilité WCAG restent ouverts~~ — PWA et push livrés, WCAG réellement ouvert. PWA : `@ducanh2912/next-pwa` dans `next.config.mjs`, worker `worker/index.ts`, manifestes client et marchand ; limite documentée dans le fichier, un seul service worker au scope `/` partagé entre les deux PWA. Accessibilité : aucune dépendance axe, a11y, pa11y ni Lighthouse dans `apps/frontend/package.json`, aucun test a11y, et `src/app/layout.tsx:35` est figé sur `<html lang="fr">` sans `dir`/`lang` dynamique à la racine — ce qui est un vrai problème pour le RTL arabe annoncé comme livré. ~~Frontend client : `GET /api/me/stores/{shopId}/kadhias` retourne 405~~ — endpoint fonctionnel (GET et POST tous deux exposés). ~~i18n AR câblé~~ — livré côté client via S14-004 / #401 ; le marchand dispose aussi d'un contexte langue FR/AR léger.

## Statuts de commande

- `draft`
- `submitted`
- `accepted`
- `partially_accepted` — le marchand a accepté une partie des lignes ; la Kadhia repasse en `draft` pour que le client la modifie et la re-soumette
- `rejected`
- `preparing`
- `ready`
- `pickup_pending`
- `completed`
- `cancelled`

## Entités métier de référence

**61 entités** dans `apps/backend/src/Entity/`, toutes portant `#[ORM\Entity]`.
Liste vérifiée contre le dossier le 14 octobre 2026 ; regroupée par domaine pour
la lisibilité, pas par arborescence de fichiers (ils sont tous à plat).

Supérette, thème et politique de commande :
`Shop`, `CustomerShop`, `ShopTheme`, `PlatformTheme`,
`ShopOrderingPolicy` (délai minimal avant retrait, épic ORDER-LEAD #569 ; défaut 0 = comportement historique)

Référentiel produit partagé :
`Category`, `Brand`, `ProductReference`, `ProductReferenceProposal`,
`ProductReferenceMergeHistory`, `ProductImage`, `OpenDataProduct`,
`ProductFamily` (regroupe plusieurs formats d'un même produit : `baseNameFr`, `baseNameAr`, `brand` nullable, `category` nullable, `country` TN),
`ProductGroup`, `ProductGroupItem`, `ProductPack`, `ProductPackItem`

Offre marchand (prix, disponibilité et visibilité propres au marchand) :
`MerchantProduct` (anciennement `MerchantProductOffer`), `MerchantLocalProduct`,
`MerchantProductPriceHistory`, `MerchantCategory`

Kadhia :
`Kadhia`, `KadhiaLine`, `KadhiaMember`, `KadhiaShareLink`

Créneaux et retrait :
`PickupSlot`, `PickupSlotRule`, `ExceptionalClosure`, `PickupSession`

Commande :
`Order`, `OrderLine`, `OrderStatusLog` (porte aussi l'auteur de la transition via `actor_*`)

Organisation marchande (épic MERCHANT-TEAM #568) :
`MerchantOrganization`, `MerchantMembership`, `MerchantInvitationToken`

Abonnement et facturation :
`Subscription`, `SubscriptionPayment`, `SubscriptionPaymentReminder`, `BillingDocument`

CRM marchand :
`MerchantCrmProfile`, `MerchantCrmContact`

Comptes et authentification :
`User` (`deletedAt` pour soft delete client, `lastLoginAt` alimenté après login JWT),
`PasswordResetToken`, `RefreshToken`, `SocialIdentity`, `SocialAuthFlow`

Notifications et canaux :
`Notification`, `PushSubscription`, `MobileDevice`

Import photo et enrichissement IA :
`CatalogPhotoImportSession`, `CatalogPhotoImportImage`, `CatalogPhotoQuotaEntry`,
`CatalogPhotoQuotaGrant`, `ProductAiEnrichmentJob`, `AiBudgetPolicy` (plafonne la dépense IA)

Exploitation, audit et support :
`AdminAuditLog` (journal append-only des actions admin critiques : `action`, `resourceType`, `resourceId`, `summary`, `metadata`, `ipAddress`, `userAgent`, `createdAt`),
`Incident`, `IncidentNote`, `FeedbackEntry`, `FeedbackSetting`

Divers client :
`CustomerProductFavorite`

## Stack cible recommandée

- API : Symfony 7 + API Platform.
- Base de données : PostgreSQL.
- ORM : Doctrine.
- Asynchrone : Symfony Messenger.
- Cache / file légère : Redis si nécessaire.
- Web temps réel : Mercure ou WebSocket selon le besoin.
- Front client : PWA mobile-first.
- Backoffice marchand : web responsive.

## Règles produit à respecter

- Ne jamais remplacer le terme **Kadhia** par un panier générique sans garder le vocabulaire métier.
- Ne pas ajouter de paiement en ligne dans le MVP sans décision explicite.
- Ne pas ajouter de livraison dans le MVP sans décision explicite.
- Les produits doivent pouvoir exister dans un référentiel commun, puis être proposés par marchand avec prix, disponibilité et visibilité propres.
- Le QR code magasin sert d'abord à ouvrir ou ajouter une supérette à la liste du client.
- Le retrait doit rester sécurisé par une double validation.

## Règles de travail IA

- Toujours commencer par lire ce fichier, puis `README.md`, puis la documentation utile dans `docs/`.
- Préférer de petits changements atomiques.
- Ne pas mélanger cadrage produit, architecture et code dans le même changement sauf demande explicite.
- Toute modification doit lister les fichiers changés, les hypothèses et les risques.
- Pour le code Symfony, préférer des services testables, des DTO clairs, des migrations Doctrine et des tests automatisés.
- Pour les documents produit, écrire en français clair, orienté MVP et décision.
