# Mobile Marchand — Périmètre, parcours et écrans (MOBILE-003, #564)

> Cadrage fonctionnel de l'application **Mobile Marchand V1** (React Native + Expo,
> `apps/merchant` du dépôt `click-and-collect-mobile` — ADR-0007). Aucun code mobile
> dans ce document ni dans ce dépôt. Le mobile **reprend les parcours PWA marchand
> validés** (`apps/frontend/src/app/merchant/`) ; il n'invente pas de produit.
> Contrat API de référence : `docs/architecture/api-contract.md`
> (source de vérité : `/api/docs.json`).

Vocabulaire métier non négociable : **Kadhia** (jamais « panier »), supérette,
marchand, client, **rendez-vous** (créneau de retrait), **retrait**, montants en **TND**.

Ligne produit (Claude/instructions.md) : l'app marchande reste **pragmatique,
actions simples et rapides** — elle embarque les opérations terrain à forte
fréquence (recevoir, décider, préparer, remettre) et laisse au web la
configuration rare et le backoffice.

---

## 1. Périmètre V1

Chaque bloc de l'issue est confronté à ce que la PWA marchand livre réellement.
« Inclus » = repris tel que validé en PWA (adapté aux conventions natives) ;
« reporté (web) » = la fonctionnalité reste sur la PWA/web en V1, avec une ligne
de justification ; « écart PWA » = comportement PWA incomplet que le mobile
corrige sans changer le produit.

### 1.1 Compte et connexion

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Connexion marchand | **Inclus** | PWA `merchant/login` → `POST /api/auth/login` (JWT RS256, 1h, stateless). Token en stockage sécurisé (expo-secure-store). |
| Compte secondaire (organisation MERCHANT-TEAM) | **Inclus** | Aucun écran spécifique : un compte secondaire se connecte comme le principal ; `GET /api/merchant/me` résout la supérette via la membership active (`merchant_organization_id`, `account.status`, `account.is_primary`). |
| Gestion d'équipe (inviter, renvoyer, révoquer) | **Reporté (web)** | PWA `merchant/parametres/equipe` → `GET/POST /api/merchant/stores/{storeId}/accounts`, `/account-invitations`, `/resend-invitation`, `DELETE .../accounts/{accountId}`. Action rare, réservée au compte principal, adossée à un flux e-mail : aucun gain terrain à l'embarquer — reste web V1. |
| Finalisation d'invitation (définir son mot de passe) | **Reporté (web)** | PWA `merchant/invitation` → `POST /api/auth/merchant-invitations/verify` + `/complete`. Le lien e-mail ouvre la page web ; à terme un universal link (#566) pourra l'ouvrir dans l'app — pas nécessaire pour la V1. |
| Première connexion avec mot de passe provisoire | **Inclus** | PWA `merchant/premiere-connexion` → `POST /api/merchant/first-login/change-password`. Tant que `password_change_required = true`, seul cet écran (et `GET /api/merchant/me`) est accessible ; le backend bloque le reste avec `403 MERCHANT_PASSWORD_CHANGE_REQUIRED`. |
| Changement de mot de passe connecté | **Inclus** | PWA `merchant/parametres/compte` → `PATCH /api/merchant/me/password`. |
| Récupération du mot de passe | **Inclus** | Parcours public `POST /api/auth/password-reset/request` / `confirm` (lien e-mail, `?portal=merchant`) ; la confirmation reste web, ouverte depuis l'app (#566). |
| Profil compte marchand | **Inclus** | PWA `merchant/parametres/profil` → `GET/PATCH /api/merchant/me` (prénom, nom, téléphone ; email en lecture seule). |
| Déconnexion et reprise de session | **Inclus** | Déconnexion locale (JWT stateless) ; à l'ouverture, restauration de session si le token est valide. JWT 1h sans refresh : politique de renouvellement à trancher en #565 (commune aux deux apps). |
| Compte inactif / suspendu / accès temporaire expiré | **Inclus** (gestion) | Codes `403 MERCHANT_ACCOUNT_INACTIVE`, `MERCHANT_TEMPORARY_PASSWORD_EXPIRED` au login — voir §4. |
| Multi-boutique | **Hors V1** (comme backend) | Plusieurs boutiques actives dans l'organisation → `409 MERCHANT_MULTIPLE_ACTIVE_STORES` au `GET /api/merchant/me` ; pas de sélecteur (décision MERCHANT-TEAM-003), message explicite — voir §4. |

### 1.2 Dashboard journalier

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Compteurs du jour et « À faire maintenant » | **Inclus** | PWA `merchant/` → `GET /api/merchant/stores/{storeId}/dashboard/today` (submitted, urgentes, accepted, preparing, ready, rendez-vous du jour avec capacité ; `pickup_orders_today` associe chaque commande active ou finalisée à son rendez-vous). |
| Actions prioritaires cliquables | **Inclus** | Cartes → liste commandes filtrée / écran retrait, comme en PWA. |
| Actualisation | **Inclus** | Pull-to-refresh natif + rafraîchissement au retour au premier plan ; indicateur de fraîcheur des données (heure du dernier chargement) — écart PWA corrigé : la PWA n'a qu'un bouton « Actualiser ». |
| Bandeau onboarding | **Inclus (lecture)** | `GET /api/merchant/onboarding` : progression affichée en bannière ; les étapes de configuration (catalogue complet, thème…) renvoient vers le web. Pas d'écran onboarding dédié en V1 mobile (guide de configuration = usage ponctuel de bureau). |

