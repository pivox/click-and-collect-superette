# Push natif, QR, App Links et Universal Links (MOBILE-005, #566)

> Cadrage. **Aucun code applicatif, aucun secret provider, aucune modification de
> `docs/architecture/api-contract.md`** (mise à jour du contrat seulement après validation
> des décisions, conformément à l'issue). L'existant cité a été vérifié dans le code
> backend (`apps/backend/src/`), la configuration (`config/packages/messenger.yaml`) et
> les routes de la PWA (`apps/frontend/src/app/`). Les travaux backend qui découlent de
> ce cadrage sont portés par l'issue dédiée **#620** (§10) — les gaps transverses déjà
> ouverts (#616 refresh token, #617 request_id, #618 version minimale) ne sont pas dupliqués.
>
> Amont : ADR-0007 (#562, Expo retenu), cadrages écrans #563 (`client-scope.md`) et
> #564 (`merchant-scope.md`), audit API #565 (`api-readiness.md`). `FRONTEND_URL` : #543
> (`docs/ops/frontend-url.md`).

## 1. Principe obligatoire

```text
Notification in-app = source de vérité persistée
Push natif        = canal externe best-effort
Lien/QR           = point d'entrée, jamais autorisation
Backend           = vérification finale du rôle, de l'ownership et de l'état métier
```

Conséquences appliquées dans tout ce document :

- **Aucune transition de commande ou de retrait ne dépend d'un push.** Le motif existant
  « notification best-effort + second `flush()` dans le try-catch » (PR #232, appliqué sur
  accept/reject/start-preparation/mark-ready) est le modèle : le push natif s'y insère au
  même endroit, isolé par try-catch, jamais bloquant.
- **Un push est un signal, pas un état.** À l'ouverture, l'app recharge toujours la ressource
  (`GET`) et affiche l'état serveur réel — jamais le contenu du push.
- **Un lien ou un QR n'autorise rien.** Il transporte au plus un identifiant opaque ; le
  backend revalide authentification, rôle, ownership et état métier à chaque requête
  (`MerchantShopAccessChecker`, filtrage client, gardes de statut — audit #565 §1/§4).
- **Le refus de permission push ne bloque aucun parcours** : l'in-app (cloche E16 client /
  E11 marchand) reste complet sans push.

## 2. Existant vérifié (base du cadrage)

### 2.1 Notifications in-app — types stables constatés dans `NotificationService`

| Méthode | Type persisté | Constat |
|---|---|---|
| `notifyCustomerOrderAccepted/Rejected/PartiallyAccepted/Preparing/Ready/Completed` | **`null`** | Aucun type stable sur le cycle client — gap, lot A de #620 |
| `notifyCustomerPickupReminder` | `pickup_reminder` | Dédupliqué par `(order, type)` |
| `notifyCustomerMerchantResponseTimeout` | `merchant_response_timeout` | Dédupliqué |
| `notifyCustomerPartialAcceptanceReminder` | `partial_acceptance_reminder` (+ variantes de cycle) | Dédupliqué |
| `notifyCustomerPartialAcceptanceTimeout` | `partial_acceptance_timeout` | Dédupliqué |
| `notifyMerchantOrderSubmitted` | `merchant_order_submitted` | Multi-destinataires (MERCHANT-TEAM-005), dédupliqué par `(order, type, user)` |
| `notifyMerchantOrderCancelled` | `merchant_order_cancelled` | idem |
| `notifyMerchantPickupCompleted` | `merchant_pickup_completed` | idem |

L'entité `Notification` porte déjà `titleFr/titleAr/bodyFr/bodyAr`, `order`, `type`, `read` :
la localisation AR du push natif ne demande **aucun** nouveau contenu, seulement la sélection
par langue de l'appareil (le Web Push actuel n'envoie que le FR).

### 2.2 Socle Web Push (#376) — conservé tel quel, non migré

- `Entity/PushSubscription` : `endpoint`/`p256dhKey`/`authKey`, unicité `endpoint_hash`
  (sha256), `scope`, compteurs `last_success_at`/`last_failure_at`/`failure_count`,
  `onDelete: CASCADE` sur l'utilisateur.
- `Dto/PushSubscriptionInput` : `endpoint` **URL `https` obligatoire** → structurellement
  inutilisable pour un token Expo/FCM/APNs (GAP_BLOCKING acté par #565 §1, porté ici).
- `WebPushService` : envoi **synchrone en ligne** (timeout 2 s), VAPID optionnel (no-op sans
  clés), payload `{title, body, url}` FR uniquement ; upsert par `endpoint_hash` avec
  réaffectation d'utilisateur ; unregister inconnu → 204.
- Endpoints : `POST /api/me/push-subscriptions` (+`/unregister`) et
  `POST /api/merchant/push-subscriptions` (+`/unregister`).

**Décision : coexistence, pas de migration.** `push_subscriptions` reste le canal PWA ;
le natif a son propre modèle (§3). Le dispatch de `NotificationService` alimente les deux.

### 2.3 Messenger (S7-009) — réutilisé tel quel

`config/packages/messenger.yaml` : transport `async` (`MESSENGER_TRANSPORT_DSN`, doctrine
persistant), `failure_transport: failed`, `retry_strategy` (3 essais, délai 1 s, multiplicateur
2), `in-memory://` en test, worker Supervisor documenté. Monitoring : `GET /api/admin/ops/messenger`
(#353). Le push natif n'ajoute **aucune** infrastructure : un message routé sur `async` suffit.

### 2.4 QR existants

- **QR magasin** : `Shop.qrCodeToken` opaque ; `MerchantStoreQrTargetUrlFactory` produit
  `{FRONTEND_URL}/stores/by-qr/{qrCodeToken}` (token `rawurlencode`é) ; rendus PNG/PDF
  imprimables (#355) ; résolution publique `GET /api/stores/by-qr/{qrCodeToken}`.
- **QR retrait** : `PickupSession` — token UUID v4 unique (`UNIQ_PICKUP_SESSIONS_TOKEN`),
  TTL 24 h (`expiresAt`), créé à `mark-ready`, plus `pickup_code` 4 chiffres de secours.
  La PWA affiche le `qr_payload` retourné par `GET /api/me/orders/{orderId}/pickup-session` ;
  le marchand scanne via `POST /api/merchant/pickup-sessions/scan`.

### 2.5 Liens sortants existants (tous dépendants de `FRONTEND_URL`, #543)

| Lien | Construction constatée |
|---|---|
| QR magasin | `{FRONTEND_URL}/stores/by-qr/{token}` (`MerchantStoreQrTargetUrlFactory`) |
| Invitation marchand (email) | `{FRONTEND_URL}/merchant/invitation?token=...` (`MerchantInvitationEmailSender`) |
| Partage Kadhia (post-V1) | `{FRONTEND_URL}/kadhia/share/{token}` (`KadhiaShareLinkService`) |
| Reset mot de passe | routes PWA `/forgot-password`, `/reset-password` (groupe `(auth)`) |

## 3. Modèle d'appareil et de token — `MobileDevice`

Nouvelle entité `mobile_devices`, distincte de `push_subscriptions` (coexistence §2.2) :

```text
mobile_device
- id                  uuid (v4)
- user_id             FK users, onDelete CASCADE
- application         client | merchant          (séparation stricte, décision 8)
- platform            android | ios
- provider            expo                        (extensible : fcm | apns — §4)
- push_token          text — stocké en clair : nécessaire à l'envoi (un hash seul ne
                      permettrait pas d'appeler le provider) ; protégé par l'accès base
- push_token_hash     sha256, UNIQUE              (clé d'upsert, même motif que endpoint_hash)
- locale              fr | ar                     (sélection titre/corps du push)
- timezone            ex. Africa/Tunis
- app_version         semver de l'app
- os_major_version    nullable — version majeure seule (minimisation, décision 9)
- enabled             bool                        (préférence utilisateur in-app)
- last_seen_at        datetime
- revoked_at          datetime nullable
- revocation_reason   nullable : logout | provider_rejected | account_deleted | inactive
- created_at / updated_at
```

Index : `user_id`, `(user_id, application, enabled)`. **Aucun IMEI, numéro de série,
identifiant matériel ou publicitaire** — le token push est le seul identifiant d'appareil,
et il est révocable.

### Les décisions demandées par l'issue (§1)

| # | Question | Décision | Justification |
|---|---|---|---|
| 1 | Multi-appareils | **Oui**, illimité par utilisateur | Un marchand a souvent téléphone + tablette de comptoir ; cohérent avec la décision « sessions multiples » de #616 |
| 2 | Réaffectation après logout/login | **Oui, par upsert** : un `push_token_hash` déjà connu est réaffecté à l'utilisateur nouvellement connecté (réactivé, `revoked_at` remis à null) | Même comportement que le Web Push existant (`refresh()`), vérifié §4.10 de #565 ; un appareil physique n'a qu'un token — il suit son utilisateur courant, jamais deux comptes à la fois |
| 3 | Enregistrement / mise à jour | `POST /api/mobile/devices` (upsert) au login et à chaque démarrage d'app ; `PATCH /{id}` pour `locale`, `timezone`, `app_version`, `enabled`, et rafraîchir `last_seen_at` | L'app est la seule source du token (expo-notifications) ; le POST au démarrage absorbe les rotations silencieuses |
| 4 | Rotation du token provider | Nouveau token → nouveau `POST` (nouvelle ligne) ; l'ancienne ligne meurt par réponse provider définitive (décision 6) ou purge (décision 7) | Expo/FCM ne notifient pas le serveur d'une rotation : seul l'appareil la voit ; aucun couplage à un « install id » intrusif |
| 5 | Désactivation au logout | `DELETE /api/mobile/devices/{id}` → `revoked_at` + raison `logout`, **best-effort** (le logout aboutit même hors ligne ; la ligne restante meurt par la décision 6 au premier envoi refusé, ou est réaffectée au prochain login — décision 2) | Un push ne doit jamais atteindre un appareil où l'utilisateur s'est déconnecté ; mais le logout ne doit pas dépendre du réseau |
| 6 | Invalidation sur réponse provider définitive | `DeviceNotRegistered` (Expo) / `UNREGISTERED` (FCM) / `410 Unregistered` (APNs) → `revoked_at` + raison `provider_rejected`, plus jamais sollicité | Évite les envois en boucle vers des tokens morts ; motif déjà présent en germe (`failure_count` Web Push) |
| 7 | Nettoyage des inactifs | Purge planifiée (commande cron/scheduler) : suppression physique des lignes `revoked_at` > 30 j et des lignes `last_seen_at` > 180 j | Minimisation : un token est une donnée personnelle ; 180 j couvre une app installée mais peu ouverte |
| 8 | Séparation Client/Marchand | **Stricte** : `application` fixé à l'enregistrement et contrôlé contre le rôle du JWT (CUSTOMER→`client`, MERCHANT→`merchant`, 403 sinon) ; le dispatch filtre toujours par `application` | Un push marchand ne doit jamais atteindre l'app client du même humain (comptes distincts, mais défense en profondeur) ; prolonge le `scope` du Web Push |
| 9 | Environnements dev/staging/prod | **Pas de champ `environment`** : chaque environnement backend a sa propre base (les devices y sont naturellement cloisonnés) ; côté app, un projet EAS/`projectId` par profil (`development`/`staging`/`production`, ADR-0007) rend les tokens non interchangeables | Un champ dupliquerait une séparation déjà garantie par l'infrastructure ; les credentials par environnement relèvent de MOBILE-006 (#567) |
| 10 | Minimisation des métadonnées | Champs ci-dessus uniquement ; `os_major_version` nullable et tronquée à la version majeure ; pas de `user_agent`, pas de modèle d'appareil, pas de géolocalisation | Seul ce qui sert l'envoi (token, locale) et le support (versions) est stocké |
| 11 | Suppression du compte (S7-003) | **Suppression physique immédiate** de tous les devices : `onDelete: CASCADE` + purge explicite dans `CustomerDeleteAccountProcessor` (le soft delete du `User` ne suffit pas — le token doit disparaître) | Un compte supprimé ne doit plus jamais recevoir de push ; aligné sur l'anonymisation existante |

### Contrats API proposés (gaps uniquement — issue #620)

```text
POST   /api/mobile/devices        # upsert par push_token_hash — 201/200
PATCH  /api/mobile/devices/{id}   # métadonnées + last_seen_at — 404 si autrui
DELETE /api/mobile/devices/{id}   # révocation logique — 204 idempotent
```

`POST /api/mobile/devices/test-notification` : **non retenu en V1** — non justifié (le
canal se vérifie avec les événements réels en staging et l'outillage Expo), et toute route
d'envoi arbitraire est une surface d'abus. Les endpoints Web Push existants restent inchangés.

## 4. Provider et abstraction d'envoi

### 4.1 Comparatif

| Critère | **Expo Push Service (retenu)** | FCM + APNs directs |
|---|---|---|
| Cohérence stack | Natif avec expo-notifications (ADR-0007) | Nécessite config plugins/prebuild + gestion tokens hétérogènes |
| Côté backend | 1 API HTTP, 1 format de token (`ExponentPushToken[...]`), 2 plateformes couvertes | 2 intégrations (HTTP v1 OAuth Google + APNs JWT p8), 2 formats de token |
| Credentials à gérer | Clés FCM/APNs déposées une fois dans EAS ; le backend n'en détient aucune | Clés FCM **et** APNs dans les secrets backend |
| Erreurs/reçus | `DeviceNotRegistered` + tickets/receipts explicites | Codes distincts par provider, à normaliser soi-même |
| Limites | Dépendance au service Expo (indisponibilité = pushs retardés — acceptable : best-effort) ; quotas larges, gratuits | Contrôle total, aucune dépendance tierce supplémentaire |
| Migration future | Champ `provider` + abstraction §4.2 : passage à FCM/APNs sans toucher au métier | — |

**Décision : Expo Push Service d'abord** — cohérent avec ADR-0007 (« Push FCM/APNs via
expo-notifications ») et avec une petite équipe ; la sortie éventuelle est préparée par
le champ `provider` du modèle et l'interface ci-dessous, pas improvisée le jour venu.

### 4.2 Abstraction backend

```php
interface PushSenderInterface   // mockable (pattern #9 : interface, jamais la classe finale)
{
    public function send(PushMessage $message, MobileDevice $device): PushSendResult;
}
// PushSendResult : Success | TemporaryFailure | PermanentFailure(reason)
final readonly class ExpoPushSender implements PushSenderInterface { ... }
```

Les services métier ne connaissent que l'interface ; `NotificationService` ne connaît même
qu'un dispatcher (message Messenger). Ajouter FCM direct = ajouter un adaptateur + router
par `device.provider`.

### 4.3 Chaîne d'envoi (diagramme demandé par l'issue)

```text
Transition métier (processor)
   │  flush() principal — la commande est déjà sauvée : rien en aval ne peut la casser
   ▼
NotificationService ── persist Notification (in-app, source de vérité)
   │  try { … ; flush(); dispatch(SendNativePushMessage(notificationId)) } catch → log warning
   │  (pattern best-effort + second flush, PR #232 — l'échec du dispatch n'atteint jamais le métier)
   ▼
Messenger transport `async` (doctrine persistant, S7-009)
   │  retry_strategy existante : 3 essais, backoff ×2 ; échec final → transport `failed`
   ▼
SendNativePushHandler
   │  1. recharge la Notification par id (absente → ack silencieux : l'état a changé)
   │  2. résout les MobileDevice actifs du destinataire (enabled, non révoqués,
   │     application = client|merchant selon le type)
   │  3. construit le payload minimal (§6) ; titre/corps choisis par device.locale
   │     depuis title_fr/ar, body_fr/ar déjà persistés
   ▼
PushSenderInterface (ExpoPushSender)
   │  Success            → last_seen_at ; log info
   │  TemporaryFailure   → exception → retry Messenger (backoff)
   │  PermanentFailure   → revoked_at + provider_rejected, PAS d'exception (pas de retry)
   ▼
FCM / APNs → appareil (tap → routage §7.4)
```

Engagements de l'issue, point par point :

- **Files asynchrones** : transport `async` existant, aucun transport nouveau.
- **Retries / backoff** : la `retry_strategy` existante (3 essais, 1 s ×2) s'applique telle
  quelle ; seules les erreurs **temporaires** (réseau, 5xx, rate limit Expo) redéclenchent.
- **Erreurs temporaires vs définitives** : classées par l'adaptateur (`PushSendResult`) ;
  une erreur définitive révoque le device (décision 6) et **ne consomme pas de retry**.
- **Idempotence** : le dispatch est émis **une fois par notification** (après son flush) ;
  le message ne porte que `notificationId` ; côté appareil, le **collapse id provider =
  `notification_id`** (`collapse_key` Android / `apns-collapse-id` iOS, exposés par Expo) :
  un retry après envoi partiel remplace la notification déjà affichée au lieu de la dupliquer.
- **Corrélation avec l'in-app** : le payload push référence `notification_id` — le tap peut
  marquer la notification lue (`PATCH /read` existant) et l'in-app reste l'historique unique.
- **Métriques** : logs Monolog canal `notification` (motifs `notification.push_dispatch_*`
  existants) + compteurs par device (succès/échec, comme `PushSubscription`) ; la santé de la
  file est déjà visible via `GET /api/admin/ops/messenger` (#353) et le transport `failed`.
- **Jamais bloquant** : dispatch dans le try-catch post-flush (PR #232) ; handler async ;
  au pire, aucun push ne part et l'in-app fait foi.

## 5. Événements push V1

Attributs communs à tous les événements :

- **Payload minimal** (§6) : `{ "type": "...", "notification_id": "<uuid>", "order_id": "<uuid>", "route": "..." }`
  — identifiants opaques uniquement.
- **Titres/corps FR et AR** : **réutilisation à l'identique des libellés in-app** de
  `NotificationService` (déjà bilingues en base) — aucun texte nouveau à maintenir ; la
  langue est choisie par `device.locale`.
- **Comportement si l'état a changé** : invariant — l'app ouvre la route, recharge par `GET`
  et affiche l'état serveur (parcours 3.8 de #563 : « notification d'une commande déjà
  finalisée » → l'écran montre l'état final, sans erreur) ; déconnecté → login puis reprise
  de la destination (parcours 3.7/3.2 de #563).

### 5.1 Client (app `client` — routes = écrans de #563)

| Événement | Type stable | Existant ? | Condition d'envoi | Priorité | Route | Déduplication (collapse) | Expiration (TTL push) |
|---|---|---|---|---|---|---|---|
| Commande acceptée | `order_accepted` | in-app oui, type à créer (#620 lot A) | Passage `submitted` → `accepted` | Haute | E14 Suivi (`/orders/{orderId}`) | `notification_id` | 24 h |
| Partiellement acceptée | `order_partially_accepted` | idem | Passage → `partially_accepted` | **Haute** (action client requise) | E14 → contexte acceptation partielle → E11 | `notification_id` | jusqu'à `startsAt − 2 h` (l'expiration métier annule ensuite) |
| Refusée | `order_rejected` | idem | Passage → `rejected` | Haute | E14 | `notification_id` | 24 h |
| Prête | `order_ready` | idem (Web Push déjà branché) | Passage → `ready` | **Haute** | E14 (bouton vers E15 Retrait) | `notification_id` | 24 h (TTL de la `PickupSession`) |
| Rappel avant retrait | `pickup_reminder` | **complet** (type + dédup `(order, type)` + corps contextualisé supérette/heure) | 1 h avant le créneau, commande `ready` | Normale | E14 | `(order, type)` côté in-app + `notification_id` | 1 h (au-delà, le rappel est périmé — ne pas livrer en retard) |
| Retrait finalisé | `order_completed` | in-app oui, type à créer | Passage → `completed` | Basse (silencieuse — pas de son) | E14 | `notification_id` | 24 h |
| Annulation auto (marchand sans réponse) | `merchant_response_timeout` | **complet** | Expiration Messenger existante | Haute | E14 | `(order, type)` + `notification_id` | 24 h |
| Rappel de re-soumission (action client) | `partial_acceptance_reminder` | **complet** | `startsAt − 4 h`, toujours `partially_accepted` | Haute | E14 → E11 | par cycle + `notification_id` | jusqu'à `startsAt − 2 h` |
| Annulation auto (partielle non confirmée) | `partial_acceptance_timeout` | **complet** | Expiration Messenger existante | Haute | E14 | `(order, type)` + `notification_id` | 24 h |

**Tranchés hors push V1 (client)** :

- **Préparation commencée** (`order_preparing`) : **in-app seul, pas de push**. Aucune action
  client, l'événement « prête » suit de près — pousser les deux double le bruit pour zéro
  valeur (fatigue notification = désabonnement). Le type stable est tout de même créé (lot A)
  pour l'historique in-app.
- **Incident nécessitant une action client** : **hors V1** — aucun socle in-app n'existe
  (vérifié : les processors incident admin ne créent aucune `Notification`). Un push sans
  in-app violerait le principe §1 (la source de vérité doit exister d'abord). À recadrer avec
  le produit si le besoin terrain se confirme.

### 5.2 Marchand (app `merchant` — routes = écrans de #564 ; destinataires multiples via `MerchantNotificationRecipientResolver`, chaque compte éligible avec ses propres devices)

| Événement | Type stable | Existant ? | Condition d'envoi | Priorité | Route | Déduplication | Expiration |
|---|---|---|---|---|---|---|---|
| Nouvelle commande | `merchant_order_submitted` | **complet** (Web Push déjà branché) | Soumission d'une Kadhia sur une supérette de l'organisation | **Maximale** (canal Android « Commandes », importance high, son) | E05 Détail commande (`/merchant/orders/{orderId}`) — conforme #564 E04 « push nouvelle commande → E05 directement » | `(order, type, user)` in-app + `notification_id` | jusqu'à `startsAt − 2 h` (ensuite l'expiration auto annule — un push tardif serait faux) |
| Commande annulée | `merchant_order_cancelled` | **complet** | Annulation client d'une `submitted` | Haute | E05 (lecture seule) | `(order, type, user)` + `notification_id` | 24 h |

**Tranchés (marchand)** :

- **Retrait finalisé** (`merchant_pickup_completed`) : in-app seul — le marchand est
  physiquement présent à la remise ; un push est redondant.
- **Rappel de traitement** : **retenu dans le principe, reporté post-V1**. Aucun équivalent
  in-app n'existe (les rappels existants sont côté client) : c'est un nouveau métier
  (nouveau type + message différé, ex. à `startsAt − 3 h` si toujours `submitted`, avant
  l'annulation auto de `startsAt − 2 h`), pas un simple branchement. Créer d'abord l'in-app,
  brancher le push ensuite — noté comme extension dans #620, non engagée en V1.
- **Incident opérationnel prioritaire** : hors V1, même raison que côté client (pas de socle
  in-app incident).
- **Événement d'abonnement bloquant** : **hors Mobile V1** — la gestion d'abonnement est web
  V1 (#564 §1.8) ; l'app n'affiche que les effets (`STORE_SUSPENDED_FOR_SUBSCRIPTION`,
  `MERCHANT_ACCOUNT_INACTIVE`). Les rappels de paiement existants (`SendMerchantPaymentReminderMessage`)
  restent sur leurs canaux actuels.

## 6. Confidentialité du push (issue §4)

Règles absolues, applicables à l'écran verrouillé :

- **Payload strictement minimal** : `type`, `notification_id`, `order_id`, `route` — tous
  opaques. L'app authentifiée recharge la ressource ; le push ne transporte jamais la donnée.
- **Jamais** : contenu ou lignes de Kadhia, montants, nom/téléphone/email/adresse du client,
  note libre, token JWT, token de `PickupSession`, `qrCodeToken`, `pickup_code`.
- **Titres/corps** : les libellés in-app existants respectent déjà cette règle (« Une
  nouvelle Kadhia a été soumise », « Votre commande est prête… ») — aucun ne cite un contenu,
  un montant ni une coordonnée. Seul `pickup_reminder` nomme la supérette et l'heure du
  créneau : assumé (c'est l'information du client sur sa propre commande, sans donnée tierce).
- Le refus de permission push ne bloque aucun parcours (§1) et n'est jamais re-demandé de
  force (§7.6).

## 7. QR et liens — stratégie unifiée

### 7.1 Domaine canonique et `FRONTEND_URL` (#543)

Toute la stratégie repose sur **une seule origine HTTPS publique** — celle de `FRONTEND_URL`
en production (`docs/ops/frontend-url.md`) : les QR imprimés, les emails, les App Links et
les Universal Links pointent tous vers elle, et la PWA y répond déjà pour chaque route.
**Décision humaine en suspens (bloquante avant tout build store)** : le nom de domaine
définitif (lié à l'hypothèse de marque « Kadhia », ADR-0007/MOBILE-006). Placeholder dans ce
document : `https://app.example.tn`. Règles déjà actées par #543 : URL absolue `https`
obligatoire, définie pour le backend **et** le worker Messenger, `target_url` vérifié avant
toute impression en série.

### 7.2 Matrice des URL publiques stables

| URL (origine = `FRONTEND_URL`) | App cible | Écran | Fallback (app absente) | Erreur |
|---|---|---|---|---|
| `/stores/by-qr/{qrToken}` | Client | E06 (transitoire) → E09 Catalogue | PWA client — même route, déjà servie | `STORE_NOT_FOUND` → cas #1/#2 de #563 |
| `/stores/{shopId}` | Client | E08 Fiche supérette | PWA client | 404 propre |
| `/orders/{orderId}` | Client | E14 Suivi de commande | PWA client | 404 sans fuite (ownership serveur) |
| `/reset-password?token=...` | Client (et marchand : parcours web assumé, #564 §1.1) | E03 | PWA — **cible principale** : le lien vient d'un email, le web est le dénominateur commun | token invalide/expiré → codes `AUTH_RESET_TOKEN_*` |
| `/merchant/orders/{orderId}` | Marchand | E05 Détail commande | PWA marchand | 404/403 sans fuite |
| `/merchant/invitation?token=...` | **Web uniquement en V1** (finalisation d'invitation = web, #565 §1.2) | — | PWA marchand | expiration/usage unique |
| `/kadhia/share/{token}` | Client (post-V1, #563 §1.3) | — | PWA client | `KADHIA_NOT_SHAREABLE` |

Règles :

- **Aucun schéma privé** (`kadhia://`) comme canal principal : toute URL est `https` et vit
  aussi en PWA — le fallback est automatique et aucun QR imprimé ne dépend de l'installation
  d'une app. Un schéma privé peut exister en interne (retour d'outils Expo) mais n'est jamais
  publié ni imprimé.
- **Mapping deux apps sans conflit** : préfixe `/merchant/*` → app marchande ; tout le
  reste → app client. Les deux apps ne revendiquent **jamais** un même chemin : les fichiers
  de déclaration (§7.3) attribuent des motifs disjoints, ce qui règle la question « les deux
  apps pourraient reconnaître un lien » par construction.
- **Lien malformé ou inconnu** : ouverture de l'app concernée, résolution API, refus propre —
  cas d'erreur déjà cadrés (#563 cas #1/#2 via E06 ; #564 cas 8) — sans fuite d'information
  (404 générique, jamais « cette commande appartient à quelqu'un d'autre »).
- **Non connecté** : authentification puis **reprise de la destination** (parcours 3.2/3.7
  de #563) ; **rôle/ownership incorrect** : refus propre par les codes existants.

### 7.3 App Links Android et Universal Links iOS

À servir sur le domaine canonique, hébergés par le frontend Next.js (`public/.well-known/`),
`Content-Type: application/json`, sans redirection :

- **`/.well-known/assetlinks.json`** (Android) : deux entrées `android_app` —
  `tn.kadhia.client` et `tn.kadhia.merchant` (identifiants = hypothèse ADR-0007 à valider
  avec les comptes stores, MOBILE-006) — avec les **empreintes SHA-256 de signature par
  environnement** (clé de production gérée par EAS + clés debug/preview si les liens doivent
  fonctionner en staging). `autoVerify=true` sur les intent filters, motifs de chemins
  disjoints (§7.2).
- **`/.well-known/apple-app-site-association`** (iOS) : `appIDs` `TEAMID.tn.kadhia.client`
  et `TEAMID.tn.kadhia.merchant`, blocs `components` par motifs de chemins (`/stores/*`,
  `/orders/*` → client ; `/merchant/orders/*` → marchand), `Associated Domains`
  (`applinks:app.example.tn`) dans les entitlements EAS.
- Côté apps : `expo-linking` + config `intentFilters`/`associatedDomains` dans `app.config`
  (ADR-0007) ; un domaine par environnement si le staging doit vérifier les liens.
- **Dépendance dure** : ces fichiers sont inutiles tant que le domaine (§7.1) n'est pas
  acté — iOS et Android vérifient le domaine à l'installation de l'app.

### 7.4 Routage notification → écran

Le champ `route` du payload (§6) est le chemin canonique de la matrice §7.2 (ex.
`/orders/{orderId}` — déjà la forme des `pushUrl` Web Push existants) : le même mapping
route → écran sert aux liens et aux pushs.

| `type` | App | Écran ouvert |
|---|---|---|
| `order_accepted`, `order_rejected`, `order_ready`, `order_completed`, `pickup_reminder`, `merchant_response_timeout`, `partial_acceptance_timeout` | Client | E14 Suivi de commande |
| `order_partially_accepted`, `partial_acceptance_reminder` | Client | E14, contexte acceptation partielle (accès E11 pour modifier) |
| `merchant_order_submitted`, `merchant_order_cancelled` | Marchand | E05 Détail commande |
| `type` inconnu (app plus ancienne que le backend) | les deux | Liste des notifications (E16 client / E11 marchand) — jamais d'erreur |

Tap **à froid** : initialisation (auth #616, version minimale #618) puis navigation vers la
route ; **en arrière-plan** : navigation directe ; **au premier plan** : pas de bannière
système — rafraîchissement de l'écran courant + badge cloche (détail UX §5 de l'issue, à
affiner à l'implémentation mobile ; le contrat backend n'en dépend pas).

### 7.5 QR magasin (issue §7)

- **Contenu** : l'URL `https` canonique existante `{FRONTEND_URL}/stores/by-qr/{qrToken}` —
  jamais un schéma privé, jamais un id interne (le token est opaque, règle sécurité existante).
- **Génération par environnement** : déjà couverte par #543 — le QR imprimé est généré avec
  le `FRONTEND_URL` de production ; les QR de dev/staging pointent vers leurs origines et ne
  doivent jamais être imprimés en série.
- **Stabilité du QR imprimé** : `qrCodeToken` est stable ; la route `/stores/by-qr/` est
  **gelée** (un QR est physiquement imprimé chez les marchands — toute évolution passe par
  une compatibilité descendante, jamais par un changement de chemin). La régénération admin
  du token (S5-005) existe : elle **invalide les impressions** — à réserver aux compromissions,
  avec réimpression organisée.
- **Scan externe** (appareil photo système) : l'URL ouvre l'app par App/Universal Link, ou la
  PWA à défaut — même destination.
- **Scan interne** (E05 client) : la caméra in-app lit l'URL, en extrait le token
  (uniquement si l'origine est le domaine canonique), résout `GET /api/stores/by-qr/{token}`
  → E06 → E09 ; QR d'une autre origine ou illisible → aide au scan, pas de navigation web
  arbitraire.
- **Erreurs** : token inconnu → `STORE_NOT_FOUND` (cas #1 #563) ; supérette inactive →
  cas #2 ; permission caméra refusée → cas #13 (saisie/lien de secours, réglages système).
- **Analytics minimales** : événement `store_qr_scanned` (source interne/externe), sans
  contenu — aligné sur §7 de #563.

### 7.6 QR de retrait (issue §8) — jamais un lien profond

Le QR de retrait est un **canal fermé app→app** : affiché par l'app client (E15), scanné par
l'app marchande (E07). Ce n'est **pas** une URL publique, il n'apparaît pas dans la matrice
§7.2 et aucun App/Universal Link ne le résout — l'encoder en URL en ferait un lien ouvrable
par n'importe quel téléphone, sans aucun bénéfice.

- **Format** : la charge `qr_payload` retournée par `GET /api/me/orders/{orderId}/pickup-session`
  (token opaque de session UUID v4). Aucune donnée métier dans le QR : ni commande, ni
  montant, ni identité.
- **Durée de vie** : TTL 24 h (`expiresAt`, existant) ; l'app client affiche l'expiration
  (`is_expired`/`is_used` déjà exposés).
- **Vérification serveur** : `POST /api/merchant/pickup-sessions/scan` revalide tout —
  ownership supérette (403 `MERCHANT_CATALOG_FORBIDDEN` si autre supérette — mapper avec le
  404 sur « QR non valide », cas 8 #564), état (`ORDER_NOT_READY`), expiration
  (`PICKUP_SESSION_EXPIRED`), usage unique (`PICKUP_SESSION_ALREADY_USED`). **Aucune
  validation locale** : sans réponse serveur, pas de remise validée (cas 11 #564 — le token
  scanné est conservé pour re-tentative).
- **Rejeu/capture** : modèle existant conservé — usage unique, double validation
  client+marchand, double scan idempotent (200, audit #565 §4.7), fenêtre force-complete
  300 s. Une capture d'écran du QR ne donne que le retrait de **sa propre** commande, déjà
  sécurisé par la double validation.
- **Secours** : code 4 chiffres (`redeem-by-code`, finalisation directe — sémantique tranchée
  par #565) et validation manuelle avec note — E09 marchand. Limite connue : unicité du code
  non garantie (GAP_NON_BLOCKING #565), hors périmètre ici.
- **Réseau indisponible** : côté client, le QR déjà chargé reste affichable (cache écran) ;
  côté marchand, aucun mode hors-ligne — message cas 11 et re-tentative.

## 8. Permissions et UX push (issue §5 — contrat produit, détail à l'implémentation mobile)

- **Moment de la demande** : jamais au premier lancement. Client : pré-écran explicatif après
  la **première soumission de commande** (« Soyez prévenu quand votre Kadhia est prête ») —
  le moment où la valeur est évidente. Marchand : pré-écran à la première arrivée sur le
  dashboard (E03) — recevoir les nouvelles commandes est le cœur du métier.
- **Pré-écran d'abord** : la boîte système n'est déclenchée qu'après un « oui » au pré-écran
  (préserve le droit de redemander, surtout sur iOS où le refus système est quasi définitif).
- **Après refus** : aucun parcours bloqué (§1) ; bandeau discret sur E16/E11 avec lien vers
  les réglages système ; jamais de re-prompt agressif.
- **Préférences in-app** : interrupteur global par app (champ `enabled` du device, décision 3) ;
  pas de granularité par type en V1.
- **Android** : canaux « Commandes » (importance high, son — `merchant_order_submitted`,
  `order_ready`, événements d'action client) et « Suivi » (importance default — le reste) ;
  Android 13+ : permission runtime `POST_NOTIFICATIONS`.
- **iOS** : demande standard (pas de permission provisoire en V1 — l'opt-in explicite est
  préféré pour un canal transactionnel) ; catégories non retenues V1.
- **Badge** : compteur de notifications non lues (aligné sur la cloche in-app) ; remis à zéro
  à la lecture (`read-all` existant).
- **Regroupement** : par commande (thread id = `order_id`) — les événements successifs d'une
  même commande se groupent.
- **Tap froid / arrière-plan / premier plan** : §7.4.

## 9. Matrice de tests (recette du cadrage)

| # | Scénario | Résultat attendu |
|---|---|---|
| 1 | POST device (client, token Expo valide) puis re-POST même token | 201 puis 200 (upsert), une seule ligne |
| 2 | POST device `application=merchant` avec JWT client | 403, aucune ligne |
| 3 | Logout → DELETE device → événement `order_ready` | Aucun push vers ce device ; in-app persistée |
| 4 | Login utilisateur B sur l'appareil de A (même token) | Device réaffecté à B ; A ne reçoit plus rien sur cet appareil |
| 5 | Réponse `DeviceNotRegistered` du provider | `revoked_at` + `provider_rejected`, plus aucun envoi, pas de retry |
| 6 | Panne provider (5xx) pendant un `mark-ready` | Commande passe `ready` (jamais bloquée) ; retries Messenger ; visible dans `failed`/#353 si échec final |
| 7 | Retry Messenger après envoi partiellement réussi | Pas de doublon visible (collapse id = `notification_id`) |
| 8 | Push `order_ready` tapé après retrait de la commande | E14 affiche `completed` (état serveur), aucun contenu du push affiché |
| 9 | Push reçu, utilisateur déconnecté | Login puis reprise vers E14 (parcours 3.7 #563) |
| 10 | Push de type inconnu (app ancienne) | Ouverture de la liste de notifications, pas de crash |
| 11 | Device `locale=ar` | Titre/corps AR (colonnes `title_ar`/`body_ar`), RTL côté app |
| 12 | Payload de chaque événement V1 | Aucun contenu Kadhia/coordonnée/note/token (revue automatisable sur le builder de payload) |
| 13 | Suppression de compte (S7-003) | Plus aucune ligne `mobile_devices` ; plus aucun push |
| 14 | Lien `/orders/{id}` — app installée / absente | App → E14 ; sinon PWA même URL |
| 15 | Lien `/merchant/orders/{id}` sur un téléphone avec les deux apps | App **marchande** uniquement (motifs disjoints) |
| 16 | `assetlinks.json` / AASA sur le domaine | Vérification App Links (adb) et Universal Links (validateur Apple) OK par build signé |
| 17 | Scan externe (appareil photo) d'un QR magasin imprimé | App client → E06 → E09 ; sans app → PWA catalogue |
| 18 | Scan interne d'un QR d'une autre origine que le domaine canonique | Aide au scan, aucune navigation |
| 19 | QR magasin token inconnu / supérette inactive | Cas #1/#2 #563, sans fuite |
| 20 | Scan QR retrait : expiré / déjà utilisé / autre supérette / réseau coupé | 409/403 mappés cas 8 #564 ; réseau → cas 11, token conservé, **jamais** de validation locale |

## 10. Écarts backend et issues

| Écart | Couverture |
|---|---|
| Enregistrement push Web Push only ; types client `null` ; aucun envoi natif ni abstraction ; événements V1 à brancher | **#620** (créée par ce cadrage) — `[MOBILE-PUSH] Modèle mobile_device, endpoints d'enregistrement, abstraction PushSender et événements push V1` (lots A–D : types stables, entité+endpoints, PushSender+Messenger, branchements). Une seule issue : les quatre lots forment une tranche verticale dont chaque partie est inutilisable sans les autres |
| Session mobile (refresh, révocation) — préalable au « login puis reprise de destination » | **#616** (existante — pas de doublon) |
| `request_id` de corrélation | **#617** (existante) |
| Version minimale d'app / maintenance (écrans E18/E17) | **#618** (existante) |
| `FRONTEND_URL` production | **#543** (traitée — `docs/ops/frontend-url.md`) ; reste la **décision humaine du domaine canonique** (§7.1), à acter avec MOBILE-006 (#567) qui porte aussi stores, certificats et credentials |
| `assetlinks.json` + AASA à servir par le frontend | Pas d'issue tant que le domaine n'est pas acté (fichiers non constructibles sans identifiants stores + empreintes) — dépendance enregistrée dans #567 via ce document |
| Notifications in-app incident (préalable à tout push incident) | Hors V1, décision produit à provoquer si le besoin terrain se confirme — pas d'issue créée |

## 11. Hypothèses et limites

- Identifiants d'apps `tn.kadhia.client`/`tn.kadhia.merchant` et marque « Kadhia » :
  hypothèses ADR-0007, à valider avec les comptes stores (MOBILE-006).
- Domaine canonique non acté : `https://app.example.tn` est un placeholder ; **aucun**
  fichier `.well-known`, build store ni impression ne doit être produit avant la décision.
- Les textes push réutilisent les libellés in-app existants : toute évolution éditoriale se
  fait à un seul endroit (`NotificationService`) et profite aux deux canaux.
- La partie UX fine des permissions (§8) est un contrat produit : les détails d'implémentation
  (canaux Android exacts, wording des pré-écrans) se règlent dans le dépôt mobile, sans
  impact sur le contrat backend.
- Ce cadrage n'engage aucune modification de `docs/architecture/api-contract.md` : les routes
  `/api/mobile/devices` y seront documentées à la livraison de #620.
