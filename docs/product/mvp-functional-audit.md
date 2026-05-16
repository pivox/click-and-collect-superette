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
| Création marchand par admin | **Oui (US-028)** | Non | Non | Oui | MANQUANT | US-028 complète. Endpoints admin marchands à coder (Sprint 5). |
| Création / gestion supérette admin | **Oui (US-009)** | Non | Non | **Oui** | MANQUANT | US-009 complétée. Endpoints admin stores à coder (Sprint 5). |
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
| Créneaux marchand CRUD | Oui (US-024) | Oui | Oui | Oui | OK | CRUD marchand ponctuel livré; récurrence et fermetures exceptionnelles restent Sprint 3b. |
| Dashboard marchand journalier | Oui (US-051) | Oui | Oui | Oui | OK | `/api/merchant/stores/{storeId}/dashboard/today`, sans données client ni lignes. |
| Thème public store | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| Thème marchand | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| Thème global admin | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| QR code retrait | Oui (US-025) | Oui | Oui | **Oui (Sprint4)** | OK | `PickupSession` créée au passage `ready`; QR token exposé côté client. |
| Double validation retrait | Oui (US-007) | Oui | Oui | **Oui (Sprint4)** | OK | Scan marchand, confirmations client/marchand, `completed` et force completion livrés. |
| Notifications client | **Oui (US-038)** | Oui | Oui | **Oui** | OK | Notifications in-app persistées, lecture et marquage comme lu côté client. |
| Notifications marchand | **Oui (US-039)** | Oui | Oui | **Oui** | OK | Notifications in-app persistées, lecture et marquage comme lu côté marchand. |
| Suivi statut client | Oui (US-026) | Oui | Oui | Oui | OK | `GET /api/me/orders/{orderId}/status`, prévu pour polling simple. |
| Rappel retrait 1h | Oui (US-064) | Oui | Oui | Oui | OK | Planification Messenger avec `DelayStamp`; production dépend d'un transport async persistant et d'un worker actif. |
| Historique statuts commande | **Oui (US-040)** | Oui | Oui | Oui | OK | `OrderStatusLog` et endpoints client/marchand livrés. |
| Admin CRUD Brand/Category | **Oui (US-029)** | Non | Non | **Oui (Sprint5)** | MANQUANT | Endpoints définis dans Sprint5/README.md. |
| Admin CRUD ProductReference | **Oui (US-029)** | Non | Non | **Oui (Sprint5)** | MANQUANT | Endpoints définis dans Sprint5/README.md. |
| i18n FR/AR/RTL | **Oui (US-008)** | Non | Non | **Oui** | MANQUANT | US-008 complétée. Implémentation Sprint 7. |
| Frontend client/marchand/admin | Oui vision | Non | Non | Non | MANQUANT | ADR-0002 (Next.js). Démarrage post-Sprint 2. |
| Observabilité / audit logs | Oui roadmap | Non | Non | Non | MANQUANT | Sprint 7 Production. |

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

### 5. Admin incomplet

Le thème global admin est présent, mais l'administration des marchands et supérettes manque.

**Documenté dans :** US-009 (complétée), US-028, Sprint5/README.md.

À implémenter (Sprint 5) :

```http
GET   /api/admin/merchants
POST  /api/admin/merchants
PATCH /api/admin/merchants/{merchantId}/activate
PATCH /api/admin/merchants/{merchantId}/suspend
GET   /api/admin/stores
POST  /api/admin/stores
PATCH /api/admin/stores/{storeId}
PATCH /api/admin/stores/{storeId}/owner
POST  /api/admin/stores/{storeId}/regenerate-qr
GET   /api/admin/brands + POST + PATCH
GET   /api/admin/categories + POST + PATCH
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

### 8. Limites restantes après Sprint 4

Ces points ne doivent pas être considérés comme livrés par Sprint 3/Sprint 4 :

- créneaux récurrents ;
- fermetures exceptionnelles ;
- délai de réponse marchand automatisé ;
- expiration automatique d'une acceptation partielle ;
- push mobile, SMS, email et realtime Mercure/WebSocket ;
- réouverture admin d'une session de retrait expirée ;
- export et statistiques avancées.

## Priorités recommandées

### P0 — Sprint Auth

**Documenté :** US-034, US-035.

- `POST /api/auth/register/customer`
- `GET /api/me/profile`
- `PATCH /api/me/profile`

Sans inscription, aucun client ne peut tester le parcours complet en production.

### P0 — Sprint 3 : parcours marchand core

**Statut : livré côté backend.**

La suite opérationnelle restante relève de Sprint 3b : récurrence des créneaux, fermetures exceptionnelles, délais automatiques, historique complet.

### P1 — Sprint 4 : retrait sécurisé et notifications

**Statut : livré côté backend.**

Points à surveiller avant production : transport Messenger async persistant, worker actif, absence de push/SMS/email/Mercure, et verrouillage éventuel des confirmations simultanées si le trafic augmente.

### P1 — Sprint 5 : administration minimale

**Documenté :** Sprint5/README.md, US-009, US-028, US-029.

- Création et gestion des supérettes (admin)
- Création et gestion des marchands (admin)
- CRUD Brand, Category, ProductReference (admin)

### P2 — i18n FR/AR/RTL

**Documenté :** US-008.

- Sélecteur de langue dans l'interface
- Mode RTL pour l'arabe
- Persistance préférence utilisateur
- Implémentation Sprint 7.

### P2 — Frontend client/marchand/admin

**Décision :** ADR-0002 (Next.js 14, App Router).

- PWA client mobile-first
- Backoffice marchand responsive
- Interface admin sobre

## Règle pour les prochaines PR IA

Avant de coder un nouveau bloc, l'agent doit vérifier :

1. la roadmap principale ;
2. ce document d'audit ;
3. le contrat API ;
4. les user stories du sprint concerné ;
5. les endpoints déjà présents dans `apps/backend/src/ApiResource`.

Ne pas réintroduire les anciens endpoints obsolètes du type `/api/orders/{orderId}/items` pour la Kadhia.
