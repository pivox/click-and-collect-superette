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
| Inscription client | Non | Non | Non | Non | MANQUANT | Ajouter `POST /api/auth/register/customer`. |
| Profil client | Non | Non | Non | Non | MANQUANT | Ajouter `GET/PATCH /api/me/profile`. |
| Mot de passe oublié | Non | Non | Non | Non | MANQUANT | Post-MVP ou Sprint Auth selon priorité. |
| Inscription marchand publique | Non | Non | Non | Non | A_DECIDER | Pour le MVP, privilégier création par admin. |
| Création marchand par admin | Oui partiel | Non | Non | Non | MANQUANT | Ajouter endpoints admin marchands. |
| Création / gestion supérette admin | Oui partiel | Non | Non | Non | MANQUANT | Compléter US-009 et endpoints admin stores. |
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
| Acceptation partielle | Oui | Domaine partiel | Partiel | Non | PARTIEL | Ajouter endpoint marchand dédié. |
| Préparation ligne par ligne | Oui | Non | Non | Non | MANQUANT | À cadrer avant Sprint préparation avancée. |
| Créneaux marchand CRUD | Oui | Non / à vérifier | Non / à vérifier | Partiel | MANQUANT | Ajouter endpoints marchand pickup-slots. |
| Thème public store | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| Thème marchand | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| Thème global admin | Oui | Oui | Oui | Oui | OK | Déjà avancé. |
| QR code retrait | Oui | Non | Non | Partiel obsolète | MANQUANT | Créer `PickupSession`. |
| Double validation retrait | Oui | Non | Non | Non | MANQUANT | Créer endpoints scan/confirm. |
| Notifications | Mentionné | Non | Non | Non | MANQUANT | Créer epic Notifications MVP. |
| i18n FR/AR/RTL | Oui vision | Non | Non | Non | MANQUANT | Découper en US opérationnelles. |
| Frontend client/marchand/admin | Oui vision | Non | Non | Non | MANQUANT | Créer roadmap écrans. |
| Observabilité / audit logs | Oui roadmap | Non | Non | Non | MANQUANT | Sprint Production. |

## Écarts critiques détectés

### 1. Authentification incomplète

Le login existe, mais l'inscription et le profil utilisateur ne sont pas cadrés ni codés.

À ajouter :

```http
POST /api/auth/register/customer
GET  /api/me/profile
PATCH /api/me/profile
```

Pour les marchands, le MVP doit privilégier un onboarding contrôlé par l'admin plutôt qu'une inscription publique non validée.

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

Les US prévoient un QR code de retrait, un token opaque, un scan marchand et une double validation. Le backend correspondant n'est pas encore présent.

À prévoir :

```http
GET   /api/me/orders/{orderId}/pickup-session
POST  /api/merchant/pickup-sessions/scan
PATCH /api/merchant/pickup-sessions/{id}/confirm
PATCH /api/me/pickup-sessions/{id}/confirm
```

### 5. Admin incomplet

Le thème global admin est présent, mais l'administration des marchands et supérettes manque encore.

À prévoir :

```http
GET   /api/admin/merchants
POST  /api/admin/merchants
PATCH /api/admin/merchants/{merchantId}/activate
PATCH /api/admin/merchants/{merchantId}/suspend
GET   /api/admin/stores
POST  /api/admin/stores
PATCH /api/admin/stores/{storeId}
PATCH /api/admin/stores/{storeId}/owner
```

### 6. Notifications absentes

Les notifications sont mentionnées dans plusieurs scénarios métier mais ne sont pas un epic dédié.

À ajouter :

```text
EPIC-NOTIF — Notifications MVP
```

MVP recommandé : notifications persistées en base + endpoints de lecture, sans push/SMS au départ.

## Priorités recommandées

### P0 — Stabiliser les sources de vérité

- Garder `docs/architecture/api-contract.md` aligné sur les endpoints réels.
- Corriger ou marquer comme obsolètes les anciennes US Kadhia/QR contradictoires.
- Conserver `docs/roadmap/mvp-roadmap.md` comme roadmap principale.

### P0 — Auth client

- `POST /api/auth/register/customer`
- `GET /api/me/profile`
- `PATCH /api/me/profile`

### P1 — Admin onboarding marchand/store

- création et gestion des marchands ;
- création et gestion des supérettes ;
- association marchand/supérette.

### P1 — Retrait sécurisé

- `PickupSession` ;
- QR code retrait ;
- scan marchand ;
- confirmation client + marchand.

### P2 — Notifications MVP

- table `notification` ;
- endpoints client/marchand ;
- création automatique lors des transitions principales.

### P2 — i18n FR/AR/RTL

- choix langue ;
- persistance préférence ;
- messages d'erreur traduisibles ;
- support RTL côté frontend.

## Règle pour les prochaines PR IA

Avant de coder un nouveau bloc, l'agent doit vérifier :

1. la roadmap principale ;
2. ce document d'audit ;
3. le contrat API ;
4. les user stories du sprint concerné ;
5. les endpoints déjà présents dans `apps/backend/src/ApiResource`.

Ne pas réintroduire les anciens endpoints obsolètes du type `/api/orders/{orderId}/items` pour la Kadhia.
