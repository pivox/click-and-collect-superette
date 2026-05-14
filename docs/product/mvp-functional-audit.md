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
| Inscription client | **Oui (US-034)** | Non | Non | **Oui** | MANQUANT | `POST /api/auth/register/customer` — US-034 documentée. |
| Profil client | **Oui (US-035)** | Non | Non | **Oui** | MANQUANT | `GET/PATCH /api/me/profile` — US-035 documentée. |
| Mot de passe oublié | Non | Non | Non | Non | A_DECIDER | Post-MVP — hors périmètre Sprint Auth. |
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
| Commandes marchand | Oui | Oui | Oui | Oui | OK | Garder routes contenant `storeId`. |
| Acceptation partielle | **Oui (US-037)** | Domaine OK | Non | **Oui** | PARTIEL | US-037 documentée. Processor + endpoint à coder. |
| Annulation commande client | **Oui (US-036)** | Domaine OK | Non | **Oui** | MANQUANT | US-036 documentée. `POST /api/me/orders/{id}/cancel` à coder. |
| Préparation ligne par ligne | Oui (US-006) | Non | Non | Non | MANQUANT | Spécification présente. À implémenter en Sprint 3. |
| Créneaux marchand CRUD | Oui (US-024) | Non | Non | **Oui (Sprint3)** | MANQUANT | US-024 existante. Endpoints définis dans Sprint3/README.md. |
| Thème public store | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| Thème marchand | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| Thème global admin | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| QR code retrait | Oui (US-025) | Non | Non | **Oui (Sprint4)** | MANQUANT | `PickupSession` définie dans Sprint4/README.md. |
| Double validation retrait | Oui (US-007) | Non | Non | **Oui (Sprint4)** | MANQUANT | Endpoints scan/confirm définis dans Sprint4/README.md. |
| Notifications client | **Oui (US-038)** | Non | Non | **Oui** | MANQUANT | US-038 documentée. Entité `Notification` à créer (Sprint 4). |
| Notifications marchand | **Oui (US-039)** | Non | Non | **Oui** | MANQUANT | US-039 documentée. Mêmes endpoints (Sprint 4). |
| Historique statuts commande | **Oui (US-040)** | Non | Non | **Oui** | MANQUANT | US-040 documentée. Entité `OrderStatusLog` à créer (Sprint 3). |
| Admin CRUD Brand/Category | **Oui (US-029)** | Non | Non | **Oui (Sprint5)** | MANQUANT | Endpoints définis dans Sprint5/README.md. |
| Admin CRUD ProductReference | **Oui (US-029)** | Non | Non | **Oui (Sprint5)** | MANQUANT | Endpoints définis dans Sprint5/README.md. |
| i18n FR/AR/RTL | **Oui (US-008)** | Non | Non | **Oui** | MANQUANT | US-008 complétée. Implémentation Sprint 7. |
| Frontend client/marchand/admin | Oui vision | Non | Non | Non | MANQUANT | ADR-0002 (Next.js). Démarrage post-Sprint 2. |
| Observabilité / audit logs | Oui roadmap | Non | Non | Non | MANQUANT | Sprint 7 Production. |

## Écarts critiques détectés

### 1. Authentification incomplète

Le login existe, mais l'inscription et le profil utilisateur ne sont pas cadrés ni codés.

**Documenté dans :** US-034, US-035 (Sprint Auth).

À implémenter :

```http
POST /api/auth/register/customer
GET  /api/me/profile
PATCH /api/me/profile
```

Pour les marchands, le MVP privilégie un onboarding contrôlé par l'admin (US-028).

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

### 4. Retrait sécurisé non codé

Les US prévoient un QR code de retrait, un token opaque, un scan marchand et une double validation.

**Documenté dans :** Sprint4/README.md, US-025, US-007.

À implémenter (Sprint 4) :

```http
GET   /api/me/orders/{orderId}/pickup-session
POST  /api/merchant/pickup-sessions/scan
PATCH /api/merchant/pickup-sessions/{id}/confirm
PATCH /api/me/pickup-sessions/{id}/confirm
```

Nouvelle entité `PickupSession` définie dans Sprint4/README.md.

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

### 6. Notifications absentes

Les notifications sont maintenant cadrées dans un epic dédié.

**Documenté dans :** EPIC-014, US-038 (client), US-039 (marchand), Sprint4/README.md.

À implémenter (Sprint 4) : entité `Notification`, endpoints `/api/me/notifications` et `/api/merchant/notifications`.
MVP : notifications persistées en base + polling. Push/SMS post-MVP.

### 7. Acceptation partielle sans endpoint API

La méthode de domaine `Order::partiallyAccept()` existe mais aucun processor ni route ne l'expose.

**Documenté dans :** US-037.

À implémenter (Sprint 3) :
```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
```

### 8. Annulation commande client absente

La méthode de domaine `Order::cancel()` existe mais aucun processor ni route ne l'expose.

**Documenté dans :** US-036.

À implémenter (Sprint 3) :
```http
POST /api/me/orders/{orderId}/cancel
```

### 9. Créneaux marchand sans CRUD API

L'entité `PickupSlot` existe. Aucun endpoint marchand pour créer/modifier/désactiver un créneau.

**Documenté dans :** US-024, Sprint3/README.md.

À implémenter (Sprint 3) :
```http
POST   /api/merchant/stores/{storeId}/pickup-slots
PATCH  /api/merchant/stores/{storeId}/pickup-slots/{slotId}
DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}
```

### 10. Historique statuts commande absent

Aucune entité `OrderStatusLog`. Les transitions ne sont pas tracées horodatées.

**Documenté dans :** US-040, Sprint3/README.md.

À implémenter (Sprint 3) : entité `OrderStatusLog`, insertion dans chaque Processor de transition.

## Priorités recommandées

### P0 — Sprint Auth (avant Sprint 3)

**Documenté :** US-034, US-035.

- `POST /api/auth/register/customer`
- `GET /api/me/profile`
- `PATCH /api/me/profile`

Sans inscription, aucun client ne peut tester le parcours complet en production.

### P0 — Sprint 3 : compléter le parcours marchand

**Documenté :** Sprint3/README.md, US-024, US-036, US-037, US-040.

- CRUD créneaux marchand (`POST/PATCH/DELETE /api/merchant/stores/{storeId}/pickup-slots`)
- Acceptation partielle (`POST .../partially-accept`)
- Annulation commande client (`POST /api/me/orders/{id}/cancel`)
- Entité `OrderStatusLog` + insertion dans tous les Processors de transition

### P1 — Sprint 4 : retrait sécurisé et notifications

**Documenté :** Sprint4/README.md, US-007, US-025, US-038, US-039, US-040.

- Entité `PickupSession` (QR code retrait)
- Endpoints scan/confirm (`POST .../scan`, `PATCH .../confirm`)
- Entité `Notification` + endpoints client + marchand

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