### 1.3 Traitement des commandes

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Liste des commandes actives (filtres) | **Inclus** | PWA `merchant/commandes` → `GET /api/merchant/stores/{storeId}/orders?status=…` (tri serveur `sort=priority` avant pagination ; résumé `customer_name` actif seulement ; onglet actif : `submitted,accepted,partially_accepted,preparing,ready,pickup_pending` ; filtres « À accepter / À préparer / Prêtes »). |
| Détail de commande | **Inclus** | `GET /api/merchant/stores/{storeId}/orders/{orderId}` (lignes, note client, coordonnées client sur commandes actives uniquement). |
| Accepter | **Inclus** | `POST .../orders/{orderId}/accept` (statut `submitted` uniquement). |
| Refuser avec raison | **Inclus** | `POST .../orders/{orderId}/reject` (`{reason}`) — libère la capacité du rendez-vous. |
| Acceptation partielle ligne par ligne | **Inclus** | `POST .../orders/{orderId}/partially-accept` (`{rejected_merchant_product_ids, notes}`) — au moins une ligne refusée, jamais toutes ; la Kadhia repasse `draft` côté client. |
| Passer en préparation + préparation ligne par ligne | **Inclus** | `POST .../start-preparation` puis `PATCH .../lines/{merchantProductId}/preparation` (`{prepared}`). |
| Marquer prête (contrôle strict) | **Inclus** | `POST .../mark-ready` — refusé tant que toutes les lignes ne sont pas `prepared` ; crée la `PickupSession` (TTL 24 h). |
| Historique de statuts avec auteur | **Inclus** | `GET .../orders/{orderId}/status-history` (`actor_type`/`actor_name`, MERCHANT-TEAM-006 — précieux en organisation multi-comptes). |
| Contact WhatsApp client | **Reporté post-V1** | PWA (commande `accepted`) → `POST .../orders/{orderId}/whatsapp-contact` : conditionnel produit (#378) et hors contrat documenté — audit #565. |
| Commande déjà traitée ailleurs (PWA / autre compte) | **Inclus** (gestion) | 409 `ORDER_INVALID_STATUS` / `ORDER_NOT_SUBMITTED` etc. → re-GET et affichage de l'état serveur — voir §4. |

### 1.4 Retrait sécurisé

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Scan du QR de retrait | **Inclus — apport mobile majeur** | PWA `merchant/retrait` (onglet QR) exige de **coller le token à la main** ; le mobile scanne avec la caméra (expo-camera) et envoie `POST /api/merchant/pickup-sessions/scan` (`{token}`). Le token est opaque, jamais affiché ni saisi. Le secours est le code à quatre chiffres. |
| Code à 4 chiffres | **Inclus** | Onglet PWA « Code 4 chiffres » → `POST /api/merchant/stores/{storeId}/orders/redeem-by-code` (`{pickupCode}` chaîne de quatre chiffres, zéros initiaux conservés) — contrat documenté, finalisation directe `completed`, quota et erreurs décrits ci-dessous. |
| Double validation (confirmation marchand) | **Inclus** | `PATCH /api/merchant/pickup-sessions/{id}/confirm` ; la commande passe `completed` après la confirmation client. |
| Force completion | **Inclus** | `PATCH /api/merchant/pickup-sessions/{id}/force-complete` (`{note}` obligatoire) — possible ≥ 5 min après confirmation marchande sans confirmation client. |
| Validation manuelle avec justification | **Inclus** | Onglet PWA « Manuel » → `POST .../orders/{orderId}/validate-manually` (`{note}` ≥ 5 caractères) — endpoint livré, **absent du contrat documenté** (renvoi #565). |
| États QR expiré / déjà utilisé / autre supérette | **Inclus** (gestion) | `PICKUP_SESSION_EXPIRED`, `PICKUP_SESSION_ALREADY_USED`, 403/404 ownership — voir §4. |

### 1.5 Historique et export

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Historique complet (filtres, recherche, pagination) | **Inclus** | PWA onglet Historique → `GET /api/merchant/stores/{storeId}/orders/history?status=&date_from=&date_to=&query=&page=&limit=` (libellés `status_label_fr`/`status_label_ar` fournis par l'API). |
| Export CSV | **Reporté (web)** | `GET .../orders/export.csv` produit un fichier destiné à Excel/poste de travail ; aucun usage terrain sur téléphone — reste web V1, l'app affiche un renvoi. |
| Statistiques marchand (#380) | **Reporté (web)** | PWA `merchant/statistiques` → `GET .../statistics`. Analyse de bureau, hors « actions terrain à forte fréquence » ; à réévaluer post-V1. |

### 1.6 Catalogue rapide

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Recherche d'un produit dans le catalogue | **Inclus** | `GET /api/merchant/stores/{storeId}/catalog` (+ recherche). |
| Disponibilité / indisponibilité unitaire | **Inclus** | `PATCH /api/merchant/catalog/{merchantProductId}` (`is_available`). Geste terrain n°1 (rupture en rayon). |
| Disponibilité en masse | **Inclus** | `PATCH /api/merchant/stores/{storeId}/products/bulk-availability`. |
| Modification du prix | **Inclus** | `PATCH /api/merchant/catalog/{merchantProductId}` (`price_tnd`). Prix snapshotés côté Kadhia : aucun impact sur les commandes en cours. |
| Photo produit local (#583) | **Inclus — atout mobile** | PWA « bloc photo produit local » → `POST /api/merchant/stores/{storeId}/local-products/{localProductId}/photo`. La caméra native rend la contribution photo immédiate en rayon. Endpoint **absent du contrat documenté** (renvoi #565). |
| Recherche référentiel par code-barres | **Inclus** | `GET /api/merchant/stores/{storeId}/product-references?barcode=` — le scan caméra du code-barres (#365) devient natif ; saisie manuelle en secours. |
| Ajout d'un produit du référentiel au catalogue | **Inclus (léger)** | `POST /api/merchant/stores/{storeId}/catalog` (prix + dispo) après recherche/scan — complète le geste code-barres sans dupliquer la création avancée. |
| Création produit local complète, import CSV, import photo IA, groupes/formats, promotions, catégories marchand, proposition de produit | **Reporté (web)** | PWA catalogue avancé (`import-csv`, `photo-import/preview|commit`, `product-groups`, `local-products/bulk`, promotions #384…) : flux de configuration longs, adaptés au clavier/grand écran — restent web V1. |
| Historique de prix | **Reporté (web)** | PWA → `GET /api/merchant/products/{merchantProductId}/price-history` (hors contrat documenté, renvoi #565) — consultation d'analyse, pas un geste terrain. |

### 1.7 Créneaux, supérette et paramètres

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Consultation des créneaux (rendez-vous) | **Inclus** | `GET /api/merchant/stores/{storeId}/pickup-slots` — vue du jour et à venir avec capacité/réservations. |
| Désactiver un créneau ponctuel | **Inclus** | `DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}` (désactivation logique) — geste opérationnel simple (imprévu du jour). |
| Fermeture exceptionnelle simple | **Inclus** | `GET/POST/DELETE /api/merchant/stores/{storeId}/exceptional-closures` — « je ferme demain » doit se faire du téléphone. |
| Règles de créneaux récurrents + génération | **Reporté (web)** | `GET/POST/PATCH/DELETE .../pickup-slot-rules` + `/generate` : configuration structurante, rare, mieux servie au grand écran. |
| Horaires d'ouverture | **Reporté (web)** (lecture seule mobile) | `GET/PATCH .../opening-hours` : l'app affiche les horaires (lecture) ; l'édition hebdomadaire reste web. |
| Délai minimal avant retrait (ORDER-LEAD) | **Inclus** | PWA `merchant/parametres/delai-retrait` → `GET/PATCH /api/merchant/stores/{storeId}/ordering-policy` (`minimum_pickup_lead_time_minutes`, 0–10080). Levier opérationnel simple (monter le délai en cas de surcharge) : sa place est dans la poche du marchand. |
| Notifications (centre in-app + push) | **Inclus** | `GET /api/merchant/notifications?page=&unread=`, `PATCH .../{id}/read`, `.../read-all`. Push natif FCM/APNs (#566) ; **écart API** : `POST /api/merchant/push-subscriptions` est au format Web Push (`endpoint`, `p256dhKey`, `authKey`) — enregistrement de tokens natifs à cadrer en #565/#566. Le centre in-app reste la source de vérité. |
| QR magasin | **Inclus (affichage + partage)** | PWA `merchant/qr-code` → `GET /api/merchant/stores/{storeId}/qr-code` (+ `.png` / `.pdf`, #355). L'app affiche le QR et partage le PNG/PDF via la feuille de partage native ; l'**impression** reste un geste web/poste de travail. |
| Thème / apparence | **Reporté (web)** | PWA `merchant/apparence` → `GET/PUT .../theme` : édition visuelle fine, fréquence très faible — reste web V1. |
| Profil supérette | **Reporté (web)** | PWA → `GET/PATCH /api/merchant/stores/{storeId}/profile` (hors contrat documenté, renvoi #565) — configuration rare. |
| Abonnement et documents mensuels | **Reporté (web)** (lecture possible post-V1) | `GET /api/merchant/subscription`, `GET /api/merchant/billing-documents` : consultation administrative. Les **effets** d'une suspension (`STORE_SUSPENDED_FOR_SUBSCRIPTION`, `MERCHANT_ACCOUNT_INACTIVE`) sont gérés en V1 (§4). |
| Langue FR/AR | **Inclus** | PWA `merchant/parametres/langue` ; clés i18n partagées (`@kadhia/i18n`), RTL complet (`I18nManager`). |
| Onboarding guidé complet | **Reporté (web)** | Bannière de progression seulement (§1.2) ; `PATCH /api/merchant/onboarding/complete` reste déclenché depuis le web. |

### 1.8 Hors périmètre V1 (rappel issue)

Backoffice administrateur, CRM complet, statistiques avancées, éditeur avancé de
thème, gestion complète de facturation, import IA catalogue, création avancée de
packs, campagnes publicitaires, fonctionnement totalement hors ligne,
duplication intégrale de la PWA marchand sans preuve d'usage mobile.

---

## 2. Navigation

Priorité absolue aux gestes du quotidien : **commandes entrantes** et **retrait**.
Tab bar de 4 onglets + écrans transversaux. En AR, ordre des onglets et gestes
de retour inversés (RTL).

```text
App
├── Pile Auth (hors onglets)
│   ├── Connexion                          (PWA /merchant/login)
│   └── Première connexion — mot de passe provisoire   (PWA /merchant/premiere-connexion)
│
├── Tab bar (4 onglets)
│   ├── Onglet Aujourd'hui
│   │   └── Dashboard du jour              (PWA /merchant)
│   ├── Onglet Commandes  (badge : commandes à traiter)
│   │   ├── Commandes actives + Historique (PWA /merchant/commandes)
│   │   ├── Détail commande                (PWA /merchant/commandes/[orderId])
│   │   └── Acceptation partielle          (modale PWA)
│   ├── Onglet Retrait
│   │   ├── Scan QR (caméra ; secours code court)   (PWA /merchant/retrait, onglet QR)
│   │   ├── Session de retrait (double validation)
│   │   └── Code 4 chiffres / validation manuelle
│   └── Onglet Boutique
│       ├── Catalogue rapide               (PWA /merchant/catalogue, sous-ensemble)
│       ├── Photo produit local (caméra)   (PWA bloc photo #583)
│       ├── Rendez-vous du jour + fermetures    (PWA /merchant/creneaux, sous-ensemble)
│       ├── QR magasin                     (PWA /merchant/qr-code)
│       └── Paramètres                     (PWA /merchant/parametres)
│           ├── Profil · Compte (mot de passe) · Langue FR/AR
│           ├── Délai minimal avant retrait     (PWA /merchant/parametres/delai-retrait)
│           └── Renvois web : équipe, apparence, abonnement, export CSV, statistiques
│
├── Écrans transversaux
│   ├── Notifications (cloche + badge non-lus)  (PWA /merchant/notifications)
│   └── Mise à jour obligatoire (version API minimale, ADR-0007)
```

Règles :

- toute l'app exige une session marchande valide (aucun écran public) ;
- un push ou une notification in-app ouvre directement l'écran cible
  (détail commande, retrait), avec pile de retour cohérente ;
- tant que `password_change_required = true`, la navigation est verrouillée sur
  l'écran Première connexion (le backend refuse tout le reste en 403) ;
- badge sur l'onglet Commandes = commandes `submitted` + `partially_accepted`
  re-soumises à traiter ; badge cloche = notifications non lues ;
- indicateur de connectivité global (bandeau hors-ligne) + horodatage de
  fraîcheur sur les listes (§6).

---

## 3. Parcours nominaux

### 3.0 Parcours cœur — de la notification à la remise

1. Push « Nouvelle commande » (ou centre in-app) → ouverture directe du détail (`GET /api/merchant/stores/{storeId}/orders/{orderId}`).
2. Le marchand lit les lignes et la note client, puis **accepte** (`POST .../accept`) — ou accepte partiellement / refuse (3.3).
3. Au moment de préparer : `POST .../start-preparation` → la commande passe `preparing`.
4. Il coche chaque ligne préparée (`PATCH .../lines/{merchantProductId}/preparation`).
5. Toutes lignes cochées → « Marquer prête » (`POST .../mark-ready`) ; la `PickupSession` est créée, le client est notifié.
6. Le client se présente : le marchand ouvre l'onglet Retrait et **scanne le QR** du client (`POST /api/merchant/pickup-sessions/scan`). En secours, le **code à 4 chiffres** (`POST .../orders/redeem-by-code`) finalise directement la remise après succès ; les étapes 7–9 concernent uniquement le QR.
7. L'écran session affiche client, lignes et total TND ; le marchand remet la Kadhia et confirme (`PATCH /api/merchant/pickup-sessions/{id}/confirm`).
8. Le client confirme sur son téléphone (double validation) → la commande passe `completed` ; notification de finalisation des deux côtés.
9. Si le client ne confirme pas (parti, téléphone déchargé) : après 5 minutes, force completion avec note (`PATCH .../force-complete`) — parcours 3.6.

### 3.1 Première connexion avec mot de passe provisoire

1. Login (`POST /api/auth/login`) avec le mot de passe provisoire → succès.
2. `GET /api/merchant/me` → `password_change_required: true` : l'app verrouille sur l'écran Première connexion.
3. Le marchand saisit mot de passe provisoire + nouveau mot de passe + confirmation → `POST /api/merchant/first-login/change-password` (204).
4. Re-lecture `GET /api/merchant/me` → accès au dashboard. Si la fenêtre provisoire est expirée : `MERCHANT_TEMPORARY_PASSWORD_EXPIRED` (§4), contact support/admin.

### 3.2 Login d'un compte secondaire (membership)

1. Le compte secondaire (invité via l'écran Équipe web, invitation finalisée sur le web) se connecte normalement.
2. `GET /api/merchant/me` résout la supérette via l'organisation de sa membership active (`account.is_primary: false`).
3. Il dispose des mêmes opérations terrain que le principal ; l'écran Équipe n'existe pas dans l'app (web only) ; l'historique de statuts porte son nom (`actor_name`).

### 3.3 Acceptation partielle avec ajustement des lignes

1. Détail d'une commande `submitted` → « Accepter partiellement ».
2. L'écran de sélection liste les lignes ; le marchand coche les lignes **refusées** (au moins une, jamais toutes — sinon l'app propose « Refuser la commande ») et saisit une note explicative.
3. `POST .../partially-accept` (`{rejected_merchant_product_ids, notes}`) → statut `partially_accepted`, la Kadhia client repasse `draft`.
4. Le client ajuste et re-soumet : la commande existante revient `submitted` dans la liste « À accepter » ; le marchand la retraite (rappel automatique à rendez-vous −4h, expiration automatique à −2h — 3.7).

### 3.4 Révocation en cours de session

1. Le compte principal révoque une membership depuis le web pendant que le compte secondaire est connecté sur mobile (JWT encore valide).
2. À la **requête suivante** du révoqué, le backend répond `403 MERCHANT_CATALOG_FORBIDDEN` (état relu à chaque requête, aucun cache).
3. L'app affiche le message d'accès retiré (§4 cas 4), purge le token et revient à Connexion. Aucune donnée boutique ne reste accessible.

### 3.5 Rappels et notifications opérationnelles

1. Rendez-vous −4h : le marchand reçoit le rappel « acceptation partielle en attente de re-soumission client » (automatisation Messenger, Sprint 3b).
2. Notifications « nouvelle commande », « commande annulée par le client », « retrait finalisé » : chacune ouvre l'écran concerné.
3. Le centre in-app (`GET /api/merchant/notifications`) reste la source de vérité : un push perdu n'a aucun impact métier ; le badge se recalcule à chaque ouverture.

### 3.6 Force completion

1. Session de retrait scannée, marchand confirmé, client non confirmé (parti sans valider).
2. Après 5 minutes, l'action « Forcer la finalisation » devient disponible ; note obligatoire.
3. `PATCH /api/merchant/pickup-sessions/{id}/force-complete` (`{note}`) → commande `completed`, `force_completed_by_merchant: true`, traçabilité conservée.

### 3.7 Commande expirée / annulée automatiquement

1. Une commande `submitted` non traitée avant rendez-vous −2h (ou `partially_accepted` non re-soumise avant −2h) est annulée automatiquement (Messenger).
2. Le marchand qui ouvre son détail depuis une vieille notification voit l'état final `cancelled` avec l'historique de statuts (`actor_type: system`) — aucun CTA, aucune erreur : un état final est un état normal.
3. Idem pour une commande annulée par le client (`submitted` uniquement) : elle disparaît de « À accepter » au rafraîchissement et une notification l'explique.

### 3.8 Multi-boutique refusé

1. `GET /api/merchant/me` → `409 MERCHANT_MULTIPLE_ACTIVE_STORES` (plusieurs boutiques actives dans l'organisation).
2. L'app affiche un écran d'erreur dédié : « Ce compte gère plusieurs supérettes actives ; ce cas n'est pas encore pris en charge sur mobile. » — pas de choix silencieux (décision backend MERCHANT-TEAM-003, sélecteur hors V1).
3. Seules issues : déconnexion, ou correction côté admin. Aucune donnée boutique n'est affichée.

### 3.9 Reprise d'une préparation commencée sur la PWA

1. Une commande `preparing` avec des lignes déjà cochées sur la PWA s'ouvre sur mobile : le re-GET du détail reflète l'état serveur des lignes (`prepared`).
2. Le marchand poursuit le cochage sur mobile et marque prête — aucun conflit : chaque coche est un PATCH unitaire idempotent sur l'état serveur.

---

## 4. Cas d'erreur obligatoires

Convention transverse : messages définis en FR avec leur **clé i18n**
(traduction AR obligatoire via `@kadhia/i18n`, jamais de texte en dur). Les
erreurs 5xx affichent le **`request_id`** quand la réponse ou ses en-têtes le
fournissent. Toute action sensible est verrouillée pendant l'envoi (anti double
tap) ; un 409 déclenche systématiquement un re-GET.

| # | Cas | Détection | Message FR (clé i18n) | Reprise proposée |
|---|---|---|---|---|
| 1 | Token expiré ou révoqué | 401 sur toute route `/api/merchant/*` | « Votre session a expiré. Reconnectez-vous. » (`errors.auth.sessionExpired`) | Purge du token, Connexion, retour au contexte après login. |
| 2 | Mauvais rôle connecté | 403 sur `/api/merchant/*` (compte client/admin dans l'app Marchand) | « Ce compte n'est pas un compte marchand. » (`errors.auth.wrongRole`) | Déconnexion proposée ; renvoi vers l'app client. |
| 3 | Compte suspendu / inactif | `403 MERCHANT_ACCOUNT_INACTIVE` (login ou en session) | « Ce compte marchand est désactivé. Contactez le support. » (`errors.merchant.accountInactive`) | Déconnexion ; contact support (lien) ; aucune donnée boutique affichée. |
| 4 | Membership révoquée en cours de session | `403 MERCHANT_CATALOG_FORBIDDEN` sur la requête suivante | « Votre accès à cette supérette a été retiré. » (`errors.merchant.accessRevoked`) | Purge du token, retour Connexion (parcours 3.4). |
| 5 | Changement de mot de passe requis | `403 MERCHANT_PASSWORD_CHANGE_REQUIRED` sur une route métier | « Remplacez d'abord votre mot de passe provisoire. » (`errors.merchant.passwordChangeRequired`) | Navigation forcée vers Première connexion (3.1). |
| 6 | Mot de passe provisoire expiré | `MERCHANT_TEMPORARY_PASSWORD_EXPIRED` (login ou first-login) | « Votre accès provisoire a expiré. Demandez un nouveau mot de passe. » (`errors.merchant.temporaryPasswordExpired`) | Contact admin/support ; l'admin régénère (`temporary-password`). |
| 7 | Multi-boutique | `409 MERCHANT_MULTIPLE_ACTIVE_STORES` sur `GET /api/merchant/me` | « Ce compte gère plusieurs supérettes actives — non pris en charge sur mobile pour l'instant. » (`errors.merchant.multipleStores`) | Déconnexion ; résolution côté admin (parcours 3.8). |
| 8 | QR de retrait invalide, expiré ou déjà utilisé | Scan → 404 (token inconnu ou d'une autre supérette — aucune fuite), `PICKUP_SESSION_EXPIRED`, `PICKUP_SESSION_ALREADY_USED`, `ORDER_NOT_READY` | « Ce QR de retrait n'est pas valide ou a déjà servi. » (`errors.pickup.invalidQr`) | Re-scanner ; code 4 chiffres ; en dernier recours validation manuelle avec note. |
| 9 | Session de retrait expirée (TTL 24 h avant scan) | Scan → `PICKUP_SESSION_EXPIRED` | « La session de retrait a expiré. » (`errors.pickup.sessionExpired`) | Validation manuelle avec note (auditée) ; pas de réouverture admin dans le MVP. |
| 10 | Code 4 chiffres erroné | `redeem-by-code` → 404 `PICKUP_CODE_NOT_FOUND` (inconnu, ambigu, déjà utilisé ou commande non éligible) ; 429 `RATE_LIMITED` avec `Retry-After` | « Code incorrect ou commande non éligible. » (`errors.pickup.wrongCode`) | Nouvelle saisie après le délai `Retry-After` si 429 ; vérifier avec le client ; bascule QR ou manuel. |
| 11 | Réseau absent au moment du scan | Échec/timeout sans statut HTTP | « Connexion requise pour valider un retrait. » (`errors.network.pickupOffline`) | Le token scanné est conservé, bouton « Réessayer » à la reconnexion ; **jamais** de remise déclarée réussie hors-ligne. |
| 12 | Commande déjà traitée par un autre compte / la PWA | Action → 409 (`ORDER_NOT_SUBMITTED`, `ORDER_NOT_PREPARING`, `ORDER_NOT_READY`, `ORDER_INVALID_STATUS`, `ORDER_ALREADY_COMPLETED`) | « Cette commande a déjà été traitée par {actor_name / un autre appareil}. » (`errors.order.conflict`) | Re-GET automatique, affichage de l'état serveur + historique de statuts avec auteur ; jamais d'écrasement. |
| 13 | Lignes non toutes préparées | `mark-ready` → 422 | « Cochez toutes les lignes avant de marquer la commande prête. » (`errors.order.linesNotPrepared`) | Focus sur la première ligne non cochée ; re-GET si écart (ligne cochée ailleurs). |
| 14 | Validation manuelle sans note | Contrôle local (note < 5 caractères) puis 422 serveur | « Une note d'au moins 5 caractères est obligatoire. » (`errors.pickup.manualNoteRequired`) | Le champ note est requis avant envoi. |
| 15 | Permission caméra refusée | API permissions OS (refus ou refus définitif) | « Sans caméra, utilisez le code à 4 chiffres. » (`errors.permissions.camera`) | Saisie du code 4 chiffres ; lien réglages système (différence Android/iOS). |
| 16 | Permission notifications refusée | API permissions OS à l'opt-in | « Vous ne serez pas alerté des nouvelles commandes. Le centre de notifications reste disponible. » (`errors.permissions.notifications`) | L'app fonctionne (in-app + rafraîchissement) ; relance de l'opt-in au premier passage `submitted` manqué ; lien réglages. |
| 17 | Supérette suspendue (abonnement) | `STORE_SUSPENDED_FOR_SUBSCRIPTION` (soumissions client bloquées) ; lifecycle `suspended` | « Supérette suspendue : les nouvelles Kadhia clients sont bloquées. Régularisez votre abonnement (web). » (`errors.store.suspendedSubscription`) | Bandeau persistant ; le traitement des commandes déjà soumises reste possible ; renvoi web abonnement. |
| 18 | Erreur serveur avec `request_id` | 5xx | « Une erreur est survenue. Réessayez. Réf. : {request_id} » (`errors.server.generic`) | Bouton « Réessayer » ; `request_id` copiable pour le support. |
| 19 | Push dupliqué ou en retard | Tap sur un push d'une commande déjà finalisée | Aucun message d'erreur | Ouverture du détail dans son état final (parcours 3.7) ; les états finaux sont des états normaux. |

---

## 5. Inventaire des écrans

17 fiches. Rappels transverses (non répétés) : tous les libellés passent par
les clés i18n FR/AR avec RTL complet ; montants en TND (3 décimales) ;
dates/heures en `Africa/Tunis` ; états d'erreur du §4 ; analytics du §7
(jamais de PII client ni de contenu de Kadhia) ; toutes les routes exigent
`ROLE_MERCHANT` + accès organisation (`MerchantShopAccessChecker`) ; toute
action serveur verrouille son bouton pendant l'envoi.

### E01 — Connexion

- **Objectif** : ouvrir une session marchande.
- **Préconditions** : aucune.
- **Données affichées** : email, mot de passe, lien « Mot de passe oublié » (ouvre le parcours web `?portal=merchant`).
- **Endpoints** : `POST /api/auth/login` puis `GET /api/merchant/me` (contexte boutique).
- **Actions** : se connecter ; mot de passe oublié (web).
- **Validations** : email au format valide, mot de passe non vide ; verrou pendant l'envoi.
- **États** : loading ; error : 401 `AUTH_INVALID_CREDENTIALS`, `MERCHANT_TEMPORARY_PASSWORD_EXPIRED` (cas 6), `MERCHANT_ACCOUNT_INACTIVE` (cas 3), 429, réseau ; success → E03 (ou E02 si `password_change_required`, écran multi-boutique si cas 7).
- **Navigation** : entrante — lancement d'app déconnecté, 401 global ; sortante — E02, E03.
- **FR/AR/RTL** : formulaire miroir en AR ; saisie email LTR.
- **Hors-ligne** : formulaire désactivé avec bandeau ; aucune tentative locale.
- **Analytics** : `merchant_login_success`, `merchant_login_failure` (motif — sans email).
- **Android/iOS** : autofill/gestionnaires de mots de passe natifs.

### E02 — Première connexion (mot de passe provisoire)

- **Objectif** : forcer le remplacement du mot de passe provisoire avant tout accès métier.
- **Préconditions** : connecté, `password_change_required: true` (navigation verrouillée).
- **Données affichées** : mot de passe provisoire actuel, nouveau mot de passe, confirmation.
- **Endpoints** : `POST /api/merchant/first-login/change-password` ; re-lecture `GET /api/merchant/me`.
- **Actions** : valider ; se déconnecter.
- **Validations** : nouveau mot de passe ≥ 8 caractères ; confirmation identique (contrôle local + serveur `MERCHANT_INVITATION_PASSWORD_CONFIRMATION_MISMATCH`-équivalent 422).
- **États** : loading ; error : provisoire erroné (422), `MERCHANT_TEMPORARY_PASSWORD_EXPIRED` (cas 6), réseau ; success → E03.
- **Navigation** : entrante — E01, 403 `MERCHANT_PASSWORD_CHANGE_REQUIRED` global ; sortante — E03, E01 (déconnexion).
- **FR/AR/RTL** : standard.
- **Hors-ligne** : désactivé avec bandeau.
- **Analytics** : `merchant_first_login_completed`.
- **Android/iOS** : suggestion de mot de passe fort iOS ; autofill Android.

### E03 — Dashboard du jour

- **Objectif** : vue opérationnelle du jour et accès direct aux actions prioritaires.
- **Préconditions** : session valide, contexte boutique résolu.
- **Données affichées** : cartes « À faire maintenant » (urgentes à accepter, en attente, à préparer, prêtes à remettre), compteurs par statut, rendez-vous du jour (`booked_count`/`capacity`), bannière onboarding si incomplet (renvoi web), horodatage de fraîcheur, bandeau suspension abonnement (cas 17) le cas échéant.
- **Endpoints** : `GET /api/merchant/stores/{storeId}/dashboard/today` ; `GET /api/merchant/onboarding` (best-effort).
- **Actions** : ouvrir la liste filtrée (E04) ou le retrait (E07) ; pull-to-refresh.
- **Validations** : aucune.
- **États** : loading (skeleton) ; empty (« Aucune action urgente ») ; error + retry (cache affiché si présent) ; stale (bandeau fraîcheur) ; success.
- **Navigation** : entrante — tab bar, lancement d'app connecté ; sortante — E04, E07, cloche → E11.
- **Push/deep link** : écran par défaut à l'ouverture d'un push sans cible précise.
- **FR/AR/RTL** : cartes et compteurs miroir en AR.
- **Hors-ligne** : derniers compteurs en cache lecture seule, bandeau « non actualisé ».
- **Analytics** : `merchant_dashboard_viewed` (compteurs agrégés).
- **Android/iOS** : néant.

### E04 — Commandes actives

- **Objectif** : lister et filtrer les commandes à traiter.
- **Préconditions** : session valide.
- **Données affichées** : cartes commande (code court, badge statut, rendez-vous, total TND, nombre de lignes) ; filtres « Toutes / À accepter / À préparer / Prêtes » (mêmes regroupements que la PWA : `submitted,partially_accepted` / `accepted,preparing` / `ready,pickup_pending`) ; onglet Historique (E10).
- **Endpoints** : `GET /api/merchant/stores/{storeId}/orders?status=&page=&limit=` (pas de coordonnées client en liste — règle contrat).
- **Actions** : ouvrir un détail ; filtrer ; pull-to-refresh ; pagination.
- **Validations** : aucune.
- **États** : loading (skeleton) ; empty par filtre (« Aucune commande à accepter ») ; error + retry ; stale ; success. Réponses en vol obsolètes ignorées au changement de filtre (équivalent mobile du pattern `requestSeq` PWA).
- **Navigation** : entrante — tab bar, E03 ; sortante — E05, E10 (onglet).
- **Push/deep link** : push « nouvelle commande » → E05 directement.
- **FR/AR/RTL** : badges statut FR/AR (mapping i18n local en liste).
- **Hors-ligne** : dernière liste en cache lecture seule (§6).
- **Analytics** : `merchant_orders_list_viewed` (filtre, nombre de commandes).
- **Android/iOS** : néant.

### E05 — Détail commande

- **Objectif** : décider (accepter / refuser / partiel), préparer ligne par ligne, marquer prête.
- **Préconditions** : commande appartenant à la supérette du contexte.
- **Données affichées** : code commande, badge statut, rendez-vous, lignes (produit, quantité, prix snapshoté, total ligne), total TND, note client, note marchand (`rejection_reason`), coordonnées client (commandes actives uniquement — masquées sur `completed`/`cancelled`/`rejected`), cases « préparée » par ligne quand `preparing`, historique de statuts avec auteur (`actor_type`/`actor_name`).
- **Endpoints** : `GET /api/merchant/stores/{storeId}/orders/{orderId}` ; `POST .../accept` ; `POST .../reject` (`{reason}`) ; `POST .../start-preparation` ; `PATCH .../lines/{merchantProductId}/preparation` (`{prepared}`) ; `POST .../mark-ready` ; `GET .../status-history`.
- **Actions** : selon statut — `submitted` : Accepter / Accepter partiellement (E06) / Refuser (raison obligatoire, dialogue) ; `accepted` : Commencer la préparation ; `preparing` : cocher les lignes puis Marquer prête ; `ready`/`pickup_pending` : renvoi vers Retrait ; états finaux : lecture seule.
- **Validations** : raison de refus non vide ; « Marquer prête » désactivé tant que toutes les lignes ne sont pas cochées (message cas 13) ; chaque action revalidée serveur (statut requis).
- **États** : loading ; error (404 → retour liste ; 409 → cas 12 avec re-GET) ; success ; états finaux sans CTA.
- **Navigation** : entrante — E04, E11, push ; sortante — E06, E07 (commande `ready`), E04.
- **Push/deep link** : cible directe des push « nouvelle commande » / « commande annulée ».
- **FR/AR/RTL** : lignes et totaux miroir ; libellés statut i18n.
- **Hors-ligne** : lecture seule du dernier état ; toutes les actions désactivées avec message (§6).
- **Analytics** : `merchant_order_viewed` (statut), `merchant_order_accepted`, `merchant_order_rejected`, `merchant_order_preparation_started`, `merchant_order_marked_ready` (sans contenu de Kadhia).
- **Android/iOS** : dialogues de confirmation natifs.

### E06 — Acceptation partielle

- **Objectif** : refuser certaines lignes avec justification, sans refuser toute la commande.
- **Préconditions** : commande `submitted`.
- **Données affichées** : lignes cochables (refusées), compteur lignes refusées/acceptées, champ note.
- **Endpoints** : `POST /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept` (`{rejected_merchant_product_ids, notes}`).
- **Actions** : cocher/décocher les lignes refusées ; valider ; annuler.
- **Validations** : au moins une ligne refusée ; toutes refusées → bascule proposée vers Refuser (règle backend) ; note recommandée (transmise au client).
- **États** : loading ; error (409 cas 12 ; 422) ; success → retour E05 (statut `partially_accepted`, bandeau « en attente de re-soumission client »).
- **Navigation** : entrante — E05 ; sortante — E05.
- **FR/AR/RTL** : cases et compteurs miroir.
- **Hors-ligne** : inaccessible (action serveur obligatoire).
- **Analytics** : `merchant_order_partially_accepted` (nombre de lignes refusées — jamais leur contenu).
- **Android/iOS** : néant.

### E07 — Retrait : scan QR

- **Objectif** : identifier la session de retrait en scannant le QR du client (apport natif — la PWA exige de coller le token).
- **Préconditions** : session valide ; permission caméra demandée à la première ouverture.
- **Données affichées** : viseur caméra, aide, repli « Code à 4 chiffres » (E09).
- **Endpoints** : `POST /api/merchant/pickup-sessions/scan` (`{token}` — payload du QR = token UUID opaque).
- **Actions** : scanner le token opaque sans jamais l’afficher ni proposer sa saisie ; basculer vers E09 pour le code à quatre chiffres.
- **Validations** : payload scanné conforme (UUID), sinon cas 8 sans appel serveur.
- **États** : loading (caméra puis requête) ; error (permission → cas 15 ; QR invalide/expiré/déjà utilisé → cas 8 ; réseau → cas 11 avec token conservé) ; success → E08.
- **Navigation** : entrante — tab bar, E03 (« Prêtes à remettre »), E05 (`ready`) ; sortante — E08, E09.
- **FR/AR/RTL** : aides traduites ; viseur neutre.
- **Hors-ligne** : scan caméra possible mais résolution impossible → cas 11 ; aucune validation locale.
- **Analytics** : `merchant_pickup_scan_started`, `merchant_pickup_scan_succeeded`, `merchant_pickup_scan_manual_fallback`.
- **Android/iOS** : cinématiques de permission caméra et liens réglages propres à chaque OS.

### E08 — Retrait : session et double validation

- **Objectif** : vérifier la commande, remettre la Kadhia, confirmer côté marchand, forcer la finalisation si nécessaire.
- **Préconditions** : session scannée (E07). Le code (E09) finalise directement le retrait.
- **Données affichées** : commande (code court), client (nom, téléphone), lignes et total TND, état de session (scannée / confirmée marchand / en attente client / clôturée), heure de scan.
- **Endpoints** : `PATCH /api/merchant/pickup-sessions/{id}/confirm` ; `PATCH /api/merchant/pickup-sessions/{id}/force-complete` (`{note}`).
- **Actions** : « Remettre la Kadhia » (confirmation marchand) ; « Forcer la finalisation » (visible si marchand confirmé, client non confirmé, ≥ 5 min, note obligatoire) ; « Scanner un autre QR ».
- **Validations** : confirmation marchand unique (bouton désactivé ensuite) ; note de force obligatoire (cas 14) ; erreurs 409 mappées (`PICKUP_SESSION_NOT_SCANNED`, `ORDER_NOT_PICKUP_PENDING`…).
- **États** : loading ; error (cas 8/12/18) ; success intermédiaire (« En attente de confirmation client ») ; success final (« Retrait finalisé », commande `completed`).
- **Navigation** : entrante — E07 ; sortante — E07 (nouveau scan), E03.
- **FR/AR/RTL** : récapitulatif miroir.
- **Hors-ligne** : inaccessible pour les actions (cas 11) ; le récapitulatif déjà chargé reste lisible.
- **Analytics** : `merchant_pickup_confirmed`, `merchant_pickup_force_completed`.
- **Android/iOS** : néant.

### E09 — Retrait : code 4 chiffres et validation manuelle

- **Objectif** : finaliser un retrait sans QR — code dicté par le client, ou validation manuelle auditée en dernier recours.
- **Préconditions** : session valide ; commande `ready` (revalidé serveur).
- **Données affichées** : volet Code — champ 4 chiffres (clavier numérique) ; volet Manuel — sélection de la commande (depuis la liste `ready`, remplace la saisie d'UUID de la PWA — écart PWA corrigé) + champ note obligatoire.
- **Endpoints** : `POST /api/merchant/stores/{storeId}/orders/redeem-by-code` (`{pickupCode}`) ; `POST /api/merchant/stores/{storeId}/orders/{orderId}/validate-manually` (`{note}`) — retrait par code documenté dans le contrat API ; validation manuelle avec note inchangée.
- **Actions** : valider le code ; valider manuellement ; basculer vers E07.
- **Validations** : code = chaîne de 4 chiffres exactement, y compris les zéros initiaux ; note manuelle ≥ 5 caractères (cas 14).
- **États** : loading ; error (code erroné, ambigu ou non éligible → 404, cas 10 ; quota → 429 et attente `Retry-After` ; validation manuelle non `ready` → 409 ; réseau → cas 11) ; success (« Retrait validé », commande `completed`, finalisation directe).
- **Navigation** : entrante — E07, tab Retrait ; sortante — confirmation finale, E03.
- **FR/AR/RTL** : saisie du code LTR (chiffres), habillage miroir.
- **Hors-ligne** : inaccessible (cas 11).
- **Analytics** : `merchant_pickup_code_redeemed`, `merchant_pickup_validated_manually` (sans code ni note).
- **Android/iOS** : clavier numérique natif (`keyboardType`).

### E10 — Historique des commandes

- **Objectif** : consulter les commandes passées (retraits en cours inclus) avec filtres et recherche.
- **Préconditions** : session valide.
- **Données affichées** : cartes (code, badge statut avec `status_label_fr`/`status_label_ar`, client — masqué sur `completed`/`cancelled`/`rejected`, total TND, rendez-vous, date) ; filtres statut/période ; recherche nom/téléphone ; renvoi « Export CSV disponible sur le web ».
- **Endpoints** : `GET /api/merchant/stores/{storeId}/orders/history?status=&date_from=&date_to=&query=&page=&limit=` (limit ≤ 50).
- **Actions** : filtrer ; rechercher ; ouvrir un détail (E05 lecture seule) ; pagination.
- **Validations** : période cohérente (`date_from` ≤ `date_to` — contrôle local avant le 400 serveur).
- **États** : loading ; empty (« Aucune commande sur cette période ») ; error + retry ; success.
- **Navigation** : entrante — E04 (onglet Historique) ; sortante — E05.
- **FR/AR/RTL** : libellés AR fournis par l'API (`status_label_ar`).
- **Hors-ligne** : dernière page en cache lecture seule.
- **Analytics** : `merchant_history_viewed` (filtre).
- **Android/iOS** : sélecteurs de date natifs.

### E11 — Notifications

- **Objectif** : centre de notifications in-app (source de vérité), relais des push.
- **Préconditions** : session valide.
- **Données affichées** : liste (titre, corps, heure relative, pastille non-lu), filtre non-lues, badge global.
- **Endpoints** : `GET /api/merchant/notifications?page=&unread=` ; `PATCH /api/merchant/notifications/{id}/read` ; `PATCH /api/merchant/notifications/read-all` ; enregistrement push natif — **écart API** : `POST /api/merchant/push-subscriptions` est au format Web Push, à cadrer en #565/#566.
- **Actions** : ouvrir (marquée lue → commande liée) ; « Tout marquer lu » ; pagination.
- **Validations** : aucune.
- **États** : loading ; empty ; error + retry ; success.
- **Navigation** : entrante — cloche (toutes surfaces), tap sur un push ; sortante — E05.
- **FR/AR/RTL** : le backend n'expose que `title_fr`/`body_fr` — repli FR en AR, point ouvert §8 (commun au cadrage client).
- **Hors-ligne** : dernière page en cache lecture seule ; marquage lu désactivé.
- **Analytics** : `merchant_notification_opened` (type d'événement), `merchant_push_optin_accepted` / `declined`.
- **Android/iOS** : canaux Android (importance haute pour « nouvelle commande ») vs catégories iOS ; permission runtime Android 13+, opt-in explicite iOS.

### E12 — Catalogue rapide

- **Objectif** : gestes catalogue à forte fréquence — rupture, prix, remise en dispo, en masse.
- **Préconditions** : session valide.
- **Données affichées** : liste produits (nom FR/AR, prix TND, badge dispo/indispo, photo ou placeholder), recherche, mode sélection multiple, renvoi « Gestion avancée sur le web » (création, import, promotions).
- **Endpoints** : `GET /api/merchant/stores/{storeId}/catalog` ; `PATCH /api/merchant/catalog/{merchantProductId}` (`is_available`, `price_tnd`) ; `PATCH /api/merchant/stores/{storeId}/products/bulk-availability` ; recherche référentiel `GET /api/merchant/stores/{storeId}/product-references?q=&barcode=` ; ajout `POST /api/merchant/stores/{storeId}/catalog`.
- **Actions** : rechercher ; basculer dispo/indispo (optimiste avec rollback) ; modifier un prix (saisie TND 3 décimales) ; sélection multiple → dispo en masse ; scanner un code-barres (caméra) pour retrouver/ajouter une référence ; photo produit local (E13).
- **Validations** : prix > 0 au format TND ; confirmation pour la bascule en masse.
- **États** : loading (skeleton) ; empty (catalogue vide → renvoi web) ; error (écriture échouée → rollback + toast) ; success.
- **Navigation** : entrante — onglet Boutique ; sortante — E13, retour.
- **FR/AR/RTL** : `name_ar` affiché en AR quand renseigné, repli FR.
- **Hors-ligne** : lecture seule du dernier catalogue ; écritures désactivées.
- **Analytics** : `merchant_product_availability_toggled`, `merchant_price_updated`, `merchant_bulk_availability_applied` (compteurs — jamais l'identité produit ni le prix), `merchant_barcode_scanned`.
- **Android/iOS** : scan code-barres via caméra (formats EAN) identique ; torche selon OS.

### E13 — Photo produit local

- **Objectif** : photographier un produit local en rayon et publier la photo (#583) — atout mobile.
- **Préconditions** : produit local du catalogue ; permission caméra.
- **Données affichées** : aperçu photo actuelle ou placeholder, viseur/sélecteur, aperçu avant envoi.
- **Endpoints** : `POST /api/merchant/stores/{storeId}/local-products/{localProductId}/photo` — livré PWA, **hors contrat documenté** (renvoi #565, avec contraintes de taille/format à documenter).
- **Actions** : prendre une photo (caméra) ou choisir dans la galerie ; recadrer léger ; envoyer ; remplacer.
- **Validations** : format/taille contrôlés avant envoi (limites à confirmer en #565).
- **États** : loading (upload avec progression) ; error (réseau → cas 11 ; 413/422 → message taille/format) ; success (photo visible immédiatement).
- **Navigation** : entrante — E12 ; sortante — E12.
- **FR/AR/RTL** : standard.
- **Hors-ligne** : prise de vue possible, envoi exigeant le réseau (photo retenue localement jusqu'à l'envoi explicite — pas de file silencieuse).
- **Analytics** : `merchant_local_product_photo_uploaded` (sans image ni identité produit).
- **Android/iOS** : permissions caméra/galerie distinctes ; compression avant upload.

### E14 — Rendez-vous du jour et fermetures

- **Objectif** : consulter les créneaux de retrait, désactiver un créneau, poser une fermeture exceptionnelle simple.
- **Préconditions** : session valide.
- **Données affichées** : créneaux du jour et à venir (`booked_count`/`capacity`, statut actif), fermetures exceptionnelles à venir, horaires d'ouverture (lecture seule), renvoi « Règles récurrentes et horaires sur le web ».
- **Endpoints** : `GET /api/merchant/stores/{storeId}/pickup-slots` ; `DELETE /api/merchant/stores/{storeId}/pickup-slots/{slotId}` (désactivation) ; `GET/POST/DELETE /api/merchant/stores/{storeId}/exceptional-closures` ; `GET /api/merchant/stores/{storeId}/opening-hours` (lecture).
- **Actions** : désactiver un créneau (confirmation — les rendez-vous déjà pris restent honorés, règle backend) ; créer/supprimer une fermeture exceptionnelle (date, motif) ; pull-to-refresh.
- **Validations** : fermeture sur date future ; confirmation explicite avant désactivation.
- **États** : loading ; empty (« Aucun créneau — configurez vos règles sur le web ») ; error + retry ; success.
- **Navigation** : entrante — onglet Boutique ; sortante — E16 (délai minimal), retour.
- **FR/AR/RTL** : créneaux horaires localisés `Africa/Tunis`.
- **Hors-ligne** : lecture seule du dernier état ; écritures désactivées.
- **Analytics** : `merchant_slot_deactivated`, `merchant_closure_created` (sans motif).
- **Android/iOS** : sélecteur de date natif.

### E15 — QR magasin

- **Objectif** : afficher le QR de la supérette et le partager (le client le scanne pour ouvrir la boutique).
- **Préconditions** : session valide.
- **Données affichées** : QR (`qr_code_token` → `target_url` absolu), nom et slug de la supérette, luminosité augmentée à l'affichage.
- **Endpoints** : `GET /api/merchant/stores/{storeId}/qr-code` ; `GET /api/merchant/stores/{storeId}/qr-code.png` / `.pdf` (téléchargement pour partage).
- **Actions** : afficher plein écran ; partager PNG/PDF via la feuille de partage native ; renvoi « Impression depuis le web ».
- **Validations** : aucune (lecture seule — pas de régénération côté marchand, règle contrat).
- **États** : loading ; error + retry ; success.
- **Navigation** : entrante — onglet Boutique ; sortante — retour.
- **FR/AR/RTL** : QR neutre ; habillage traduit.
- **Hors-ligne** : dernier QR en cache affichable (le token ne change pas côté marchand).
- **Analytics** : `merchant_store_qr_viewed`, `merchant_store_qr_shared` (format).
- **Android/iOS** : feuille de partage native respective ; API de luminosité distinctes.

### E16 — Paramètres et compte

- **Objectif** : profil, mot de passe, langue, délai minimal avant retrait, déconnexion ; renvois web pour le reste.
- **Préconditions** : session valide.
- **Données affichées** : identité (nom, email lecture seule, téléphone), badge compte principal/secondaire, sélecteur de langue FR/AR, sous-écran « Délai minimal avant retrait » (`GET/PATCH /api/merchant/stores/{storeId}/ordering-policy`, entier 0–10080 minutes avec presets ; 422 `SHOP_ORDERING_POLICY_INVALID_LEAD_TIME` mappé), version d'app, renvois web : équipe, apparence, abonnement/documents, export CSV, statistiques, onboarding.
- **Endpoints** : `GET/PATCH /api/merchant/me` ; `PATCH /api/merchant/me/password` ; `GET/PATCH /api/merchant/stores/{storeId}/ordering-policy`.
- **Actions** : modifier prénom/nom/téléphone ; changer le mot de passe (ancien requis) ; changer de langue (bascule RTL) ; modifier le délai minimal (confirmation avec rappel de l'effet sur les rendez-vous proposés aux clients) ; se déconnecter (purge du token).
- **Validations** : prénom/nom requis ; nouveau mot de passe ≥ 8 caractères ; délai entier dans [0, 10080].
- **États** : loading ; error (422 champ par champ) ; success (confirmation visuelle).
- **Navigation** : entrante — onglet Boutique ; sortante — E01 (déconnexion), liens web externes.
- **FR/AR/RTL** : bascule `I18nManager` (peut requérir un redémarrage — comportement commun au cadrage client).
- **Hors-ligne** : lecture seule ; modifications désactivées.
- **Analytics** : `merchant_language_changed` (fr|ar), `merchant_lead_time_updated` (ancienne/nouvelle valeur en minutes), `merchant_logout`.
- **Android/iOS** : néant.

### E17 — Mise à jour obligatoire

- **Objectif** : bloquer une version d'app sous le seuil de version API minimale (ADR-0007).
- **Préconditions** : contrôle de version au démarrage (endpoint public de version minimale — à définir en #565, commun aux deux apps).
- **Données affichées** : message explicatif, bouton vers le store.
- **Endpoints** : endpoint public de version minimale (écart API assumé, #565).
- **Actions** : ouvrir Google Play / App Store.
- **Validations** : écran non contournable.
- **États** : un seul état ; en cas d'échec réseau du contrôle, l'app continue et re-vérifie plus tard (jamais de blocage hors-ligne).
- **Navigation** : entrante — démarrage ; sortante — store externe.
- **FR/AR/RTL** : standard.
- **Hors-ligne** : jamais affiché sur simple échec réseau.
- **Analytics** : `force_update_shown`.
- **Android/iOS** : liens store distincts.

---

## 6. Politique cache / hors-ligne V1

Politique **minimaliste et honnête**, alignée sur le cadrage Client (lecture
seule, aucune action métier hors-ligne) et durcie pour le rôle marchand :

- **Lecture seule en cache** : dernier dashboard, dernières listes de commandes
  (actives et historique), derniers détails consultés, dernier catalogue,
  créneaux, QR magasin, dernière page de notifications. Tout contenu servi du
  cache porte un bandeau « données non actualisées » avec l'heure du dernier
  chargement.
- **Aucune action métier hors-ligne** : accepter, refuser, accepter
  partiellement, préparer, marquer prête, scanner, confirmer un retrait, forcer
  la finalisation, valider par code ou manuellement, modifier prix/dispo/délai
  **exigent le serveur**. Hors réseau, les boutons sont désactivés avec message
  explicite ; aucune transition n'est jamais simulée comme réussie, aucune file
  locale silencieuse.
- **Re-sync au retour réseau** : les écrans visibles re-fetchent à la
  reconnexion et au retour au premier plan ; l'état d'une commande est
  **toujours** revalidé serveur avant d'afficher ses actions (protection contre
  l'état local obsolète et le multi-appareils).
- **Conflits signalés, jamais écrasés** : tout 409 déclenche un re-GET et
  l'affichage de l'état serveur, avec l'auteur de la transition quand
  l'historique le fournit (cas 12).
- **Exceptions d'usage** : le scan caméra fonctionne hors-ligne mais la
  résolution attend le réseau (token conservé, cas 11) ; une photo produit prise
  hors-ligne reste locale jusqu'à un envoi explicite réussi.
- Implémentation indicative (ADR-0007) : TanStack Query + persistance légère ;
  JWT en stockage sécurisé, jamais dans le cache de données.

---

## 7. Analytics

Événements **non sensibles uniquement** : jamais de PII (client ou marchand),
jamais de contenu de Kadhia (libellés produits, notes libres, raisons de refus),
jamais de montant individuel, jamais de code de retrait ni de token.
Identifiants techniques admis : `store_id`, statuts, compteurs. Outil et
consentement : décision humaine §8 (commune au cadrage client).

| Événement | Propriétés (non sensibles) |
|---|---|
| `merchant_app_opened` | source (direct, push) |
| `merchant_login_success` / `merchant_login_failure` | motif (invalid, inactive, expired_temporary, network) |
| `merchant_first_login_completed` | — |
| `merchant_dashboard_viewed` | compteurs agrégés (submitted, preparing, ready) |
| `merchant_orders_list_viewed` | filtre, nombre de commandes |
| `merchant_order_viewed` | statut |
| `merchant_order_accepted` / `merchant_order_rejected` | — |
| `merchant_order_partially_accepted` | nombre de lignes refusées |
| `merchant_order_preparation_started` / `merchant_order_marked_ready` | — |
| `merchant_pickup_scan_started` / `merchant_pickup_scan_succeeded` / `merchant_pickup_scan_manual_fallback` | — |
| `merchant_pickup_confirmed` / `merchant_pickup_force_completed` | — |
| `merchant_pickup_code_redeemed` / `merchant_pickup_validated_manually` | — |
| `merchant_history_viewed` | filtre |
| `merchant_notification_opened` | type d'événement métier |
| `merchant_push_optin_accepted` / `merchant_push_optin_declined` | — |
| `merchant_product_availability_toggled` | sens (on/off) |
| `merchant_price_updated` | — |
| `merchant_bulk_availability_applied` | nombre de produits, sens |
| `merchant_barcode_scanned` | résultat (trouvé / non trouvé) |
| `merchant_local_product_photo_uploaded` | — |
| `merchant_slot_deactivated` / `merchant_closure_created` | — |
| `merchant_store_qr_viewed` / `merchant_store_qr_shared` | format (png/pdf) |
| `merchant_lead_time_updated` | ancienne et nouvelle valeur (minutes) |
| `merchant_language_changed` | fr \| ar |
| `merchant_logout` | — |
| `force_update_shown` | — |
| `error_shown` | clé i18n, code HTTP (sans request_id ni payload) |

---

## 8. Dépendances et points ouverts

### Dépendances de cadrage

- **#565 (MOBILE-004 — audit API, auth, sessions)** :
  - politique de session mobile (JWT 1h sans refresh) et endpoint public de
    version API minimale (E17) — communs au cadrage client ;
  - confirmation au contrat des endpoints **utilisés par la PWA marchand mais
    absents de `docs/architecture/api-contract.md`** :
    `POST /api/merchant/stores/{storeId}/orders/{orderId}/validate-manually`,
    `POST /api/merchant/stores/{storeId}/local-products/{localProductId}/photo`
    (#583, avec limites taille/format),
    `POST /api/merchant/stores/{storeId}/orders/{orderId}/whatsapp-contact`
    (#378, si repêché),
    `GET /api/merchant/products/{merchantProductId}/price-history`,
    `GET/PATCH /api/merchant/stores/{storeId}/profile`,
    ainsi que les routes catalogue avancé restant web
    (`photo-import/preview|commit`, `import-from-product-group`,
    `product-groups`, `local-products/bulk`) ;
  - format d'enregistrement push natif (FCM/APNs) vs
    `POST /api/merchant/push-subscriptions` actuel (Web Push) ;
  - retrait par code : sémantique confirmée et documentée en QA mobile #12, finalisation directe `completed` ; validation manuelle avec note conservée ;
  - convention `request_id` sur les erreurs 5xx ;
  - règles d'idempotence des transitions sensibles (double tap réseau lent sur
    accept/ready/confirm) — matrice à produire en #565.
- **#566 (MOBILE-005 — push natif, QR, App/Universal Links)** : canaux/catégories
  de notification (importance haute « nouvelle commande »), push → écran cible
  avec reprise après login, format du QR de retrait scanné (payload = token),
  liens des e-mails marchands (invitation, reset) vers l'app.
- **#567 (MOBILE-006 — sécurité, stores, QA, publication)** : identifiant
  `tn.kadhia.merchant` (hypothèse ADR-0007), checklist de publication, E2E des
  parcours accept → ready → scan → double validation.

### Décisions humaines restantes

1. **Gestion d'équipe et export CSV maintenus web** : valider ces reports (ou
   preuve d'usage terrain contraire) avant le backlog de build.
2. **Session mobile** : reconnexion horaire vs refresh token (arbitrage
   sécurité/UX, #565 — commun aux deux apps).
3. **Notifications en arabe** : le backend n'expose que `title_fr`/`body_fr` —
   accepter le repli FR en V1 ou ajouter `title_ar`/`body_ar` (écart produit,
   commun au cadrage client).
4. **Outil analytics** et politique de consentement/opt-out (commun client).
5. **WhatsApp commande (#378)** : trancher le produit avant tout repêchage
   mobile.
6. **Multi-boutique** : confirmer que le sélecteur de supérette reste hors V1
   (décision backend MERCHANT-TEAM-003) ou planifier son cadrage.
7. **Ordre de lancement** : ADR-0005 place **Android marchand en premier** — ce
   cadrage alimente donc le premier backlog de build mobile ; découpage par
   capacité (commandes, retrait, catalogue, boutique), pas par plateforme.
