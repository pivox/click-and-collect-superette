# Audit fonctionnel MVP — Click & Collect Supérette Tunisie

## Objectif

Ce document consolide l'état fonctionnel du MVP en croisant :

- la vision produit ;
- les user stories ;
- la roadmap ;
- le contrat API ;
- les endpoints backend déjà implémentés.

Il sert de point de contrôle avant de lancer de nouvelles PR backend afin d'éviter les régressions dues à des documents obsolètes ou contradictoires.

## Source de vérité roadmap

La roadmap de référence est :

```text
docs/roadmap/mvp-roadmap.md
```

Le fichier `docs/product/mvp-roadmap.md` est conservé comme index court et doit pointer vers la roadmap principale.

## Statuts utilisés

| Statut | Signification |
| --- | --- |
| `OK` | Fonctionnellement cadré et backend présent. |
| `PARTIEL` | Une partie existe, mais le parcours n'est pas complet. |
| `MANQUANT` | Bloc attendu MVP non cadré ou non codé. |
| `A_ALIGNER` | Document ou contrat à corriger pour refléter le code réel. |
| `A_DECIDER` | Décision produit nécessaire avant développement. |

## Synthèse globale

| Bloc fonctionnel | Documenté | Backend | Tests | Contrat API | Statut | Décision / action |
| --- | --- | --- | --- | --- | --- | --- |
| Login JWT | Oui | Oui | Oui | Oui | OK | Garder `/api/auth/login`. |
| Inscription client | **Oui (US-034)** | Oui | Oui | **Oui** | OK | `POST /api/auth/register/customer` livré; rôle forcé `ROLE_CUSTOMER`, email normalisé, password hashé. |
| Profil client | **Oui (US-035)** | Oui | Oui | **Oui** | OK | `GET/PATCH /api/me/profile` livré; accès `ROLE_CUSTOMER`, champs sensibles non exposés. |
| Mot de passe oublié | **Oui (US-046)** | Oui | **Oui** | **Oui** | OK | `POST /api/auth/password-reset/request` + `POST /api/auth/password-reset/confirm` livrés; alias `forgot-password` / `reset-password` exposés; entité `PasswordResetToken` créée. |
| Inscription marchand publique | Non | Non | Non | Non | A_DECIDER | Créé par admin uniquement dans le MVP (US-028). |
| Lecture marchands admin | **Oui (US-028)** | Oui | Oui | Oui | OK | S5-001 livré : `GET /api/admin/merchants` et `GET /api/admin/merchants/{merchantId}`. Les mutations marchands sont livrées via S5-004. |
| Lecture supérettes admin | **Oui (US-009)** | Oui | Oui | **Oui** | OK | S5-002 livré : `GET /api/admin/stores` et `GET /api/admin/stores/{storeId}`. |
| Création / modification supérettes admin | **Oui (US-009)** | Oui | Oui | Oui | OK | S5-003 livré : `POST /api/admin/stores` et `PATCH /api/admin/stores/{storeId}`. Slug et QR générés à la création. |
| Création / gestion marchands admin | **Oui (US-009, US-028)** | Oui | Oui | Oui | OK | S5-004 livré : création, mise à jour, suspension et activation marchands. |
| QR code store | Oui | Oui | Oui | Oui | OK | Token opaque `qr_code_token`. |
| Recherche store | Oui | Oui | Oui | Oui | OK | Garder `GET /api/stores/search`. |
| Relation client/store | Oui | Oui | Oui | Oui | OK | Garder `/api/me/stores/*`. |
| Catalogue public store | Oui | Oui | Oui | Oui | OK | Garder `/api/stores/{storeId}/catalog`. |
| Référentiel produit marchand | Oui | Oui | Oui | Oui | OK | Garder `/merchant/stores/{storeId}/product-references`. |
| Catalogue marchand | Oui | Oui | Oui | Oui | OK | Garder endpoints catalogue marchand réels. |
| Kadhia multiple | Oui | Oui | Oui | Oui | OK | Garder `/api/me/kadhias`. |
| Soumission Kadhia | Oui | Oui | Oui | Oui | OK | Garder `POST /api/me/kadhias/{kadhiaId}/submit`. |
| Historique commandes client | Oui | Oui | Oui | Oui | OK | `GET /api/me/orders` et `GET /api/me/orders/{id}` présents et testés. |
| Commandes marchand | Oui | Oui | Oui | Oui | OK | Liste et détail via routes contenant `storeId`; coordonnées client uniquement dans le détail autorisé. |
| Acceptation / refus marchand | **Oui (US-005)** | Oui | Oui | Oui | OK | Accept/reject depuis `submitted`, ownership marchand, logs et libération du créneau sur refus. |
| Acceptation partielle | **Oui (US-037)** | Oui | Oui | Oui | OK | Endpoint livré; Kadhia repasse en `draft`, lignes refusées retirées, resoumission même commande. |
| Annulation commande client | **Oui (US-036)** | Oui | Oui | Oui | OK | `POST /api/me/orders/{orderId}/cancel`, `submitted` uniquement, créneau libéré, log `cancelled`. |
| Préparation ligne par ligne | Oui (US-006) | Oui | Oui | Oui | OK | `OrderLine.prepared` persistant et endpoint marchand de préparation. |
| Mark-ready strict | Oui (US-023) | Oui | Oui | Oui | OK | `mark-ready` depuis `preparing` uniquement, toutes les lignes doivent être préparées. |
| Créneaux marchand CRUD | Oui (US-024, US-047, US-056, US-057) | Oui | Oui | Oui | OK | CRUD ponctuel, règles récurrentes, fermetures exceptionnelles et horaires livrés. |
| Dashboard marchand journalier | Oui (US-051) | Oui | Oui | Oui | OK | `/api/merchant/stores/{storeId}/dashboard/today`, sans données client ni lignes. |
| Thème public store | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| Thème marchand | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| Thème global admin | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| QR code retrait | Oui (US-025) | Oui | Oui | **Oui (Sprint4)** | OK | `PickupSession` créée au passage `ready`; QR token exposé côté client. |
| Double validation retrait | Oui (US-007) | Oui | Oui | **Oui (Sprint4)** | OK | Scan marchand, confirmations client/marchand, `completed` et force completion livrés. |
| Notifications client | **Oui (US-038)** | Oui | Oui | **Oui** | OK | Notifications in-app persistées, lecture et marquage comme lu côté client. |
| Notifications marchand | **Oui (US-039)** | Oui | Oui | **Oui** | OK | Notifications in-app persistées, lecture et marquage comme lu côté marchand. |
| Suivi statut client | Oui (US-026) | Oui | Oui | Oui | OK | `GET /api/me/orders/{orderId}/status`, prévu pour polling simple. |
| Rappel retrait 1h | Oui (US-064) | Oui | Oui | Oui | PARTIEL | Planification Messenger avec `DelayStamp` livrée. Transport Doctrine persistant + Supervisor livrés S7-009. Contenu notification encore générique : il ne contient pas encore nom de supérette, heure du créneau et numéro de commande comme demandé par l'US. |
| Historique statuts commande | **Oui (US-040)** | Oui | Oui | Oui | OK | `OrderStatusLog` et endpoints client/marchand livrés. |
| Admin CRUD Brand/Category | **Oui (US-029)** | Oui | Oui | **Oui (Sprint5)** | OK | S5-006/S5-006b livré : `/api/admin/categories` et `/api/admin/brands`. |
| Admin CRUD ProductReference | **Oui (US-029)** | Oui | Oui | **Oui (Sprint5)** | OK | S5-007 livré : `/api/admin/product-references` dont archive. |
| i18n FR/AR/RTL | **Oui (US-008, US-093)** | Oui front client | Oui | Oui | OK | S14-004 / #401 : contexte langue client FR/AR, persistance `client:lang`, `dir=rtl` pour arabe, message catalogs réutilisés. Le marchand dispose aussi d'un contexte langue FR/AR léger. |
| Frontend client | Oui vision | Oui | Oui (Playwright + tests frontend ciblés) | Oui | OK | Parcours inscription → catalogue → Kadhia → suivi commande, notifications, QR retrait, confirmation client, thème supérette et i18n client livrés. |
| Frontend admin backoffice | Oui vision | Oui | Non (E2E) | Oui | OK | PRs #130–#132 : auth admin, référentiel produits, marchands, supérettes, audit logs, dashboard KPI. |
| Frontend marchand | Oui vision | Oui | Oui (tests frontend ciblés) | Oui | OK | Login, dashboard, commandes, retrait, historique, notifications, catalogue, créneaux/règles/fermetures/horaires, onboarding, QR magasin, thème, paramètres et export CSV livrés. |
| Observabilité / audit logs | Oui roadmap | Oui | Oui | Oui | OK | S7-005 : `/api/health` public, `app:diagnostics:check`, logs structurés. S7-004 : `AdminAuditLog`, 15 actions loggées. |
| Fermeture supérette (US-058) | Oui | Oui | Oui | Oui | OK | S7-001 : `PATCH /api/admin/stores/{storeId}/archive`, annulation commandes actives, révocation QR. |
| Export CSV commandes (US-061) | Oui | Oui | Oui | Oui | OK | S7-002 : `GET /api/merchant/stores/{storeId}/orders/export.csv`, RFC 4180, BOM UTF-8. |
| Suppression compte client (US-062) | Oui | Oui | Oui | Oui | OK | S7-003 : `DELETE /api/me/account`, soft delete, anonymisation, blocage JWT. |
| Images produits web/mobile (US-041 / #391) | Oui roadmap | Oui | Oui | Oui | OK | S13-005 livré : upload admin, original conservé, variantes WebP responsive, fallback JPEG, placeholder catégorie, exposition API. |
| PWA installable client/marchand (#374, #375) | Oui roadmap | Non | Non | Non | MANQUANT | Manifest/service worker et stratégie offline restent ouverts Sprint 14. |
| Push notifications (#376) | Oui roadmap | Non | Non | Non | MANQUANT | Hors notifications in-app MVP ; à traiter après durcissement production. |
| Accessibilité minimum WCAG (#379) | Oui roadmap | Non audité | Non | Non | MANQUANT | Audit et corrections de base restent ouverts Sprint 14. |
| Fiabilité production EPIC-015 (#352-#354) | Oui roadmap | Oui | Oui | N/A | OK | #352 runbook worker async, #353 monitoring Messenger admin et #354 KPI terrain livrés. |
| Activation terrain EPIC-016 (#355-#356) | Oui roadmap | Oui | Oui | N/A | OK | #355 QR magasin PNG/PDF livré côté marchand ; #356 checklist d'activation supérette livrée via PR #412. |

## Écarts critiques détectés

### 1. Authentification client livrée

Sprint Auth a livré le socle client : inscription, login JWT, profil connecté et reset password.

**Documenté dans :** US-034, US-035, US-046 (Sprint Auth).

Endpoints livrés :

```http
POST /api/auth/register/customer
GET  /api/me/profile
PATCH /api/me/profile
POST /api/auth/password-reset/request
POST /api/auth/password-reset/confirm
```

Pour les marchands, le MVP privilégie toujours un onboarding contrôlé par l'admin (US-028). L'inscription marchand publique reste hors périmètre.

### 2. Ancien contrat API Kadhia remplacé

Le contrat API global décrivait auparavant un ancien modèle où la commande servait de panier.

Le modèle actuel est :

```http
POST   /api/me/stores/{storeId}/kadhias
GET    /api/me/kadhias
GET    /api/me/kadhias/{kadhiaId}
PATCH  /api/me/kadhias/{kadhiaId}
PUT    /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}
POST   /api/me/kadhias/{kadhiaId}/submit
```

### 3. Contrat API marchand aligné sur le `storeId`

Les routes réelles côté marchand incluent le `storeId` pour vérifier le propriétaire de la supérette.

Exemples :

```http
GET  /api/merchant/stores/{storeId}/orders
POST /api/merchant/stores/{storeId}/orders/{orderId}/accept
POST /api/merchant/stores/{storeId}/orders/{orderId}/reject
POST /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation
POST /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
```

### 4. Retrait sécurisé livré

Les US prévoient un QR code de retrait, un token opaque, un scan marchand et une double validation.

**Documenté dans :** Sprint4/README.md, US-025, US-007.

Livré (Sprint 4) :

```http
GET   /api/me/orders/{orderId}/pickup-session
POST  /api/merchant/pickup-sessions/scan
PATCH /api/merchant/pickup-sessions/{id}/confirm
PATCH /api/me/pickup-sessions/{id}/confirm
PATCH /api/merchant/pickup-sessions/{id}/force-complete
GET   /api/me/orders/{orderId}/status
```

`PickupSession` porte le token QR opaque, les dates de scan/confirmation, l'usage unique et la force completion. `OrderStatusLog` trace les transitions `ready`, `pickup_pending` et `completed`.

### 5. Admin Sprint 5 livré

Le thème global admin est présent. Sprint 5 couvre maintenant l'administration minimale côté backend : marchands, supérettes, QR, catégories, marques, référentiel produit, propositions, logo/cover, QR marchand et onboarding marchand.

**Documenté dans :** US-009 (complétée), US-028, Sprint5/README.md.

Livré (Sprint 5) :

```http
GET   /api/admin/merchants
GET   /api/admin/merchants/{merchantId}
POST  /api/admin/merchants
PATCH /api/admin/merchants/{merchantId}
PATCH /api/admin/merchants/{merchantId}/activate
PATCH /api/admin/merchants/{merchantId}/suspend
GET   /api/admin/stores
GET   /api/admin/stores/{storeId}
POST  /api/admin/stores
PATCH /api/admin/stores/{storeId}
GET   /api/admin/stores/{storeId}/qr-code
POST  /api/admin/stores/{storeId}/regenerate-qr
GET/POST/PATCH/DELETE /api/admin/brands
GET/POST/PATCH/DELETE /api/admin/categories
GET/POST/PATCH /api/admin/product-references
PATCH /api/admin/product-references/{id}/archive
```

### 6. Notifications Sprint 4 livrées

Les notifications sont maintenant cadrées dans un epic dédié.

**Documenté dans :** EPIC-014, US-038 (client), US-039 (marchand), Sprint4/README.md.

Livré (Sprint 4) : entité `Notification`, endpoints `/api/me/notifications` et `/api/merchant/notifications`.
MVP : notifications persistées en base + polling. Push/SMS/email/Mercure restent hors périmètre.

### 7. Sprint 3 backend livré

Le parcours marchand core est maintenant livré côté backend.

**Documenté dans :** `docs/Sprint3/README.md`, `docs/Sprint3/completion-report.md`.

Endpoints Sprint 3 confirmés :

```http
GET    /api/merchant/stores/{storeId}/orders
GET    /api/merchant/stores/{storeId}/orders/{orderId}
POST   /api/merchant/stores/{storeId}/orders/{orderId}/accept
POST   /api/merchant/stores/{storeId}/orders/{orderId}/reject
POST   /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
POST   /api/merchant/stores/{storeId}/orders/{orderId}/start-preparation
PATCH  /api/merchant/stores/{storeId}/orders/{orderId}/lines/{merchantProductId}/preparation
POST   /api/merchant/stores/{storeId}/orders/{orderId}/mark-ready
GET    /api/merchant/stores/{storeId}/orders/{orderId}/status-history
GET    /api/merchant/stores/{storeId}/pickup-slots
POST   /api/merchant/stores/{storeId}/pickup-slots
PATCH  /api/merchant/stores/{storeId}/pickup-slots/{slotId}
DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}
GET    /api/merchant/stores/{storeId}/dashboard/today
POST   /api/me/orders/{orderId}/cancel
GET    /api/me/orders/{orderId}/status-history
```

### 8. Limites restantes historiques après Sprint 4

Ces points n'étaient pas livrés par Sprint 3/Sprint 4, mais plusieurs ont été traités ensuite :

- créneaux récurrents — livré Sprint 3b ;
- fermetures exceptionnelles — livré Sprint 3b ;
- délai de réponse marchand automatisé — livré Sprint 3b ;
- expiration automatique d'une acceptation partielle — livré Sprint 3b ;
- push mobile, SMS, email et realtime Mercure/WebSocket ;
- réouverture admin d'une session de retrait expirée ;
- enrichissement du contenu du rappel US-064 avec nom de supérette, heure de créneau et numéro de commande ;
- statistiques avancées.

## Suite recommandée au 4 juin 2026

### Contexte déjà livré

Le cœur MVP Sprints 0-9 est livré sur `main`, avec frontend client, marchand et admin avancés. S13-005 a livré les images produits web/mobile. S14-004 / #401 a livré l'i18n client FR/AR + RTL.

### P1 — Sprint 10 clôturable

Priorité recommandée avant PWA, monétisation ou croissance :

- clôturer administrativement Sprint 10 ;
- considérer #357 — journal opérationnel marchand minimal — livré côté backend ;
- fermer #358 — décision bêta FR-only vs FR+AR — comme non nécessaire depuis les livraisons FR/AR.

Justification : #352, #353, #354, #355, #356 et #357 sont livrées. Les supérettes peuvent être validées par checklist avant bêta ; le journal opérationnel marchand minimal donne une première lecture support sur la fiche marchand admin, et la décision langue est absorbée par l'i18n client FR/AR + RTL (#401) et la préférence langue marchand (#395).

### Livré Sprint 10

- #352 — validation production du worker async ;
- #353 — monitoring des jobs asynchrones ;
- #354 — KPI terrain ;
- #355 — QR magasin imprimable PNG/PDF ;
- #356 — checklist d'activation supérette ;
- #357 — journal opérationnel marchand minimal.

### P3 — Sprint 14 : PWA, push, accessibilité

PWA client/marchand, push notifications et accessibilité WCAG restent importants, mais doivent passer après la fiabilité de production pour la bêta.

- #374 — PWA client.
- #375 — PWA marchand.
- #376 — push notifications.
- #379 — accessibilité minimum WCAG.

### P2 — Frontend client/marchand/admin

**Décision :** ADR-0002 (Next.js 14, App Router).

- PWA client mobile-first encore ouverte (#374).
- Backoffice marchand responsive livré.
- Interface admin sobre livrée.

## Règle pour les prochaines PR IA

Avant de coder un nouveau bloc, l'agent doit vérifier :

1. la roadmap principale ;
2. ce document d'audit ;
3. le contrat API ;
4. les user stories du sprint concerné ;
5. les endpoints déjà présents dans `apps/backend/src/ApiResource`.

Ne pas réintroduire les anciens endpoints obsolètes du type `/api/orders/{orderId}/items` pour la Kadhia.
