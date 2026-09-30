# Audit de préparation API mobile — authentification, sessions, erreurs, idempotence (MOBILE-004, #565)

> Audit + cadrage. **Aucun endpoint ajouté, aucun code modifié.** Chaque ligne de la
> matrice a été vérifiée dans le code backend (`apps/backend/src/`), la configuration
> (`apps/backend/config/`) ou par sondage HTTP en local (lecture seule). Les écarts
> bloquants sont découpés en issues backend dédiées (§7).
>
> Parcours audités : cadrages Mobile Client (#563, `docs/mobile/client-scope.md`)
> et Mobile Marchand (#564, `docs/mobile/merchant-scope.md`). Contrat de référence :
> `docs/architecture/api-contract.md` (source de vérité : `/api/docs.json`).

**Méthode de vérification réellement appliquée** :

- lecture des `ApiResource`, `Processor`, `Provider`, `Security`, `EventSubscriber` du backend ;
- configuration : `security.yaml`, `lexik_jwt_authentication.yaml`, `api_platform.yaml`, `nelmio_cors.yaml`, `monolog.yaml`, `docker/nginx/backend.conf`, `composer.json` ;
- export OpenAPI local : `docker compose exec -T backend php bin/console api:openapi:export` (OpenAPI 3.1.0, **189 chemins**) + sondage des opérations mobiles ;
- sondage HTTP local (lecture seule) des formats d'erreur réels : 401 Lexik, 404 problem+json, 422 validation ;
- **aucun test PHPUnit exécuté** dans cette passe (les comportements cités s'appuient sur le code et sur les assertions des tests fonctionnels existants, référencés ci-dessous).

Statuts : `READY` · `READY_WITH_UI_HANDLING` (utilisable tel quel, avec gestion UI/mapping obligatoire côté app) · `GAP_NON_BLOCKING` · `GAP_BLOCKING` · `OUT_OF_SCOPE` (hors Mobile V1).

---

## 1. Matrice d'audit

Conventions de lecture :

- **Ownership** : « checker » = `MerchantShopAccessChecker::denyUnlessMerchantOwnsShop()` (403 `MERCHANT_CATALOG_FORBIDDEN`, membership relue à chaque requête) ; « client » = filtrage par utilisateur courant (404 si autre client, aucune fuite) ; « — » = route publique.
- **Erreurs stables** : codes portés par `detail` (problem+json, §3). Seuls les codes principaux sont listés ; matrice détaillée d'idempotence au §4.
- **Cache** : cache HTTP serveur. « aucun » = pas d'en-tête cache (cache applicatif TanStack Query côté app, §6 des cadrages).
- **Doc contrat** : ✔ = documenté dans `docs/architecture/api-contract.md` ; ✘ = livré mais absent du contrat (action « documenter »).

### 1.1 Application Client

| Application | Parcours | Endpoint | Méthode | Rôle | Ownership | Pagination | Erreurs stables | Idempotence | Cache | Statut mobile | Action |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Client | Connexion | `/api/auth/login` | POST | public | — | — | 401 Lexik `{code,message}` : `Invalid credentials.`, `MERCHANT_TEMPORARY_PASSWORD_EXPIRED` | oui (sans effet) | aucun | READY_WITH_UI_HANDLING | Format 401 ≠ problem+json (§3) ; pas d'`expires_in` ni refresh → issue [refresh](§7) ; pas de rate limiting (§6) |
| Client | Inscription | `/api/auth/register/customer` | POST | public | — | — | 409 `AUTH_EMAIL_ALREADY_EXISTS`, 422 violations | non (2e appel → 409) | aucun | READY | 201 avec `{token,user}` (auto-login OK). Corriger le contrat : code réel = `AUTH_EMAIL_ALREADY_EXISTS`, pas `AUTH_EMAIL_ALREADY_USED` |
| Client | Mot de passe oublié | `/api/auth/password-reset/request` | POST | public | — | — | 202 neutre (jamais de divulgation) | oui (rotation des tokens) | aucun | READY | — |
| Client | Réinitialisation | `/api/auth/password-reset/confirm` | POST | public | — | — | 400 `AUTH_RESET_TOKEN_INVALID` / `_ALREADY_USED` / `_EXPIRED`, 422 `AUTH_WEAK_PASSWORD` | non (token usage unique) | aucun | READY | 204 ; token TTL 1 h, haché sha256. Ne révoque pas les JWT émis → issue [refresh](§7) |
| Client | Profil | `/api/me/profile` | GET/PATCH | CUSTOMER | client | — | 422 violations | PATCH idempotent | aucun | READY | — |
| Client | Suppression compte | `/api/me/account` | DELETE | CUSTOMER | client | — | — | oui (2e appel → 401, email anonymisé) | aucun | READY | 204 ; soft delete + anonymisation (`CustomerDeleteAccountProcessor`) — exigence stores couverte |
| Client | QR magasin | `/api/stores/by-qr/{qrCodeToken}` | GET | public | — | — | 404 `STORE_NOT_FOUND` | oui | aucun | READY | — |
| Client | Visite/reconnaissance | `/api/me/stores/{storeId}/visit` | POST | CUSTOMER | client | — | 404 `STORE_NOT_FOUND` | oui (upsert relation) | aucun | READY | — |
| Client | Recherche supérette | `/api/stores/search` | GET | public | — | page/limit | 400 filtres invalides | oui | aucun | READY | — |
| Client | Fiche supérette | `/api/stores/{storeId}` | GET | public | — | — | 404 `STORE_NOT_FOUND` | oui | aucun | READY | — |
| Client | Thème supérette | `/api/stores/{storeId}/theme` | GET | public | — | — | 404 | oui | `Cache-Control: public, max-age=300` (`StoreThemeCacheSubscriber`) | READY | Seul endpoint avec cache HTTP |
| Client | Horaires | `/api/stores/{storeId}/opening-hours` | GET | public | — | — | 404 | oui | aucun | READY | — |
| Client | Mes supérettes | `/api/me/stores` | GET | CUSTOMER | client | oui | — | oui | aucun | READY | — |
| Client | Favori supérette | `/api/me/stores/{storeId}/favorite` | PATCH | CUSTOMER | client | — | 404 `CUSTOMER_STORE_NOT_FOUND` | idempotent | aucun | READY | — |
| Client | Masquer supérette | `/api/me/stores/{storeId}` | DELETE | CUSTOMER | client | — | 404 | oui | aucun | READY | — |
| Client | Catalogue public | `/api/stores/{storeId}/catalog` | GET | public | — | `?query`, `?category`, page | 404 | oui | aucun | READY | Images S13-005 avec variantes WebP + fallback — adapté mobile |
| Client | Créer Kadhia | `/api/me/stores/{storeId}/kadhias` | POST | CUSTOMER | client | — | 404 `STORE_NOT_FOUND` | non (chaque POST crée un brouillon — multi-Kadhia assumé) | aucun | READY | Création toujours explicite (verrou UI) |
| Client | Kadhia active (variante) | `/api/me/stores/{storeId}/kadhias` | GET | CUSTOMER | client | oui | — | oui | aucun | READY | ✘ contrat : variante utilisée par la PWA — documenter ou unifier sur `GET /api/me/kadhias?store_id=` |
| Client | Mes Kadhias | `/api/me/kadhias?status=&store_id=&page=` | GET | CUSTOMER | client | oui | 400 filtres | oui | aucun | READY | — |
| Client | Détail Kadhia | `/api/me/kadhias/{kadhiaId}` | GET | CUSTOMER | client | — | 404 `KADHIA_NOT_FOUND` | oui | aucun | READY | — |
| Client | Note Kadhia | `/api/me/kadhias/{kadhiaId}` | PATCH | CUSTOMER | client | — | 422 `KADHIA_NOT_EDITABLE`, ≤ 500 car. | idempotent | aucun | READY | — |
| Client | Supprimer Kadhia | `/api/me/kadhias/{kadhiaId}` | DELETE | CUSTOMER | client | — | 422 `KADHIA_NOT_DELETABLE`, 404 | non (2e appel → 404) | aucun | READY | ✘ contrat : livré (`DeleteKadhiaProcessor`), à documenter |
| Client | Ligne Kadhia (upsert) | `/api/me/kadhias/{kadhiaId}/lines/{merchantProductId}` | PUT | CUSTOMER | client | — | 422 `KADHIA_NOT_EDITABLE`, `PRODUCT_NOT_AVAILABLE` | idempotent (upsert quantité) | aucun | READY | Prix snapshoté à l'ajout (règle sécurité respectée) |
| Client | Retirer ligne | `/api/me/kadhias/{kadhiaId}/lines/{merchantProductId}` | DELETE | CUSTOMER | client | — | 422 `KADHIA_NOT_EDITABLE`, 404 | non (2e appel → 404) | aucun | READY | — |
| Client | Rendez-vous | `/api/stores/{storeId}/pickup-slots?from=&available=` | GET | public | — | — | 400 filtres | oui | aucun | READY | `booking_policy` (délai minimal) exposé — ORDER-LEAD-002 |
| Client | **Soumission** | `/api/me/kadhias/{kadhiaId}/submit` | POST | CUSTOMER | client | — | Voir §4.1 : `PICKUP_SLOT_FULL`/`_EXPIRED`/`_CLOSED`/`_MINIMUM_LEAD_TIME_NOT_MET`, `KADHIA_EMPTY`, `PRODUCT_UNAVAILABLE`, `PARTIAL_ACCEPTANCE_EXPIRED`, 409 `STORE_SUSPENDED_FOR_SUBSCRIPTION` | **oui en séquentiel** (Kadhia non-draft → 201 avec la commande existante) ; risque résiduel en simultané strict (§4.1) | aucun | READY_WITH_UI_HANDLING | Verrou UI anti double tap obligatoire ; capacité créneau protégée par UPDATE atomique. Écart cadrage : le double envoi renvoie **201 idempotent**, pas `KADHIA_NOT_EDITABLE` — corriger #563 §1.4/§4 |
| Client | Mes commandes | `/api/me/orders?page=&limit=` | GET | CUSTOMER | client | `{items,total,page,limit}` | — | oui | aucun | READY | — |
| Client | Détail commande | `/api/me/orders/{id}` | GET | CUSTOMER | client | — | 404 `ORDER_NOT_FOUND` | oui | aucun | READY | `pickup_code` exposé quand `ready` |
| Client | Suivi statut | `/api/me/orders/{orderId}/status` | GET | CUSTOMER | client | — | 404 | oui | aucun | READY | Polling léger prévu au contrat ; `status_label_fr/_ar` fournis |
| Client | Historique statuts | `/api/me/orders/{orderId}/status-history` | GET | CUSTOMER | client | — | 404 | oui | aucun | READY | — |
| Client | Annulation | `/api/me/orders/{orderId}/cancel` | POST | CUSTOMER | client | — | 409 `ORDER_NOT_SUBMITTED` | non (2e appel → 409, à mapper « déjà traitée ») | aucun | READY | Limitée à `submitted` (revalidé serveur) |
| Client | Notifications | `/api/me/notifications?page=&unread=` | GET | CUSTOMER | client | `{items,total,page}` | — | oui | aucun | READY_WITH_UI_HANDLING | `title_fr`/`body_fr` uniquement — repli FR en AR (décision produit ouverte, #563 §8) |
| Client | Marquer lu / tout lu | `/api/me/notifications/{id}/read`, `/read-all` | PATCH | CUSTOMER | client | — | 404 | idempotent | aucun | READY | — |
| Client | **Push natif** | `/api/me/push-subscriptions` (+`/unregister`) | POST | CUSTOMER | client | — | 422 violations | upsert par `endpoint_hash` (unique) | aucun | GAP_BLOCKING | **Web Push uniquement** (DTO `endpoint` URL https + `p256dhKey` + `authKey` — `Dto/PushSubscriptionInput.php`) : inutilisable pour FCM/APNs/Expo. Porté par **#566** (modèle `mobile_device`) — pas de nouvelle issue ici |
| Client | QR de retrait | `/api/me/orders/{orderId}/pickup-session` | GET | CUSTOMER | client | — | 404, `is_expired`/`is_used` en payload | oui | aucun | READY | TTL session 24 h |
| Client | Confirmation client | `/api/me/pickup-sessions/{id}/confirm` | PATCH | CUSTOMER | client (404 si autrui) | — | 409 `PICKUP_SESSION_ALREADY_USED`/`_NOT_SCANNED`, `ORDER_NOT_PICKUP_PENDING` | idempotent tant que non close ; puis 409 | aucun | READY_WITH_UI_HANDLING | **Écart cadrage** : l'ordre des confirmations est libre côté backend — `PICKUP_SESSION_NOT_MERCHANT_CONFIRMED` n'existe **pas** sur cette route (réservé à force-complete). Corriger #563 E15 ; l'UI peut séquencer, le serveur ne l'impose pas |
| Client | Version minimale d'app | *(inexistant)* | GET | public | — | — | — | — | — | GAP_BLOCKING | **Issue créée** (§7) — exigé par ADR-0007, écrans E18 (#563) / E17 (#564) |
| Client | Remplacements | `/api/me/kadhias/{kadhiaId}/replacements` | GET | CUSTOMER | client | — | — | oui | aucun | OUT_OF_SCOPE | Post-V1 (#563 §1.3) ; livré (`KadhiaReplacementsProvider`), ✘ contrat — documenter si repêché |
| Client | Favoris produits / suggestions | `/api/me/stores/{storeId}/favorite-products`, `/suggestions`, `/api/me/products/{merchantProductId}/favorite` | GET/PATCH | CUSTOMER | client | — | — | — | aucun | OUT_OF_SCOPE | Post-V1 (#563 §1.3) ; livrés, ✘ contrat — documenter si repêchés |
| Client | Partage de Kadhia | `/api/me/kadhias/{kadhiaId}/share-links`, `/api/me/kadhia-share-links/{token}/join` | POST | CUSTOMER | client | — | 409 `KADHIA_NOT_SHAREABLE` | — | aucun | OUT_OF_SCOPE | Post-V1 (#563 §1.3), dépend de #566 |
| Client | WhatsApp commande | `/api/me/orders/{orderId}/whatsapp-contact` | POST | CUSTOMER | client | — | — | — | aucun | OUT_OF_SCOPE | Conditionnel produit #378 |

### 1.2 Application Marchand

| Application | Parcours | Endpoint | Méthode | Rôle | Ownership | Pagination | Erreurs stables | Idempotence | Cache | Statut mobile | Action |
|---|---|---|---|---|---|---|---|---|---|---|---|
| Marchand | Connexion | `/api/auth/login` | POST | public | — | — | 401 Lexik ; `MERCHANT_TEMPORARY_PASSWORD_EXPIRED` au login (`LoginUserChecker`) | oui | aucun | READY_WITH_UI_HANDLING | **Écart** : un compte suspendu (`active=false`) obtient un JWT — `MERCHANT_ACCOUNT_INACTIVE` n'est levé qu'en aval (`/merchant/me`, checker). L'UI doit traiter le 403 post-login ; durcissement inclus dans l'issue refresh (§7) |
| Marchand | Contexte compte | `/api/merchant/me` | GET/PATCH | MERCHANT | provider dédié | — | 403 `MERCHANT_ACCESS_REQUIRED`/`MERCHANT_ACCOUNT_INACTIVE`, 404 `MERCHANT_ACTIVE_STORE_NOT_FOUND`, **409 `MERCHANT_MULTIPLE_ACTIVE_STORES`** | oui | aucun | READY | `password_change_required`, `account.{status,is_primary}`, `merchant_organization_id` exposés (`MerchantMeProvider`) — conforme #564 §1.1 |
| Marchand | Première connexion | `/api/merchant/first-login/change-password` | POST | MERCHANT | soi-même | — | 403 `MERCHANT_PASSWORD_CHANGE_NOT_REQUIRED`/`MERCHANT_TEMPORARY_PASSWORD_EXPIRED`, 422 `MERCHANT_PASSWORD_CONFIRMATION_MISMATCH`/`MERCHANT_CURRENT_PASSWORD_INVALID`/`AUTH_WEAK_PASSWORD` | non (2e appel → 403 `..._NOT_REQUIRED`) | aucun | READY | 204. Verrouillage global : `MerchantPasswordChangeRequiredSubscriber` (403 `MERCHANT_PASSWORD_CHANGE_REQUIRED` partout sauf `/merchant/me` + cette route) — conforme #564 §2 |
| Marchand | Mot de passe connecté | `/api/merchant/me/password` | PATCH | MERCHANT | soi-même | — | 422 `MERCHANT_CURRENT_PASSWORD_INVALID`/`AUTH_WEAK_PASSWORD` | idempotent | aucun | READY | 204 ; ancien mot de passe requis ; ne révoque pas les JWT émis (§2) |
| Marchand | Récupération mot de passe | `/api/auth/password-reset/request` + `/confirm` | POST | public | — | — | cf. Client | — | aucun | READY | Parcours web ouvert depuis l'app (#566) |
| Marchand | Invitation (finalisation) | `/api/auth/merchant-invitations/verify` + `/complete` | POST | public | — | — | — | — | aucun | OUT_OF_SCOPE | Web V1 (#564 §1.1) |
| Marchand | Dashboard | `/api/merchant/stores/{storeId}/dashboard/today` | GET | MERCHANT | checker | — | 404 `STORE_NOT_FOUND` | oui | aucun | READY | — |
| Marchand | Onboarding (lecture) | `/api/merchant/onboarding` | GET | MERCHANT | soi-même | — | — | oui | aucun | READY | Bannière seule en V1 ; `step.key` = clé i18n (labels FR only) |
| Marchand | Commandes actives | `/api/merchant/stores/{storeId}/orders?status=&page=&limit=` | GET | MERCHANT | checker | `{items,total,page,limit}` | 400 filtres | oui | aucun | READY | Pas de coordonnées client en liste (règle contrat respectée) |
| Marchand | Détail commande | `/api/merchant/stores/{storeId}/orders/{orderId}` | GET | MERCHANT | checker | — | 404 `ORDER_NOT_FOUND` | oui | aucun | READY | Coordonnées client sur commandes actives uniquement |
| Marchand | Historique | `/api/merchant/stores/{storeId}/orders/history?...` | GET | MERCHANT | checker | `{items,total,page,limit}`, limit ≤ 50 | **422** `ORDER_HISTORY_INVALID_DATE_RANGE` (`MerchantOrderHistoryProvider`) | oui | aucun | READY | Écart mineur cadrage #564 E10 (annonçait 400) : le code réel est 422 — mapper les deux |
| Marchand | Accepter | `.../orders/{orderId}/accept` | POST | MERCHANT | checker | — | 409 `ORDER_NOT_SUBMITTED` | non (2e appel → 409) | aucun | READY | Notification best-effort + 2e flush ✔ |
| Marchand | Refuser | `.../orders/{orderId}/reject` | POST | MERCHANT | checker | — | 409 `ORDER_NOT_SUBMITTED` | non (2e appel → 409) | aucun | READY_WITH_UI_HANDLING | **Écart cadrage** : `reason` est **facultatif** côté backend (nullable ≤ 500) — l'obligation « raison obligatoire » (#564 E05) est une règle UI. Libération du créneau par décrément ORM (§4.4) |
| Marchand | Acceptation partielle | `.../orders/{orderId}/partially-accept` | POST | MERCHANT | checker | — | 422 `NO_LINES_REJECTED`/`ORDER_LINE_NOT_FOUND`/`USE_REJECT_ENDPOINT`, 409 `ORDER_NOT_SUBMITTED`/`ORDER_KADHIA_REQUIRED`/`KADHIA_LINE_NOT_FOUND` | non — **2e appel identique → 409 `KADHIA_LINE_NOT_FOUND`** (pas `ORDER_NOT_SUBMITTED`) | aucun | READY_WITH_UI_HANDLING | Mapper les deux 409 sur « déjà traitée » + re-GET (§4.3) |
| Marchand | Démarrer préparation | `.../orders/{orderId}/start-preparation` | POST | MERCHANT | checker | — | 409 `ORDER_NOT_ACCEPTED` | non (2e appel → 409) | aucun | READY | — |
| Marchand | Préparer une ligne | `.../orders/{orderId}/lines/{merchantProductId}/preparation` | PATCH | MERCHANT | checker | — | 400 `PREPARED_REQUIRED`, 409 `ORDER_NOT_PREPARING`, 404 `ORDER_LINE_NOT_FOUND` | **pleinement idempotent** (affectation booléenne) | aucun | READY | Parcours multi-appareils 3.9 (#564) validé par le code |
| Marchand | Marquer prête | `.../orders/{orderId}/mark-ready` | POST | MERCHANT | checker | — | **409** `ORDER_LINES_NOT_FULLY_PREPARED`, 409 `ORDER_NOT_PREPARING` | non (2e appel → 409) | aucun | READY | Écart mineur cadrage #564 (cas 13 annonçait 422) : le code réel est **409** — mapper. Crée la `PickupSession` (TTL 24 h, token unique) + `pickup_code` 4 chiffres (non unique — §4.7) |
| Marchand | Historique statuts | `.../orders/{orderId}/status-history` | GET | MERCHANT | checker | — | 404 | oui | aucun | READY | `actor_type`/`actor_name` (MERCHANT-TEAM-006) |
| Marchand | Scan retrait | `/api/merchant/pickup-sessions/scan` | POST | MERCHANT | checker (403 si autre supérette) | — | 404 `PICKUP_SESSION_NOT_FOUND`, 409 `PICKUP_SESSION_ALREADY_USED`/`_EXPIRED`, `ORDER_NOT_READY` | **double scan → 200 idempotent** (même sortie) | aucun | READY_WITH_UI_HANDLING | Écart cadrage #564 cas 8 : un token d'une **autre supérette** → **403 `MERCHANT_CATALOG_FORBIDDEN`**, pas 404 — mapper les deux sur « QR non valide » |
| Marchand | Confirmation marchand | `/api/merchant/pickup-sessions/{id}/confirm` | PATCH | MERCHANT | checker | — | 409 `PICKUP_SESSION_ALREADY_USED`/`_NOT_SCANNED`/`_EXPIRED`, `ORDER_NOT_PICKUP_PENDING` | idempotent tant que le client n'a pas confirmé ; puis 409 | aucun | READY | — |
| Marchand | Force completion | `/api/merchant/pickup-sessions/{id}/force-complete` | PATCH | MERCHANT | checker | — | 409 `PICKUP_FORCE_COMPLETION_TOO_EARLY` (300 s après scan), `PICKUP_SESSION_NOT_MERCHANT_CONFIRMED`, `PICKUP_SESSION_ALREADY_CUSTOMER_CONFIRMED`, `ORDER_ALREADY_COMPLETED` | non (2e appel → 409 `ORDER_ALREADY_COMPLETED`) | aucun | READY | `note` obligatoire (NotBlank ≤ 500, **pas de minimum de longueur**) ; traçabilité `forceNote` + journal |
| Marchand | Code 4 chiffres | `/api/merchant/stores/{storeId}/orders/redeem-by-code` | POST | MERCHANT | checker | — | **404 `PICKUP_CODE_NOT_FOUND`** (code inconnu, commande non `ready`, double appel) | non (2e appel → 404) | aucun | READY_WITH_UI_HANDLING | ✘ contrat — **sémantique tranchée (question #564 §8)** : **finalisation directe** `ready` → `completed`, session marquée `used`, sortie `{order_id, status}`, sans étape de confirmation. Clé JSON `pickupCode` (camelCase). À documenter au contrat ; collision de code possible (§4.7, GAP_NON_BLOCKING) |
| Marchand | Validation manuelle | `.../orders/{orderId}/validate-manually` | POST | MERCHANT | checker | — | 409 `ORDER_NOT_READY`, 422 note (5–500 car.) | non (2e appel → 409) | aucun | READY | ✘ contrat — sémantique : finalisation directe `ready` → `completed` ; la `PickupSession` n'est **pas** consommée et `pickup_code` n'est pas effacé (trace : journal `withdrawal_validated_manually: <note>`). À documenter |
| Marchand | Notifications | `/api/merchant/notifications` (+`/read`, `/read-all`) | GET/PATCH | MERCHANT | soi-même | `{items,total,page}` | 404 | idempotent | aucun | READY_WITH_UI_HANDLING | `title_fr`/`body_fr` uniquement (repli FR en AR — décision produit ouverte) |
| Marchand | **Push natif** | `/api/merchant/push-subscriptions` (+`/unregister`) | POST | MERCHANT | soi-même | — | 422 violations | upsert par `endpoint_hash` | aucun | GAP_BLOCKING | **Web Push uniquement** (même DTO que côté client). Porté par **#566** (`mobile_device`) — pas de nouvelle issue ici |
| Marchand | Catalogue | `/api/merchant/stores/{storeId}/catalog` | GET/POST | MERCHANT | checker | oui | 404, 422 | GET oui / POST non | aucun | READY | — |
| Marchand | Dispo / prix unitaire | `/api/merchant/catalog/{merchantProductId}` | PATCH | MERCHANT | checker | — | 404, 422 | idempotent | aucun | READY | Prix snapshotés côté Kadhia : aucun impact commandes en cours |
| Marchand | Dispo en masse | `/api/merchant/stores/{storeId}/products/bulk-availability` | PATCH | MERCHANT | checker | — | 422 | idempotent | aucun | READY | — |
| Marchand | Référentiel / code-barres | `/api/merchant/stores/{storeId}/product-references?q=&barcode=` | GET | MERCHANT | checker | oui | 400 filtres | oui | aucun | READY | — |
| Marchand | Photo produit local | `/api/merchant/stores/{storeId}/local-products/{localProductId}/photo` | POST/DELETE | MERCHANT | checker | — | 400 fichier requis, 422 (taille, `MERCHANT_PHOTO_TOO_SMALL`), 404 | non | aucun | GAP_NON_BLOCKING | Contrôleur Symfony brut (`MerchantLocalProductPhotoController`) : **absent de `/api/docs.json`** → invisible du SDK généré (§5). ✘ contrat. Action : exposer via ApiResource ou décrire à la main dans le SDK + documenter les limites taille/format |
| Marchand | Rendez-vous (créneaux) | `/api/merchant/stores/{storeId}/pickup-slots` (+`/{slotId}` DELETE) | GET/DELETE | MERCHANT | checker | oui | 404 | DELETE = désactivation logique, idempotent | aucun | READY | — |
| Marchand | Fermetures exceptionnelles | `/api/merchant/stores/{storeId}/exceptional-closures` (+`/{closureId}`) | GET/POST/DELETE | MERCHANT | checker | oui | 404, 422 | POST non / DELETE oui | aucun | READY | — |
| Marchand | Horaires (lecture) | `/api/merchant/stores/{storeId}/opening-hours` | GET | MERCHANT | checker | — | 404 | oui | aucun | READY | Édition web V1 |
| Marchand | Délai minimal retrait | `/api/merchant/stores/{storeId}/ordering-policy` | GET/PATCH | MERCHANT | checker | — | 422 `SHOP_ORDERING_POLICY_INVALID_LEAD_TIME`/`_UNKNOWN_FIELD` | idempotent | aucun | READY | — |
| Marchand | QR magasin | `/api/merchant/stores/{storeId}/qr-code` (+`.png`, `.pdf`) | GET | MERCHANT | checker | — | 404 | oui | aucun | READY_WITH_UI_HANDLING | `.png`/`.pdf` servis par `MerchantStoreQrAssetController` (contrôleur brut) : absents d'OpenAPI → téléchargement à décrire à la main dans le SDK (§5) |
| Marchand | Version minimale d'app | *(inexistant)* | GET | public | — | — | — | — | — | GAP_BLOCKING | **Issue créée** (§7) — commune aux deux apps |
| Marchand | Suspension abonnement (effets) | codes `STORE_SUSPENDED_FOR_SUBSCRIPTION` (409, à la soumission client), `MERCHANT_ACCOUNT_INACTIVE` (403) | — | — | — | — | stables | — | — | READY | Gestion V1 = affichage des états (#564 cas 17) ; écrans abonnement web |
| Marchand | Équipe, export CSV, statistiques, thème, profil supérette, price-history, subscription/billing | divers | — | MERCHANT | checker | — | — | — | — | OUT_OF_SCOPE | Web V1 (#564 §1) ; `profile`, `price-history` ✘ contrat — à documenter lors d'un éventuel repêchage |

**Décompte** : 78 lignes — READY : 48 · READY_WITH_UI_HANDLING : 12 · GAP_NON_BLOCKING : 1 · GAP_BLOCKING : 4 (2 endpoints × 2 apps : push natif porté par #566, version minimale → issue §7) · OUT_OF_SCOPE : 13.

---

## 2. Authentification et sessions — les 14 décisions

État constaté dans : `config/packages/lexik_jwt_authentication.yaml` (`token_ttl: 3600`, `user_id_claim: email`, RS256), `config/packages/security.yaml` (firewalls `login`/`api` stateless, checkers), `src/Security/LoginUserChecker.php`, `src/Security/DeletedUserChecker.php`, `src/EventSubscriber/LastLoginAtSubscriber.php`, `src/EventSubscriber/MerchantPasswordChangeRequiredSubscriber.php`, `composer.json` (aucun bundle refresh). Payload de login réel : `{"token": "...", "password_change_required": bool}` — **pas d'`expires_in`** (l'exemple d'ADR-0003 est obsolète sur ce point).

| # | Décision | État actuel constaté | Risque mobile | Décision proposée |
|---|---|---|---|---|
| 1 | Durée de vie de l'access token | 3600 s, RS256, stateless (`token_ttl`) | Re-login par mot de passe **toutes les heures** : rédhibitoire en usage terrain | **Conserver 1 h** pour l'access token ; le confort vient du refresh (décision 2) |
| 2 | Refresh token | **Absent** (ADR-0003 : « non implémenté dans le MVP » ; aucun bundle, aucun endpoint) | Sans refresh, session mobile inacceptable ; stocker le mot de passe est exclu | **Introduire un refresh token opaque persisté** (table `refresh_tokens`, hash en base, 30 j glissants) — issue backend §7, contrat détaillé dans l'issue. Champ additif dans la réponse de login, non cassant pour la PWA |
| 3 | Rotation / usage unique | Sans objet (pas de refresh) | Un refresh volé = session illimitée | **Rotation à usage unique** : chaque refresh renvoie un nouveau couple ; réutilisation d'un token consommé → révocation de la chaîne (détection de vol) |
| 4 | Révocation au logout | Aucun endpoint logout ; déconnexion = purge locale du token | Le JWT reste valable ≤ 1 h après logout ; le refresh (futur) doit être révocable | `POST /api/auth/logout` révoque le refresh token présenté ; l'access token court expire seul. Acceptable : fenêtre résiduelle ≤ 1 h |
| 5 | Révocation après changement / reset de mot de passe | `PasswordResetConfirmProcessor` / `ChangeMerchantPasswordProcessor` : **aucune invalidation des JWT émis** (pas de `tokenVersion`, pas de listener `JWTDecoded`) ; les reset tokens eux-mêmes sont à usage unique, TTL 1 h, hachés | Un attaquant déjà en session survit ≤ 1 h à un reset | Révoquer **tous les refresh tokens** de l'utilisateur au reset/changement de mot de passe (issue §7). La fenêtre JWT résiduelle ≤ 1 h est actée comme acceptable pour le MVP mobile |
| 6 | Désactivation / suspension d'un compte | `LoginUserChecker` ne vérifie que `deletedAt` + expiration du mot de passe provisoire : **un compte `active=false` obtient un JWT**. Marchand : bloqué en aval à chaque requête (`MerchantMeProvider`, `MerchantShopAccessChecker` → 403 `MERCHANT_ACCOUNT_INACTIVE`, membership relue à chaque appel — parcours 3.4 de #564 validé). Client : aucun blocage `active=false` | Marchand : risque contenu (aucune donnée servie). Client suspendu : accès intact | Durcir `LoginUserChecker` (refuser `active=false` au login et au refresh) + révoquer les refresh tokens à la suspension admin — inclus dans l'issue refresh (§7) |
| 7 | Sessions simultanées multi-appareils | JWT stateless : illimitées de fait, invisibles | Aucun risque fonctionnel ; aucune visibilité | **Autorisées** : un refresh token actif par appareil (colonne `client` : `mobile_client`/`mobile_merchant`/`web`) |
| 8 | Liste / révocation d'un appareil perdu | Impossible (rien n'est persisté) | Téléphone perdu = session active jusqu'à expiration | **Post-V1** : la révocation individuelle s'appuiera sur `refresh_tokens` + le modèle `mobile_device` (#566). En V1 : le reset de mot de passe (décision 5) sert de révocation globale |
| 9 | Token expiré pendant une action | 401 Lexik `{code:401, message:"Expired JWT Token"}` | Perte de contexte, double soumission au retry naïf | Intercepteur : sur 401 → refresh silencieux → **rejouer une seule fois** les requêtes idempotentes ; pour les actions non idempotentes (§4), re-GET de l'état avant re-tentative. Sans refresh disponible : purge + écran Connexion avec retour au contexte (cadrages §4 cas 4/1) |
| 10 | Restauration d'une sauvegarde téléphone | Aucun mécanisme serveur | Un refresh token restauré d'une vieille sauvegarde peut resurgir | La rotation à usage unique neutralise le cas : un token déjà consommé → 401 + révocation de chaîne → re-login. Keychain iOS : exclure l'entrée du backup (`kSecAttrAccessibleWhenUnlockedThisDeviceOnly`) via expo-secure-store |
| 11 | Horloge et tolérance au décalage | Validation `exp`/`iat` côté serveur uniquement ; pas de clock skew configuré côté client | Une horloge locale fausse ne doit jamais bloquer | Ne **jamais** valider l'expiration côté app d'après l'horloge locale : tenter l'appel, réagir au 401 (décision 9). Optionnel : `Date` de la réponse HTTP comme référence d'affichage |
| 12 | Stockage sécurisé Keychain/Keystore | Sans objet backend ; PWA : `localStorage` (ADR-0003) | Token en clair = compromission locale triviale | **expo-secure-store** (Keychain iOS / Keystore Android) pour JWT + refresh, conforme ADR-0007 (package `@kadhia/auth`) |
| 13 | Aucune persistance en stockage non sécurisé | — | Fuite via cache/logs/analytics | Règle déjà actée dans les cadrages (§6) : jamais de token dans le cache TanStack Query, les logs (`ClientLogProcessor` masque déjà `refresh_token` côté backend), ni les événements analytics |
| 14 | Aucun secret serveur dans le binaire | Clés RS256 privées côté serveur uniquement (`config/jwt/`, gitignoré) ; l'API ne requiert aucune API key | Faible | Rien à embarquer : URL d'API + clé publique éventuelle seulement. Profils EAS par environnement, jamais de secret dans le dépôt (ADR-0007) |

**Conclusion session mobile** : le modèle actuel (JWT 1 h, sans refresh ni révocation) **ne permet pas une session mobile acceptable**. Contrat cible proposé — sans implémentation ici — découpé dans l'issue backend dédiée (§7) : refresh token opaque persisté + rotation à usage unique + révocation (logout, reset, suspension, suppression) + multi-appareils, access token JWT inchangé.

---

## 3. Contrat d'erreur mobile

### Format actuel constaté (sondages HTTP locaux + tests fonctionnels)

Trois formats coexistent :

**a) Erreurs API Platform (toutes les routes ApiResource, 4xx/5xx)** — `Content-Type: application/problem+json` (ou `application/json` selon `Accept` ; `error_formats` dans `api_platform.yaml`). Le **code métier stable est porté par `detail`** :

```json
{ "type": "/errors/404", "title": "An error occurred", "status": 404, "detail": "STORE_NOT_FOUND" }
```

(Observé en local sur `GET /api/stores/by-qr/{token-inconnu}` ; en environnement dev une clé `trace` s'ajoute — absente en prod. Assertions équivalentes dans `tests/Functional/Api/*` : `$json['detail'] === 'MERCHANT_CATALOG_FORBIDDEN'`, etc. La PWA lit déjà `response.data.detail`.)

**b) Erreurs de validation (422)** — même canal, avec `violations` :

```json
{ "status": 422, "title": "An error occurred",
  "type": "/validation_errors/...",
  "detail": "email: This value is not a valid email address.\npassword: ...",
  "violations": [
    { "propertyPath": "email", "message": "This value is not a valid email address.", "code": "bd79c0ab-..." }
  ] }
```

(Observé en local sur `POST /api/auth/register/customer` avec données invalides.) Les `message` sont en **anglais** (locale Symfony par défaut) : l'app ne doit **jamais** les afficher — mapper `propertyPath` (+ contexte d'écran) sur ses propres clés i18n FR/AR.

**c) Erreurs du firewall Lexik (401 login/JWT)** — `Content-Type: application/json`, format différent :

```json
{ "code": 401, "message": "JWT Token not found" }
```

(`message` ∈ `Invalid credentials.`, `Expired JWT Token`, `Invalid JWT Token`, `JWT Token not found`, ou un code métier du checker comme `MERCHANT_TEMPORARY_PASSWORD_EXPIRED`.)

### Écarts vs le format cible de l'issue (`{code, message, request_id, violations}`)

| Attendu issue | Constat | Écart |
|---|---|---|
| `code` métier stable | Porté par `detail` (a, b) ou `message` (c) — MAJUSCULES_SNAKE | Nom de clé différent, sémantique équivalente — **pas de refonte nécessaire** |
| `message` localisable | `title` générique ; `detail` = le code lui-même ; violations en anglais | Les messages utilisateur sont de la responsabilité de l'app (clé i18n par code — déjà prévu par #563/#564 §4) |
| `request_id` | **Absent** : `CorrelationIdSubscriber` lit `X-Client-Request-Id` entrant et `CorrelationIdProcessor` le met dans les logs, mais rien n'est généré ni renvoyé | **GAP → issue backend** (§7) |
| `violations[]` uniforme | Présent et uniforme sur les 422 de validation (`{propertyPath, message, code}`) | Conforme |
| Pas de stack trace / secret | `trace` présent **en dev uniquement** ; rien en prod (comportement API Platform standard) | Conforme (à re-vérifier sur staging avant bêta) |

### Décision proposée : mapping mobile sur l'existant, sans refonte backend

1. Le SDK mobile normalise les trois formats vers un type unique `ApiError { httpStatus, code, violations?, requestId? }` :
   - `code` = `detail` si MAJUSCULES_SNAKE, sinon `message` (format Lexik), sinon `HTTP_<status>` ;
   - la logique UX route **uniquement** sur `code` + statut HTTP — jamais sur un texte traduit (règle de l'issue respectée) ;
   - `violations[].propertyPath` sert au marquage de champ, jamais à l'affichage du `message` anglais.
2. `request_id` : issue backend créée (§7) — génération serveur + en-tête `X-Request-Id` en réponse ; en attendant, l'app envoie déjà `X-Client-Request-Id` (accepté par CORS et journalisé) et affiche cet identifiant **client** sur les 5xx comme référence support.
3. Aucun changement du format problem+json d'API Platform : les deux PWA et les tests en dépendent ; une clé `code` dupliquée n'apporterait rien que le mapping SDK ne fournit pas.
4. À inscrire au contrat (`api-contract.md` § Conventions) lors de la prochaine passe documentaire : le fait que `detail` porte le code stable, le format Lexik du 401, le format des violations.

---

## 4. Idempotence et concurrence

Constat global (vérifié dans `src/Processor/` et `src/Entity/Order.php`, `PickupSession.php`) : **aucun verrou pessimiste ni champ de version optimiste** sur les flux commande/retrait. Les gardes d'état sont en PHP (lecture → vérification → flush) et donnent des 409 stables en double appel **séquentiel** (double tap, retry). La seule protection atomique est l'`UPDATE pickup_slots ... WHERE booked_count < capacity` de la soumission. Deux requêtes strictement simultanées peuvent passer une même garde (limitation reconnue en commentaire dans `CustomerPickupSessionConfirmProcessor`) — d'où la règle transverse : **verrou UI pendant l'envoi + jamais de retry aveugle sur une action non idempotente + re-GET sur tout 409**.

| Action (fichier) | Double tap | Retry après timeout | État changé ailleurs | HTTP + code métier | Clé d'idempotence nécessaire ? | Rechargement |
|---|---|---|---|---|---|---|
| **4.1 Soumission Kadhia** (`SubmitOrderProcessor`) | Verrou UI ; si la 2e requête part quand même : Kadhia non-draft → **201 avec la commande existante** (`findActiveByKadhia`) — pas de double commande en séquentiel | Sûr : rejouer renvoie 201 idempotent ou l'erreur d'origine | Créneau plein → 422 `PICKUP_SLOT_FULL` (pré-contrôle + UPDATE atomique) ; produit retiré → 422 `PRODUCT_UNAVAILABLE` ; suspension → 409 `STORE_SUSPENDED_FOR_SUBSCRIPTION` ; resoumission tardive → 422 `PARTIAL_ACCEPTANCE_EXPIRED` | 201 / 404 `KADHIA_NOT_FOUND`, `PICKUP_SLOT_NOT_FOUND` / 422 (cf. matrice) / 409 | **Non** en V1 : l'idempotence naturelle (statut Kadhia) couvre le double tap et le retry. Limite documentée : deux envois **strictement simultanés** peuvent créer 2 commandes (pas d'unicité `orders.kadhia_id`) — accepté V1 avec verrou UI ; durcissement serveur listé GAP_NON_BLOCKING (§6) | Succès → écran commande ; 422 créneau → recharger les rendez-vous ; 422 produit → retour Kadhia |
| **4.2 Accept / Reject** (`MerchantAcceptOrderProcessor`, `MerchantRejectOrderProcessor`) | 2e appel → 409 `ORDER_NOT_SUBMITTED` | Sûr après re-GET : si la commande est passée `accepted`/`rejected`, l'action a réussi | Traitée par un autre compte/PWA → 409 `ORDER_NOT_SUBMITTED` | 200 / 409 | Non (transition d'état = garde naturelle) | Tout 409 → re-GET détail + historique de statuts (`actor_name`) |
| **4.3 Acceptation partielle** (`MerchantPartiallyAcceptOrderProcessor`) | 2e appel identique → **409 `KADHIA_LINE_NOT_FOUND`** (les lignes Kadhia sont déjà supprimées — ce contrôle précède la garde de statut) ; sinon 409 `ORDER_NOT_SUBMITTED` | Après timeout : **re-GET obligatoire avant re-tentative** (si le statut est `partially_accepted`, l'action a réussi) | Idem 4.2 | 200 / 422 `NO_LINES_REJECTED`, `USE_REJECT_ENDPOINT`, `ORDER_LINE_NOT_FOUND` / 409 `ORDER_NOT_SUBMITTED`, `KADHIA_LINE_NOT_FOUND`, `ORDER_KADHIA_REQUIRED` | Non | Mapper **les deux** 409 sur « déjà traitée » + re-GET |
| **4.4 Start-preparation** (`MerchantStartPreparationProcessor`) | 2e appel → 409 `ORDER_NOT_ACCEPTED` | Sûr après re-GET | Idem | 200 / 409 | Non | Re-GET sur 409 |
| **4.5 Préparation d'une ligne** (`MerchantPrepareOrderLineProcessor`) | **Pleinement idempotent** : re-cocher → 200 sans effet ; `prepared:false` décoche | **Sûr sans précaution** — seule action rejouable aveuglément | Ligne cochée depuis la PWA → le PATCH aboutit au même état (parcours 3.9 #564 validé) | 200 / 400 `PREPARED_REQUIRED` / 409 `ORDER_NOT_PREPARING` / 404 `ORDER_LINE_NOT_FOUND` | Non | Re-GET du détail après rafale de coches |
| **4.6 Mark-ready** (`MerchantMarkReadyProcessor` + `OrderTransitionService::markReady`) | 2e appel → 409 `ORDER_NOT_PREPARING` | Sûr après re-GET (si `ready`, la session existe — contrainte unique `order_id` sur `pickup_sessions`) | Ligne décochée ailleurs → **409 `ORDER_LINES_NOT_FULLY_PREPARED`** | 200 / 409 | Non | 409 lignes → re-GET + focus 1re ligne non cochée |
| **4.7 Scan retrait** (`MerchantPickupSessionScanProcessor`) | **Double scan → 200 idempotent** (même sortie si `pickup_pending` + `scannedAt` renseigné) | Sûr : rejouer le scan est sans effet | Session utilisée → 409 `PICKUP_SESSION_ALREADY_USED` ; expirée → 409 `PICKUP_SESSION_EXPIRED` ; commande non prête → 409 `ORDER_NOT_READY` ; autre supérette → **403** `MERCHANT_CATALOG_FORBIDDEN` | 200 / 404 `PICKUP_SESSION_NOT_FOUND` / 403 / 409 | Non | 409/403 → message cas 8 (#564), pas de re-scan automatique |
| **4.8 Confirmations retrait** (client : `CustomerPickupSessionConfirmProcessor` ; marchand : `MerchantPickupSessionConfirmProcessor`) | Idempotentes tant que la session n'est pas close (`confirmedAt ??=`) ; ensuite 409 `PICKUP_SESSION_ALREADY_USED` | Sûr : rejouer une confirmation déjà posée → 200 | L'autre partie a confirmé entre-temps → la commande passe `completed` (comportement voulu). **Constat** : l'ordre des confirmations est **libre** côté serveur (le contrôle `PICKUP_SESSION_NOT_MERCHANT_CONFIRMED` n'existe que sur force-complete) ; côté client, l'expiration n'est plus vérifiée après scan (limite MVP connue) | 200 / 404 / 409 `PICKUP_SESSION_NOT_SCANNED`, `ORDER_NOT_PICKUP_PENDING`, `PICKUP_SESSION_ALREADY_USED` | Non | Polling `GET /api/me/orders/{orderId}/status` (~4 s) pendant la session, comme la PWA |
| **4.9 Validation par code / manuelle** (`MerchantRedeemByCodeProcessor`, `MerchantValidateManuallyProcessor`) | redeem : 2e appel → 404 `PICKUP_CODE_NOT_FOUND` (le code est effacé à la finalisation) ; manuel : 2e appel → 409 `ORDER_NOT_READY` | **Re-GET obligatoire avant re-tentative** : après timeout, un 404/409 peut signifier « déjà finalisée » — vérifier le statut de la commande avant tout message d'erreur | Commande finalisée ailleurs → 404/409 idem | redeem : 200 `{order_id, status}` / 404 `PICKUP_CODE_NOT_FOUND` ; manuel : 200 / 409 `ORDER_NOT_READY` / 422 note | Non | GAP_NON_BLOCKING : `pickup_code` (4 chiffres, `random_int`) **sans unicité** — collision possible entre deux commandes `ready` d'une même supérette (`findOneBy` arbitraire). Faible probabilité (≤ quelques `ready` simultanées / 10 000 codes) ; à durcir avant montée en charge (§6) |
| **4.10 Push : enregistrement / renouvellement / révocation** (`Register*/Unregister*PushSubscriptionProcessor`) | Ré-enregistrement du même endpoint → **upsert** (contrainte unique `endpoint_hash`, `refresh()` réassigne utilisateur/clés/scope) ; unregister inconnu → **204 silencieux** | Sûr (upsert / 204) ; premier enregistrement simultané ×2 → 500 possible (violation d'unicité non interceptée) — retry simple suffit | Token réaffecté à un autre compte → l'upsert transfère la souscription (comportement voulu au changement d'utilisateur) | 201 / 204 / 422 violations | Non | Sans objet — mais **Web Push only** : le cycle de vie FCM/APNs (rotation de token provider, invalidation) est à cadrer avec le modèle `mobile_device` de #566 |

**Motif « notification best-effort + 2e flush » (PR #232)** : appliqué sur accept, reject, start-preparation, mark-ready. Non appliqué (notification dans la transaction ou avant le flush principal) sur submit, partially-accept, cancel et les finalisations de retrait — un échec d'insertion de notification y ferait échouer la transition (500). Sans impact sur le contrat mobile (l'app traite le 500 générique) ; noté pour le backlog backend.

**Conclusion** : **aucune clé d'idempotence (`Idempotency-Key`) n'est nécessaire en V1.** Les transitions d'état servent de garde naturelle, les actions rejouables (scan, confirmations, préparation de ligne, push) sont idempotentes, et la soumission est idempotente en séquentiel. Règles d'or côté app : verrou UI pendant l'envoi, jamais de retry automatique sur POST non idempotent sans re-GET préalable, tout 409 → re-GET + affichage de l'état serveur (déjà actées dans #563/#564 §4).

---

## 5. OpenAPI et SDK

| Décision | Proposition |
|---|---|
| Source OpenAPI de référence | **`/api/docs.json`** (public — `docs_formats` dans `api_platform.yaml`, règle `PUBLIC_ACCESS` sur `^/api/docs`). Export local reproductible : `docker compose exec -T backend php bin/console api:openapi:export`. Vérifié : OpenAPI **3.1.0**, **189 chemins** |
| Opérations mobiles décrites ? | Sondage effectué sur l'export : `login`, `register/customer`, `kadhias/{id}/submit`, `pickup-sessions/scan`, `pickup-sessions/{id}/confirm` (client + marchand), `redeem-by-code`, `validate-manually`, `first-login/change-password`, `push-subscriptions`, `orders/{orderId}/status`, `merchant/me`, `pickup-slots` → **tous présents**. **Absents** (contrôleurs Symfony bruts) : `POST/DELETE .../local-products/{localProductId}/photo`, `GET .../qr-code.png|.pdf`, `GET /api/health` — à décrire à la main dans le SDK ou à exposer via ApiResource (action portée par la ligne GAP_NON_BLOCKING §1.2) |
| SDK TypeScript | **Oui — `openapi-typescript` + `openapi-fetch`** (types purs + client fetch léger, zéro runtime lourd, compatible React Native). Orval écarté en V1 : la génération de hooks TanStack Query couplerait le SDK à la stratégie de cache des apps ; les hooks restent dans `apps/client`/`apps/merchant` |
| Emplacement | `packages/api-sdk` du dépôt `click-and-collect-mobile` (`@kadhia/api-sdk`, ADR-0007) — **après acceptation de l'ADR** (statut « Proposé ») ; aucun code mobile dans le monorepo produit |
| Règles de régénération | Le spec exporté est **committé** dans le dépôt mobile (`packages/api-sdk/openapi.json`) avec la date et le SHA backend d'origine ; régénération sur PR dédiée ; le diff du spec **est** la revue de changement de contrat |
| Détection CI des breaking changes | Job CI backend (post-#527 ou dans la CI existante) : export `api:openapi:export` + diff avec `oasdiff` (ou `openapi-diff`) contre la version committée — échec sur suppression/renommage de champ ou de route consommée. À défaut en V1 : la régénération du SDK échoue à la compilation TypeScript, ce qui reste un filet |
| Types transverses | Pagination : enveloppe `{items, total, page, limit}` (providers custom — pas de `hydra:member` ; `CustomerNotificationListOutput` omet `limit` : à normaliser à l'occasion). Dates : ISO 8601. IDs : UUID (string). Montants : **TND en string décimale à 3 décimales** — jamais de float côté app (helper `mobile-core`) |
| Mapping des erreurs métier | Type `ApiError` du §3 dans `@kadhia/api-sdk` ; la table des codes de `api-contract.md` § « Codes d'erreur MVP » sert de source d'enum (complétée par les codes relevés au §1/§4 qui en sont absents : `AUTH_EMAIL_ALREADY_EXISTS`, `KADHIA_NOT_DRAFT`, `KADHIA_NOT_DELETABLE`, `PICKUP_SLOT_CLOSED`, `PICKUP_SLOT_MUST_LAST_ONE_HOUR`, `PARTIAL_ACCEPTANCE_EXPIRED`, `ORDER_NOT_ACCEPTED`, `ORDER_LINES_NOT_FULLY_PREPARED`, `PICKUP_CODE_NOT_FOUND`, `ORDER_HISTORY_INVALID_DATE_RANGE`, `NO_LINES_REJECTED`, `USE_REJECT_ENDPOINT`, `MERCHANT_PASSWORD_CHANGE_REQUIRED`, `MERCHANT_TEMPORARY_PASSWORD_EXPIRED`, `MERCHANT_MULTIPLE_ACTIVE_STORES`, `MERCHANT_CATALOG_FORBIDDEN`…) |

---

## 6. Compatibilité et exploitation (constats complémentaires)

| Sujet | Constat | Évaluation mobile |
|---|---|---|
| Versionnement d'API | Aucun (`/api` sans version) ; politique implicite : évolutions additives | Acceptable **si** l'endpoint de version minimale existe (issue §7) — c'est lui qui autorise les ruptures futures |
| Maintenance mode | Inexistant | Couvert par le bloc `maintenance` de l'endpoint `mobile/config` proposé (issue §7) |
| Timeouts / retries | Aucune directive serveur | Côté app : timeout 10–15 s ; retry automatique **uniquement** sur GET et actions idempotentes (§4) |
| Pagination mobile | Défauts 20/page (max 100), enveloppe `{items,total,page,limit}` | Adapté au mobile ; `limit` ≤ 20 recommandé sur réseau tunisien |
| Compression | `docker/nginx/backend.conf` : **pas de gzip/brotli** ; dépendrait du reverse proxy de prod (hors dépôt) | GAP_NON_BLOCKING : activer gzip sur les réponses JSON avant bêta mobile (payload catalogue) — action ops, pas d'issue API |
| Cache HTTP | Quasi absent : seul `GET /api/stores/{id}/theme` a `Cache-Control: public, max-age=300` ; pas d'ETag/Last-Modified ; `Vary: Content-Type, Authorization, Origin` | Acceptable V1 : cache applicatif TanStack Query (cadrages §6). ETag sur le catalogue = optimisation post-V1 |
| Corrélation | `X-Client-Request-Id` accepté et journalisé ; rien de généré/renvoyé | Issue request_id (§7) |
| Logs sans données sensibles | `ClientLogProcessor` masque les tokens (dont `refresh_token`) ; canaux Monolog dédiés (`front.prod.log` pour les logs client via `POST /api/client-logs`, public) | OK ; `POST /api/client-logs` est réutilisable par les apps mobiles pour la remontée d'erreurs (`@kadhia/observability`) |
| Staging | Profils EAS `development/staging/production` prévus (ADR-0007) ; environnement staging backend hors périmètre de cet audit | À confirmer en MOBILE-006 (#567) |
| TLS/HTTPS | Obligatoire de fait (JWT Bearer) ; ATS iOS / cleartext Android interdiront l'HTTP | Config réseau des apps : HTTPS only, pas d'exception cleartext |
| Rate limiting / anti-abus | **Absent** : pas de `symfony/rate-limiter`, pas de `login_throttling` sur le firewall — login, register, password-reset et `client-logs` publics non limités | **GAP_NON_BLOCKING (P1 avant bêta publique)** : ajouter `login_throttling` + limiteurs sur register/reset. Issue à créer au moment du durcissement sécurité (MOBILE-006/#567 la référence) — pas bloquant pour démarrer le développement mobile |
| Upload de fichiers | Un seul flux mobile V1 : photo produit local (multipart, contrôleur brut) | Cf. ligne GAP_NON_BLOCKING §1.2 |

---

## 7. Synthèse des écarts et issues créées

### GAP_BLOCKING (P0 — conditionnent le développement mobile)

| Écart | Preuve | Issue |
|---|---|---|
| Session mobile impossible : JWT 1 h sans refresh, sans révocation (logout, reset, suspension), login accepté pour un compte suspendu | `lexik_jwt_authentication.yaml`, `composer.json`, `LoginUserChecker`, `PasswordResetConfirmProcessor` (§2) | **#616** — refresh token opaque persisté, rotation à usage unique, révocation, multi-appareils |
| Aucun `request_id` de corrélation restitué au client (exigé par #563 §4 cas 16 et #564 §4 cas 18) | `CorrelationIdSubscriber` (entrant seulement), sondage : aucun en-tête ni champ en réponse (§3) | **#617** — génération serveur + en-tête `X-Request-Id` |
| Aucun endpoint public de version minimale d'application (exigé par ADR-0007 ; écrans E18 #563 / E17 #564) | Recherche `src/` : néant ; `/api/health` = liveness only (§6) | **#618** — `GET /api/mobile/config` (version minimale + maintenance) |
| Enregistrement push **Web Push uniquement** (`endpoint`/`p256dhKey`/`authKey`) — aucun canal FCM/APNs/Expo | `Dto/PushSubscriptionInput.php`, `Entity/PushSubscription.php` (§1, §4.10) | **Porté par #566** (modèle `mobile_device` + enregistrement de tokens natifs — §1 de #566 le couvre intégralement ; pas d'issue doublon créée ici) |

### GAP_NON_BLOCKING (P1 avant bêta / P2 post-bêta)

| Écart | Priorité | Action (sans issue à ce stade — backlog documenté) |
|---|---|---|
| Rate limiting absent (login/register/reset publics non limités) | P1 avant bêta publique | Issue de durcissement à créer avec MOBILE-006 (#567) : `login_throttling` + `symfony/rate-limiter` |
| `pickup_code` 4 chiffres non unique par supérette (collision → mauvaise commande finalisée par `redeem-by-code`) | P1 avant montée en charge | Contrainte d'unicité par boutique parmi les commandes `ready`, ou re-tirage à la création |
| Endpoints hors OpenAPI (photo produit local, QR `.png`/`.pdf`) → invisibles du SDK généré | P1 pour le SDK | Exposer via ApiResource ou description manuelle dans `@kadhia/api-sdk` ; documenter les limites taille/format de la photo |
| Compression gzip non configurée sur nginx | P1 avant bêta | Action ops (config reverse proxy) |
| Double soumission strictement simultanée d'une même Kadhia possible (pas d'unicité `orders.kadhia_id`, pas de verrou) | P2 (verrou UI suffisant en V1) | Contrainte partielle unique ou verrou pessimiste au submit |
| Libération de créneau par décrément ORM (reject) et décrément `toBinary()` non typé (cancel) — risques de perte de mise à jour à vérifier | P2 | Aligner sur l'UPDATE atomique du submit |
| Notifications `title_fr`/`body_fr` sans arabe | P2 (décision produit, commune #563/#564 §8) | Ajouter `title_ar`/`body_ar` si le repli FR est refusé |
| Endpoints livrés hors contrat documenté (DELETE Kadhia, `GET /me/stores/{storeId}/kadhias`, `redeem-by-code`, `validate-manually`, replacements, favoris, share-links, profile marchand, price-history) | P2 (documentaire) | Passe de mise à jour de `api-contract.md` — les sémantiques tranchées par cet audit (§1) servent de source |
| Écarts mineurs cadrages vs code : submit double envoi → 201 idempotent (pas `KADHIA_NOT_EDITABLE`) ; confirmations retrait sans ordre imposé ; `reject.reason` facultatif ; mark-ready → 409 (pas 422) ; history → 422 (pas 400) ; scan autre supérette → 403 (pas 404) ; register → `AUTH_EMAIL_ALREADY_EXISTS` | P2 (documentaire) | Corriger #563/#564 et le contrat lors de la même passe |

### Mise à jour du contrat

Conformément à l'issue (« sans inventer de route livrée »), **aucune modification de `docs/architecture/api-contract.md` dans cette PR** : les décisions du §2/§3/§5 restent des propositions tant qu'elles ne sont pas validées ; la passe documentaire P2 ci-dessus les reportera une fois les issues arbitrées.
