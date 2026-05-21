# AI Context — Click & Collect Supérette Tunisie

Ce fichier est la base commune pour Claude, Codex et tout autre agent IA utilisé sur le projet.

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

- Backend MVP : 100 % (Sprint 5 audité et clôturé — S5-012 livré).
- Produit terrain testable : environ 95 %.
- Sprint 6 (personnalisation visuelle) : implémenté côté backend (`PlatformTheme`, `ShopTheme`, thème public par supérette).
- Sprint 7 démarré : S7-001 livré — `PATCH /api/admin/stores/{storeId}/archive`, champs `Shop.archivedAt`/`Shop.archiveReason`, annulation des commandes actives, 19 tests. S7-003 livré côté backend — `DELETE /api/me/account`, champs `User.deletedAt`/`User.lastLoginAt`, anonymisation minimale du compte client, invalidation des `PasswordResetToken`, blocage login des comptes supprimés, commandes conservées pour l'historique marchand.
- Prochaine priorité recommandée : S7-002 (export CSV commandes), S7-004 (audit trail admin) ou frontend MVP (Next.js).
- 952 tests backend passants, PHPStan niveau 8 clean, CS Fixer clean.

## Limites connues

Limites Sprint 4 : le rappel de retrait 1h utilise Symfony Messenger et `DelayStamp`. Un vrai différé en production nécessite un transport async persistant et un worker actif ; les notifications restent in-app, sans push mobile, SMS, email ni Mercure/WebSocket dans le MVP backend actuel. Le contenu du rappel US-064 reste générique et doit encore intégrer le nom de la supérette, l'heure du créneau et le numéro de commande. Après scan, la confirmation client et la force completion ne bloquent plus sur le TTL, mais la confirmation marchand conserve encore un contrôle d'expiration côté processor. Le MVP ne prévoit pas de réouverture admin d'une session expirée et les confirmations simultanées ne sont pas sérialisées par un `SELECT FOR UPDATE` dédié.

Limites Sprint 5 : slug pas protégé contre race condition (faible risque admin-only ; une contrainte `UNIQUE` en base est recommandée avant charge élevée). S5-005 retourne `target_url` en chemin relatif — un QR imprimé nécessite une URL absolue composée côté interface. S5-008 n'expose pas `created_product_reference_id` quand null (propriété nullable exclue de la sérialisation JSON par API Platform par défaut). S5-008 : le statut `merged` de `ProductReferenceProposalStatus` est orphelin — l'endpoint `POST /merge` a été supprimé au profit de `PATCH /approve` + `productReferenceId` ; des enregistrements `merged` existants en base resteraient cohérents dans la lecture mais ne peuvent plus être créés ; prévoir un script one-shot de migration (`merged` → `approved`) avant passage en production si nécessaire. S5-008 : la collection `/api/admin/product-proposals` est paginée mais ne retourne pas de total (`X-Total-Count` / `hydra:totalItems`) — à ajouter dans une prochaine itération. S5-011 : le critère `qr_code` est toujours satisfait dès qu'un shop actif existe (`qrCodeToken` non-nullable) — redondant avec `store_profile` ; le label a été renommé "Accéder au QR code" pour refléter cette réalité. Le critère `theme` ne prend pas en compte le `PlatformTheme` (singleton toujours présent) : seul un `ShopTheme` explicitement configuré satisfait l'étape. `PATCH /complete` est idempotent (200) ; aucun prérequis de complétion des étapes n'est vérifié avant de l'accepter. Le calculateur exécute 1 + 4N requêtes pour un marchand avec N shops actifs ; impact négligeable dans le MVP (quasi-tous les marchands ont 1 shop). Les labels `step.label` sont en français uniquement — le frontend doit utiliser `step.key` comme clé i18n pour le support de l'arabe. Le backend ne couvre pas encore l'email d'invitation, le billing, l'analytics, l'upload ou le CSV export.

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

Entités présentes dans `apps/backend/src/Entity/` :

- `Shop`
- `CustomerShop`
- `Category`
- `Brand`
- `ProductReference`
- `ProductReferenceProposal`
- `MerchantProduct` (anciennement `MerchantProductOffer`)
- `OpenDataProduct`
- `Kadhia`
- `KadhiaLine`
- `PickupSlot`
- `PickupSlotRule`
- `ExceptionalClosure`
- `Order`
- `OrderLine`
- `OrderStatusLog`
- `PasswordResetToken`
- `PickupSession`
- `Notification`
- `PlatformTheme`
- `ShopTheme`
- `User` (`deletedAt` pour soft delete client, `lastLoginAt` alimenté après login JWT)

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
