# Mobile Client — Périmètre, parcours et écrans (MOBILE-002, #563)

> Cadrage fonctionnel de l'application **Mobile Client V1** (React Native + Expo,
> `apps/client` du dépôt `click-and-collect-mobile` — ADR-0007). Aucun code mobile
> dans ce document ni dans ce dépôt. Le mobile **reprend les parcours PWA client
> validés** (`apps/frontend/src/app/(client)/`) ; il n'invente pas de produit.
> Contrat API de référence : `docs/architecture/api-contract.md`
> (source de vérité : `/api/docs.json`).

Vocabulaire métier non négociable : **Kadhia** (jamais « panier »), supérette,
marchand, client, **rendez-vous** (créneau de retrait), **retrait**, montants en **TND**.

---

## 1. Périmètre V1

Chaque point des cinq blocs de l'issue est confronté à ce que la PWA livre
réellement. « Inclus » = repris tel que validé en PWA (adapté aux conventions
natives) ; « reporté » = post-V1 avec justification ; « écart PWA » = la PWA a un
comportement incomplet que le mobile corrige sans changer le produit.

### 1.1 Accès et compte

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Inscription client | **Inclus** | PWA `register` → `POST /api/auth/register/customer`. Écart PWA corrigé : la réponse contient déjà le JWT — le mobile connecte directement (la PWA renvoie vers `/login` et perd le contexte). |
| Connexion / déconnexion | **Inclus** | PWA `login` → `POST /api/auth/login` ; déconnexion locale (pas d'endpoint logout, JWT stateless 1h). |
| Récupération / réinitialisation du mot de passe | **Inclus** | PWA `(auth)/forgot-password` + `reset-password` → `POST /api/auth/password-reset/request` / `confirm`. La confirmation passe par le lien e-mail (web) ; l'app ouvre ce lien via universal link (#566). |
| Profil client | **Inclus** | PWA `profile` → `GET/PATCH /api/me/profile` (prénom, nom). |
| Suppression du compte | **Inclus** | PWA `profile` (modale « SUPPRIMER ») → `DELETE /api/me/account`. Requis par les stores (Apple/Google exigent la suppression in-app). |
| Reprise de session | **Inclus** | JWT conservé dans le stockage sécurisé (expo-secure-store) ; à l'ouverture, l'app restaure la session si le token est encore valide. Détail sessions mobiles : #565. |
| Gestion de session expirée | **Inclus** | Équivalent de l'intercepteur 401 PWA : purge du token + renvoi vers Connexion avec retour au contexte (`redirect`). JWT 1h sans refresh token : politique de renouvellement à trancher en #565. |

### 1.2 Découverte de supérette

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Ouverture depuis QR magasin | **Inclus** | PWA `stores/by-qr/[qrToken]` → `GET /api/stores/by-qr/{qrCodeToken}` + `POST /api/me/stores/{storeId}/visit` (source `qr_code`). Le mobile ajoute le **scan caméra natif** (expo-camera) — la PWA n'offre que la saisie manuelle du code (`stores/by-qr-scan`) ; la saisie manuelle est conservée en secours. |
| Ouverture depuis un lien universel | **Inclus** | Nouveau canal d'entrée (App Links / Universal Links, #566) résolvant vers les mêmes routes que le QR. |
| Recherche de supérette | **Inclus** | PWA `stores` → `GET /api/stores/search?query=&city=`. |
| Fiche supérette | **Inclus** | PWA `stores/[shopId]` → `GET /api/stores/{storeId}` + thème `GET /api/stores/{storeId}/theme` + horaires `GET /api/stores/{storeId}/opening-hours`. |
| Relation client/supérette et supérette active | **Inclus** | PWA `stores` → `GET /api/me/stores`, `POST /api/me/stores/{storeId}/visit`, `PATCH /api/me/stores/{storeId}/favorite`, `DELETE /api/me/stores/{storeId}`. La « supérette active » (contexte Kadhia courant) est un état local d'app, comme en PWA. |

### 1.3 Catalogue et Kadhia

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Catalogue public | **Inclus** | PWA `stores/[shopId]/catalog` → `GET /api/stores/{storeId}/catalog` (images produits S13-005 avec placeholder catégorie). |
| Recherche et catégories | **Inclus** | Mêmes query params `?query=` et `?category=` (slug). |
| Détail produit | **Reporté post-V1** | La PWA n'a **aucun écran de détail produit** (la carte produit n'est pas cliquable). Le mobile n'invente pas ; à réévaluer quand la PWA l'aura validé. |
| Ajout, suppression, modification de quantités | **Inclus** | `PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}` (upsert quantité), `DELETE .../lines/{merchantProductId}`. |
| Création et reprise d'une Kadhia draft | **Inclus** | `POST /api/me/stores/{storeId}/kadhias`, `GET /api/me/kadhias?status=draft`, `GET /api/me/kadhias/{kadhiaId}`. Création toujours explicite (jamais par GET). |
| Multi-Kadhia | **Inclus** | Modèle backend « Kadhia multiple » ; la PWA le gère (sélecteur de brouillon au catalogue, liste par onglets). Écart PWA corrigé : le mobile porte l'identifiant de la Kadhia dans la navigation jusqu'au rendez-vous (la PWA s'appuie sur un contexte localStorage fragile pour l'écran créneau). |
| Note client | **Inclus** | `PATCH /api/me/kadhias/{kadhiaId}` (`notes`, sert aussi de titre) + note au marchand transmise au submit (`notes` du payload). |
| Produit devenu indisponible / prix modifié | **Inclus** | Revalidation serveur systématique : erreurs `PRODUCT_UNAVAILABLE` à la soumission, lignes refusées via acceptation partielle, prix snapshotés à l'ajout de ligne (jamais re-fetchés — règle sécurité). Les **suggestions de remplacement** PWA (`GET /api/me/kadhias/{kadhiaId}/replacements`) sont **reportées post-V1** : endpoint hors contrat documenté, à confirmer en audit #565 avant intégration au SDK. |
| Partage de Kadhia | **Reporté post-V1** | Livré en PWA (`share-links`) mais hors des blocs de l'issue ; dépend des liens universels (#566) et d'endpoints hors contrat documenté. |
| Favoris produits et suggestions d'achat | **Reporté post-V1** | Présents en PWA mais hors blocs de l'issue ; endpoints (`/api/me/stores/{storeId}/favorite-products`, `/suggestions`, `/api/me/products/{merchantProductId}/favorite`) absents du contrat documenté — écart à traiter en #565. |

### 1.4 Commande et retrait

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Choix du rendez-vous | **Inclus** | PWA `kadhia/slot` → `GET /api/stores/{storeId}/pickup-slots?from=today&available=true` avec `booking_policy` (délai minimal de préparation, ORDER-LEAD-002). |
| Soumission idempotente | **Inclus** | `POST /api/me/kadhias/{kadhiaId}/submit` — bouton verrouillé pendant l'envoi ; la Kadhia passant `submitted`, un double envoi reçoit `KADHIA_NOT_EDITABLE` (pas de double commande). |
| Historique et détail commande | **Inclus** | `GET /api/me/orders`, `GET /api/me/orders/{id}`, `GET /api/me/orders/{orderId}/status-history`. |
| Suivi des statuts | **Inclus** | `GET /api/me/orders/{orderId}/status` (polling simple, comme prévu par le contrat). |
| Acceptation, refus, acceptation partielle | **Inclus** | Statuts `accepted` / `rejected` / `partially_accepted` avec `rejection_reason` et `rejected_lines` (snapshot). |
| Retour vers la Kadhia après acceptation partielle | **Inclus** | La Kadhia repasse `draft` ; bandeau + reprise d'édition + re-soumission sur le rendez-vous réservé (parcours PWA `?context=partially_accepted`). |
| Notifications in-app | **Inclus** | `GET /api/me/notifications`, `PATCH .../{id}/read`, `PATCH .../read-all`. Source de vérité. |
| Notifications push | **Inclus (V1 minimale)** | Push natif FCM/APNs via expo-notifications (#566). **Écart API** : les endpoints actuels `POST /api/me/push-subscriptions` (+ `/unregister`) sont au format Web Push (`endpoint`, `p256dhKey`, `authKey`) — l'enregistrement de tokens FCM/APNs est à cadrer en #565/#566. |
| QR de retrait | **Inclus** | `GET /api/me/orders/{orderId}/pickup-session` (`qr_payload` = token opaque). |
| Code à quatre chiffres | **Inclus** | `pickup_code` exposé par `GET /api/me/orders/{id}` quand la commande est `ready` (secours si le scan échoue ; le marchand le saisit via son interface). |
| Confirmation client | **Inclus** | `PATCH /api/me/pickup-sessions/{id}/confirm` (double validation client + marchand). |
| États terminé et annulé | **Inclus** | `completed` / `cancelled` ; annulation client `POST /api/me/orders/{orderId}/cancel` (statut `submitted` uniquement). |
| Contact WhatsApp commande | **Reporté post-V1** | Présent en PWA mais conditionnel produit (#378) et hors contrat documenté. |

### 1.5 Localisation et accessibilité

| Point | V1 mobile | Référence PWA / justification |
|---|---|---|
| Français | **Inclus** | Langue par défaut. |
| Arabe | **Inclus** | Clés i18n partagées avec le frontend quand le sens est identique (package `@kadhia/i18n`). Écart PWA corrigé : **100 % des écrans mobiles passent par les clés i18n** (plusieurs pages PWA sont encore en FR en dur : accueil, login, register, stores, notifications). |
| RTL complet | **Inclus** | `I18nManager` RN + composants du design system pensés RTL (icônes directionnelles, navigation, timeline). |
| Tailles de texte / accessibilité minimale | **Inclus** | Respect du Dynamic Type / font scale système, cibles tactiles ≥ 44 pt, labels d'accessibilité sur les actions clés (reprend l'exigence a11y minimum #379). |
| Formats date/heure et TND | **Inclus** | Fuseau `Africa/Tunis`, formats tunisiens, montants `X,XXX TND` à 3 décimales (package `mobile-core`). Jamais `new Date('YYYY-MM-DD')` naïf (gotcha connu de décalage UTC). |

### 1.6 Hors périmètre V1 (rappel issue)

Paiement en ligne, livraison, marketplace multi-marchands à panier commun,
publicité avancée, recommandations sponsorisées, import IA, administration,
fonctionnement totalement hors ligne, fonctionnalités expérimentales non
validées en PWA.

---

## 2. Navigation

Arborescence calquée sur les routes PWA `(client)`, adaptée aux conventions
natives (tab bar + piles). En AR, l'ordre des onglets et les gestes de retour
s'inversent (RTL).

```text
App
├── Pile Auth (hors onglets, présentée si non connecté quand requis)
│   ├── Connexion                 (PWA /login)
│   ├── Inscription               (PWA /register)
│   └── Mot de passe oublié / réinitialisation   (PWA /forgot-password, /reset-password)
│
├── Tab bar (4 onglets)
│   ├── Onglet Accueil
│   │   ├── Accueil               (PWA /)
│   │   ├── Mes supérettes / recherche   (PWA /stores)
│   │   ├── Fiche supérette       (PWA /stores/[shopId])
│   │   └── Catalogue             (PWA /stores/[shopId]/catalog)
│   ├── Onglet Kadhia
│   │   ├── Mes Kadhias           (PWA /kadhia)
│   │   ├── Détail Kadhia         (PWA /kadhia/[kadhiaId])
│   │   └── Rendez-vous et soumission    (PWA /kadhia/slot)
│   ├── Onglet Commandes
│   │   ├── Mes commandes         (PWA /orders)
│   │   ├── Suivi de commande     (PWA /orders/[orderId])
│   │   └── Retrait               (PWA /orders/[orderId]/pickup)
│   └── Onglet Profil
│       └── Profil et compte      (PWA /profile ; langue, déconnexion, suppression)
│
├── Écrans transversaux (modaux / hors onglets)
│   ├── Scan QR magasin (caméra + saisie manuelle)   (remplace PWA /stores/by-qr-scan)
│   ├── Ouverture supérette par QR ou lien (transitoire)  (PWA /stores/by-qr/[qrToken])
│   ├── Notifications (icône cloche avec badge non-lus)   (PWA /notifications)
│   └── Mise à jour obligatoire (version API minimale, ADR-0007)
```

Règles :

- le catalogue et les fiches supérettes sont consultables **sans compte** ;
  la connexion n'est exigée qu'à la première action authentifiée (créer une
  Kadhia, voir ses commandes…), avec retour au contexte après login ;
- les entrées externes (QR scanné hors app, universal link, notification push)
  atterrissent sur l'écran transitoire ou directement sur l'écran cible, avec
  la même règle de retour au contexte ;
- badge de notifications non lues sur la cloche ; badge du nombre d'articles de
  la Kadhia active sur l'onglet Kadhia.

---

## 3. Parcours nominaux

### 3.0 Parcours cœur

1. Le client scanne le QR de la supérette (caméra in-app ou appareil photo système via universal link).
2. L'app résout le QR (`GET /api/stores/by-qr/{qrCodeToken}`), enregistre la visite si connecté (`POST /api/me/stores/{storeId}/visit`) et ouvre le catalogue aux couleurs de la supérette.
3. Le client parcourt le catalogue (`GET /api/stores/{storeId}/catalog`), filtre par catégorie ou recherche.
4. Au premier ajout : connexion si nécessaire, puis création explicite d'une Kadhia (`POST /api/me/stores/{storeId}/kadhias`) et ajout des lignes (`PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}`).
5. Le client ouvre sa Kadhia, ajuste quantités et note, puis choisit son rendez-vous (`GET /api/stores/{storeId}/pickup-slots?from=today&available=true`, délai minimal `booking_policy` respecté).
6. Il soumet (`POST /api/me/kadhias/{kadhiaId}/submit` avec `pickup_slot_id` et note) → commande `submitted`.
7. Il suit le statut sur l'écran commande (`GET /api/me/orders/{id}`, notifications in-app/push : acceptée, en préparation…).
8. À `ready`, il reçoit la notification « commande prête » ; l'écran commande affiche le code à 4 chiffres (`pickup_code`) et le CTA retrait.
9. À la supérette, il présente le QR de retrait (`GET /api/me/orders/{orderId}/pickup-session`) — ou dicte le code à 4 chiffres si le scan échoue.
10. Le marchand scanne et confirme ; l'app suit la session (`GET /api/me/orders/{orderId}/status` en polling) → `pickup_pending`.
11. Le client confirme à son tour (`PATCH /api/me/pickup-sessions/{id}/confirm`) — double validation.
12. La commande passe `completed` ; elle reste consultable dans l'historique.

### 3.1 Inscription puis retour au contexte initial

1. Client anonyme au catalogue ; il tape « Commencer ma Kadhia ».
2. Écran Connexion (contexte cible mémorisé) → lien « Créer un compte ».
3. Inscription (`POST /api/auth/register/customer`) ; la réponse contient le JWT → session ouverte immédiatement.
4. Retour automatique au catalogue d'origine ; l'action initiale (création de Kadhia) est rejouée. *(Écart PWA assumé : la PWA renvoie vers `/login` et perd le contexte.)*

### 3.2 Login depuis un deep link

1. Le client ouvre un universal link (ex. `…/orders/{orderId}`) sans session valide.
2. L'app mémorise la cible, présente Connexion.
3. `POST /api/auth/login` → succès.
4. Navigation vers la cible du lien (écran commande), pile de retour cohérente (onglet Commandes).

### 3.3 Reprise d'une Kadhia existante

1. Le client ouvre l'onglet Kadhia ; `GET /api/me/kadhias?status=draft` liste ses brouillons.
2. Il ouvre un brouillon (`GET /api/me/kadhias/{kadhiaId}`).
3. Il modifie lignes ou note, poursuit vers le rendez-vous et soumet — ou retourne au catalogue de la supérette pour compléter.

### 3.4 Multi-Kadhia

1. Au catalogue, le client tape « + » alors que plusieurs brouillons existent pour cette supérette.
2. Un sélecteur propose les brouillons (titre = note, total, date) ou « Nouvelle Kadhia ».
3. Le choix devient la Kadhia active de la supérette ; l'ajout s'applique à elle.
4. Chaque Kadhia se soumet indépendamment ; l'identifiant est porté par la navigation jusqu'à la soumission.

### 3.5 Acceptation partielle puis nouvelle soumission

1. Notification « commande partiellement acceptée » → écran commande : motif, lignes acceptées, lignes refusées (`rejected_lines`).
2. CTA « Modifier ma Kadhia » : la Kadhia est repassée `draft` côté serveur ; l'app l'ouvre en contexte acceptation partielle.
3. Le client ajuste (retire ou remplace les produits refusés, quantités).
4. Il re-soumet (`POST /api/me/kadhias/{kadhiaId}/submit`) sur le rendez-vous déjà réservé (exempté du délai minimal) → la commande existante est re-soumise.

### 3.6 Annulation

1. Sur une commande `submitted`, action « Annuler la commande ».
2. Confirmation explicite (dialogue natif).
3. `POST /api/me/orders/{orderId}/cancel` → `cancelled`, capacité du rendez-vous libérée.
4. L'écran reflète le statut ; la commande reste dans l'historique.

### 3.7 Notification ouverte alors que l'utilisateur est déconnecté

1. Push reçu (« Votre commande est prête ») ; l'utilisateur tape la notification sans session valide.
2. L'app démarre, mémorise la cible (commande), présente Connexion.
3. Après login, navigation directe vers l'écran commande visé.
4. Si le compte connecté ne possède pas la commande : message « commande introuvable » et retour à Mes commandes (jamais de fuite d'information).

### 3.8 Notification d'une commande déjà finalisée

1. Le client ouvre une notification ancienne (commande `completed` ou `cancelled`).
2. `GET /api/me/orders/{id}` → l'écran commande s'affiche dans son état final (pas de CTA retrait ni annulation).
3. Aucune erreur : l'état final est un état normal, l'historique de statuts reste consultable.

---

## 4. Cas d'erreur obligatoires

Convention transverse : les messages sont définis en FR ci-dessous avec leur
**clé i18n** (traduction AR obligatoire via `@kadhia/i18n`, jamais de texte en
dur). Les erreurs serveur 5xx affichent le **`request_id`** quand la réponse ou
ses en-têtes le fournissent, pour le support.

| # | Cas | Détection | Message FR (clé i18n) | Reprise proposée |
|---|---|---|---|---|
| 1 | QR invalide, inconnu ou expiré | `GET /api/stores/by-qr/{qrCodeToken}` → 404 (`STORE_NOT_FOUND`) ou token malformé | « Ce QR code n'est pas reconnu. » (`errors.qr.unknown`) | Re-scanner ; saisie manuelle du code ; retour Accueil. |
| 2 | Supérette inactive | 404/`STORE_DISABLED` sur la fiche/catalogue ; `STORE_SUSPENDED_FOR_SUBSCRIPTION` (409) à la soumission | « Cette supérette n'accepte pas de commandes pour le moment. » (`errors.store.inactive`) | Retour à Mes supérettes ; la Kadhia draft est conservée. |
| 3 | Réseau absent ou lent | Échec/timeout de la requête (pas de statut HTTP) | « Connexion impossible. Vérifiez votre réseau. » (`errors.network.offline`) | Bouton « Réessayer » ; contenu en cache affiché avec bandeau « données non actualisées » (§6). |
| 4 | Token expiré ou révoqué | 401 sur une route authentifiée | « Votre session a expiré. Reconnectez-vous. » (`errors.auth.sessionExpired`) | Purge du token, écran Connexion avec retour au contexte après login. |
| 5 | Mauvais rôle connecté | 403 sur `/api/me/*` (compte marchand/admin dans l'app Client) | « Ce compte n'est pas un compte client. » (`errors.auth.wrongRole`) | Déconnexion proposée ; renvoi vers l'app/portail marchand. |
| 6 | Catalogue vide | `GET .../catalog` → 200 avec `items: []` | « Aucun produit dans ce catalogue pour l'instant. » (`catalog.empty`) — variantes recherche/catégorie sans résultat | Effacer les filtres ; revenir plus tard ; ce n'est pas une erreur technique. |
| 7 | Produit indisponible entre affichage et ajout | `PUT .../lines/...` → 4xx `PRODUCT_NOT_AVAILABLE` ; ou `PRODUCT_UNAVAILABLE` à la soumission | « Ce produit n'est plus disponible. » (`errors.product.unavailable`) | Retirer la ligne ; rafraîchir le catalogue ; à la soumission, la Kadhia est conservée pour ajustement. |
| 8 | Prix modifié | Prix snapshoté à l'ajout de ligne : écart visible entre catalogue rafraîchi et ligne de Kadhia | « Le prix de ce produit a changé. Le prix de votre Kadhia est celui affiché à l'ajout. » (`errors.product.priceChanged`) | Information non bloquante ; le client peut supprimer/re-ajouter la ligne pour prendre le nouveau prix. |
| 9 | Rendez-vous devenu complet | Soumission → `PICKUP_SLOT_FULL` (ou `PICKUP_SLOT_EXPIRED`, `PICKUP_SLOT_MINIMUM_LEAD_TIME_NOT_MET`) | « Ce rendez-vous vient d'être complet. Choisissez-en un autre. » (`errors.slot.full`) | Rechargement automatique des rendez-vous, focus sur la liste ; la Kadhia est conservée. |
| 10 | Double tap / double soumission | Verrou UI pendant l'envoi ; seconde requête → `KADHIA_NOT_EDITABLE` | Aucun message si le verrou suffit ; sinon « Votre Kadhia a déjà été envoyée. » (`errors.kadhia.alreadySubmitted`) | Navigation vers la commande créée. |
| 11 | Commande déjà modifiée depuis un autre appareil | Action → 409 (`ORDER_INVALID_STATUS`, `KADHIA_NOT_EDITABLE`) ou état différent au re-GET | « Cette commande a changé depuis un autre appareil. » (`errors.order.conflict`) | Re-GET automatique et affichage de l'état serveur ; jamais d'écrasement silencieux. |
| 12 | QR de retrait expiré ou déjà utilisé | `pickup-session` → `is_expired` / `is_used` ; confirmations → `PICKUP_SESSION_EXPIRED`, `PICKUP_SESSION_ALREADY_USED` | « Ce QR de retrait n'est plus valide. Adressez-vous au marchand. » (`errors.pickup.invalidSession`) | Rafraîchir la session ; code à 4 chiffres en secours ; le marchand peut finaliser côté caisse (force completion). |
| 13 | Permission caméra refusée | API permissions OS (refus ou refus définitif) | « Sans accès à la caméra, scannez via la saisie manuelle du code. » (`errors.permissions.camera`) | Saisie manuelle du code magasin ; lien vers les réglages système (différence Android/iOS §5). |
| 14 | Permission notifications refusée | API permissions OS au moment de l'opt-in | « Vous ne recevrez pas d'alertes. Le suivi reste disponible dans l'app. » (`errors.permissions.notifications`) | L'app fonctionne intégralement (notifications in-app + polling) ; relance de l'opt-in aux moments clés (après 1re soumission), lien réglages. |
| 15 | Lien profond malformé | Route/paramètres du link non reconnus (UUID invalide, chemin inconnu) | « Ce lien n'est pas valide. » (`errors.link.malformed`) | Atterrissage sur l'Accueil, sans crash ; log observabilité (sans PII). |
| 16 | Erreur serveur avec `request_id` | 5xx | « Une erreur est survenue. Réessayez. Réf. : {request_id} » (`errors.server.generic`) | Bouton « Réessayer » ; le `request_id` est copiable pour le support. |

---

## 5. Inventaire des écrans

18 fiches. Rappels transverses (non répétés dans chaque fiche) : tous les
libellés passent par les clés i18n FR/AR avec RTL complet ; tous les montants en
TND (3 décimales) ; dates/heures en `Africa/Tunis` ; les états d'erreur suivent
le §4 ; les événements analytics respectent le §7 (jamais de PII ni de contenu
de Kadhia).

### E01 — Connexion

- **Objectif** : ouvrir une session client et revenir au contexte demandé.
- **Préconditions** : aucune (accessible déconnecté).
- **Données affichées** : champs email / mot de passe, liens Inscription et Mot de passe oublié.
- **Endpoints** : `POST /api/auth/login`.
- **Actions** : se connecter ; aller à l'inscription ; mot de passe oublié.
- **Validations** : email non vide au format valide, mot de passe non vide ; bouton verrouillé pendant l'envoi.
- **États** : loading (bouton) ; error : 401 `AUTH_INVALID_CREDENTIALS` (« Identifiants incorrects »), 429 (« Trop de tentatives »), réseau ; success → navigation.
- **Navigation** : entrante — toute action authentifiée en étant déconnecté, deep link, notification, 401 global ; sortante — contexte d'origine (défaut : Accueil), Inscription, Mot de passe oublié.
- **FR/AR/RTL** : formulaire miroir en AR ; saisie email reste LTR.
- **Hors-ligne** : formulaire désactivé avec bandeau hors-ligne ; aucune tentative locale.
- **Analytics** : `login_success`, `login_failure` (motif : invalid/ratelimited/network — sans email).
- **Android/iOS** : autofill/gestionnaires de mots de passe natifs (`autofillHints` / `textContentType`).

### E02 — Inscription

- **Objectif** : créer un compte client et enchaîner sans rupture sur le contexte initial.
- **Préconditions** : déconnecté.
- **Données affichées** : nom, email, mot de passe, téléphone (optionnel), lien Connexion.
- **Endpoints** : `POST /api/auth/register/customer` (la réponse `201` contient `token` + `user` → session ouverte immédiatement).
- **Actions** : créer le compte ; basculer vers Connexion.
- **Validations** : nom requis, email au format valide, mot de passe ≥ 8 caractères ; verrou pendant l'envoi.
- **États** : loading ; error : 409 `AUTH_EMAIL_ALREADY_USED`, 422 (détails champ par champ), réseau ; success → session ouverte + retour au contexte.
- **Navigation** : entrante — Connexion, Accueil ; sortante — contexte d'origine (écart PWA corrigé : pas de retour à Connexion), Connexion.
- **FR/AR/RTL** : idem E01.
- **Hors-ligne** : formulaire désactivé.
- **Analytics** : `register_success`, `register_failure` (motif).
- **Android/iOS** : suggestion de mot de passe fort iOS ; autofill Android.

### E03 — Mot de passe oublié / réinitialisation

- **Objectif** : demander un lien de réinitialisation ; poser un nouveau mot de passe depuis le lien e-mail.
- **Préconditions** : aucune ; le volet « réinitialisation » s'ouvre via universal link porteur du token (#566).
- **Données affichées** : étape 1 — champ email ; étape 2 — nouveau mot de passe.
- **Endpoints** : `POST /api/auth/password-reset/request` ; `POST /api/auth/password-reset/confirm`.
- **Actions** : envoyer la demande ; définir le nouveau mot de passe.
- **Validations** : email au format valide ; nouveau mot de passe ≥ 8 caractères.
- **États** : loading ; success requête = message neutre `202` (« Si un compte existe… ») — jamais de divulgation d'existence de compte ; error confirm : token invalide/expiré (`AUTH_RESET_TOKEN_INVALID`) → proposer une nouvelle demande ; success confirm → Connexion.
- **Navigation** : entrante — Connexion, universal link ; sortante — Connexion.
- **FR/AR/RTL** : standard.
- **Hors-ligne** : désactivé avec bandeau.
- **Analytics** : `password_reset_requested`, `password_reset_completed` (sans email).
- **Android/iOS** : gestion du lien entrant (App Links vs Universal Links) — cadrée en #566.

### E04 — Accueil

- **Objectif** : point d'entrée — scanner un QR, retrouver ses supérettes, reprendre une Kadhia en cours.
- **Préconditions** : aucune.
- **Données affichées** : CTA Scan QR, CTA recherche, supérettes récentes/favorites (connecté) ou suggestion de découverte (anonyme), bandeau « Kadhia en cours » si un brouillon actif existe (données serveur — l'équivalent PWA est inopérant en mode API réel, écart corrigé).
- **Endpoints** : connecté — `GET /api/me/stores`, `GET /api/me/kadhias?status=draft&page=1` ; anonyme — `GET /api/stores/search` (mise en avant).
- **Actions** : scanner ; chercher ; ouvrir une supérette ; reprendre la Kadhia.
- **Validations** : aucune.
- **États** : loading (skeleton) ; empty (aucune supérette connue → CTA scan/recherche) ; error (bandeau + retry, cache affiché si présent) ; success.
- **Navigation** : entrante — lancement d'app, tab bar ; sortante — E05 Scan, E07 Mes supérettes, E08 Fiche, E09 Catalogue, E11 Détail Kadhia, cloche → E16.
- **FR/AR/RTL** : ordre des cartes et chevrons inversés en AR.
- **Hors-ligne** : dernières supérettes en cache, bandeau hors-ligne.
- **Analytics** : `home_viewed`, `home_scan_tapped`.
- **Android/iOS** : néant.

### E05 — Scan QR magasin

- **Objectif** : scanner l'affiche QR d'une supérette (apport natif ; la PWA n'a que la saisie manuelle).
- **Préconditions** : permission caméra (demandée à la première ouverture).
- **Données affichées** : viseur caméra, aide, lien « Saisir le code manuellement » (repli PWA `stores/by-qr-scan`).
- **Endpoints** : aucun pendant le scan ; la résolution est faite par E06.
- **Actions** : scanner ; basculer en saisie manuelle (validation format `^[a-z0-9-]{1,100}$` avec message d'erreur explicite — écart PWA corrigé : la PWA échoue silencieusement) ; annuler.
- **Validations** : payload scanné conforme à une URL/token attendu, sinon cas #15 (lien malformé).
- **États** : loading (ouverture caméra) ; error (permission refusée → cas #13 ; scan illisible → aide) ; success → E06.
- **Navigation** : entrante — E04, E07 ; sortante — E06, retour.
- **FR/AR/RTL** : textes d'aide traduits ; viseur neutre.
- **Hors-ligne** : scan possible, résolution impossible → message réseau avec le token retenu pour retry.
- **Analytics** : `qr_scan_started`, `qr_scan_succeeded`, `qr_scan_manual_fallback`.
- **Android/iOS** : libellés et cinématiques de permission caméra différents ; lien vers réglages système propre à chaque OS.

### E06 — Ouverture supérette par QR ou lien (transitoire)

- **Objectif** : résoudre un token QR ou un universal link vers la supérette et son catalogue.
- **Préconditions** : token présent (scan, saisie manuelle ou lien).
- **Données affichées** : indicateur de chargement aux couleurs neutres.
- **Endpoints** : `GET /api/stores/by-qr/{qrCodeToken}` ; si connecté, `POST /api/me/stores/{storeId}/visit` (`{"source":"qr_code"}`, échec silencieux toléré comme en PWA).
- **Actions** : aucune (écran transitoire).
- **Validations** : aucune.
- **États** : loading ; error → cas #1 (QR inconnu) ou #2 (supérette inactive) avec re-scan/retour ; success → remplacement de pile vers E09.
- **Navigation** : entrante — E05, universal link, appareil photo système ; sortante — E09 Catalogue (replace), E04 en erreur.
- **FR/AR/RTL** : standard.
- **Hors-ligne** : cas #3 avec retry (token conservé).
- **Analytics** : `store_opened_via_qr` / `store_opened_via_link` (store_id).
- **Android/iOS** : App Links (vérification de domaine) vs Universal Links (AASA) — cadrés en #566.

### E07 — Mes supérettes / recherche

- **Objectif** : retrouver ses supérettes reconnues, en chercher de nouvelles, gérer favoris et retraits de liste.
- **Préconditions** : aucune (anonyme : recherche publique seule).
- **Données affichées** : cartes supérettes (nom, ville, favori, statut), favorites en tête ; champ de recherche.
- **Endpoints** : `GET /api/me/stores` (connecté) ; `GET /api/stores/search?query=&city=` ; `PATCH /api/me/stores/{storeId}/favorite` ; `DELETE /api/me/stores/{storeId}` (masquer).
- **Actions** : rechercher ; ouvrir une fiche ; épingler/dépingler un favori (optimiste avec rollback, comme en PWA) ; masquer ; pagination (« Afficher plus », 20 par page).
- **Validations** : requête de recherche ≥ 1 caractère.
- **États** : loading (skeleton) ; empty (« Aucune supérette — scannez un QR ») ; error + retry ; success.
- **Navigation** : entrante — E04, tab bar ; sortante — E08, E05 Scan.
- **FR/AR/RTL** : standard.
- **Hors-ligne** : dernière liste en cache lecture seule ; favori/masquage désactivés.
- **Analytics** : `store_search_performed` (nombre de résultats, sans requête), `store_favorited`.
- **Android/iOS** : néant.

### E08 — Fiche supérette

- **Objectif** : présenter la supérette et lancer ou reprendre une Kadhia.
- **Préconditions** : supérette existante.
- **Données affichées** : nom, adresse, ville, statut ouvert/fermé, horaires, prochain rendez-vous possible, mention « paiement sur place », thème visuel de la supérette.
- **Endpoints** : `GET /api/stores/{storeId}` ; `GET /api/stores/{storeId}/theme` ; `GET /api/stores/{storeId}/opening-hours` ; si connecté `GET /api/me/kadhias?status=draft&store_id={storeId}` (adapte le CTA : Commencer / Continuer / Reprendre — comme `StartKadhiaCta` PWA).
- **Actions** : CTA principal vers le catalogue (0–1 brouillon) ou vers Mes Kadhias (≥ 2 brouillons) ; favori.
- **Validations** : aucune.
- **États** : loading ; error 404 → « Supérette introuvable » ; supérette inactive → cas #2 ; success.
- **Navigation** : entrante — E04, E06, E07 ; sortante — E09, E10.
- **FR/AR/RTL** : horaires et adresses formatés localement.
- **Hors-ligne** : fiche en cache lecture seule.
- **Analytics** : `store_detail_viewed` (store_id).
- **Android/iOS** : néant.

### E09 — Catalogue

- **Objectif** : parcourir le catalogue public et alimenter la Kadhia active.
- **Préconditions** : consultation libre ; ajout réservé au client connecté avec Kadhia active.
- **Données affichées** : grille produits (nom FR/AR, marque, format, prix TND, disponibilité, image responsive S13-005 ou placeholder catégorie), pastilles catégories, recherche, panneau/badge Kadhia (nombre d'articles, total).
- **Endpoints** : `GET /api/stores/{storeId}/catalog` (+ `?query=`, `?category=`, pagination) ; `GET /api/me/kadhias?status=draft&store_id={storeId}` (Kadhia active — la PWA passe par la variante `GET /api/me/stores/{storeId}/kadhias`, exposée mais non documentée au contrat, à unifier en #565) ; `POST /api/me/stores/{storeId}/kadhias` (création explicite) ; `PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}` ; `DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}`.
- **Actions** : recherche ; filtre catégorie ; +/- quantité ; « Commencer ma Kadhia » ; sélecteur multi-Kadhia (choisir un brouillon ou en créer un) ; ouvrir la Kadhia.
- **Validations** : quantité ≥ 1 ; ajout bloqué si produit indisponible ; anonyme → Connexion avec retour (cas standard §2).
- **États** : loading (skeleton) ; empty (3 variantes : catalogue vide / recherche sans résultat / catégorie vide) ; error + retry ; erreurs d'ajout non bloquantes (toast) ; success.
- **Navigation** : entrante — E06, E08 ; sortante — E11 Détail Kadhia, E08 retour. Pas de détail produit en V1 (§1.3).
- **FR/AR/RTL** : `name_ar` et `category_ar` affichés quand la langue est AR et le champ renseigné, sinon repli FR (écart PWA corrigé : la PWA ignore `labelAr`) ; grille miroir.
- **Hors-ligne** : dernier catalogue consulté en cache lecture seule, bandeau « non actualisé » ; ajout désactivé.
- **Analytics** : `catalog_viewed` (store_id), `catalog_search` (nombre de résultats, sans requête), `product_added` (store_id, sans identité produit ni prix).
- **Android/iOS** : néant.

### E10 — Mes Kadhias

- **Objectif** : lister brouillons et Kadhias envoyées (multi-Kadhia).
- **Préconditions** : connecté (sinon Connexion avec retour).
- **Données affichées** : deux onglets Brouillons / Envoyées ; cartes (titre = note ou nom de supérette, nombre d'articles, total TND, badge statut, date relative).
- **Endpoints** : `GET /api/me/kadhias?status=draft|submitted&page=N` (filtre `store_id` disponible).
- **Actions** : ouvrir (Continuer / Voir) ; pagination ; CTA « Trouver une supérette » si vide.
- **Validations** : aucune.
- **États** : loading ; empty par onglet ; error + retry ; success.
- **Navigation** : entrante — tab bar, E08 (≥ 2 brouillons) ; sortante — E11, E07.
- **FR/AR/RTL** : badges et dates localisés.
- **Hors-ligne** : dernière liste en cache lecture seule.
- **Analytics** : `kadhia_list_viewed` (nombre de brouillons).
- **Android/iOS** : néant.

### E11 — Détail Kadhia

- **Objectif** : consulter et éditer une Kadhia draft (lignes, note), la supprimer, poursuivre vers le rendez-vous.
- **Préconditions** : connecté, Kadhia appartenant au client.
- **Données affichées** : lignes (produit, quantité, prix unitaire snapshoté, total ligne), total TND, note client, bandeau acceptation partielle le cas échéant (motif + lignes refusées), lecture seule si non-draft.
- **Endpoints** : `GET /api/me/kadhias/{kadhiaId}` ; `PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}` ; `DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}` ; `PATCH /api/me/kadhias/{kadhiaId}` (note ≤ 500 caractères) ; suppression de la Kadhia — endpoint utilisé par la PWA, à confirmer au contrat en #565.
- **Actions** : +/- quantité ; retirer une ligne ; éditer la note ; supprimer la Kadhia (confirmation) ; « Ajouter d'autres articles » ; « Choisir un rendez-vous ».
- **Validations** : note ≤ 500 caractères ; édition refusée si non-draft (422 → message « déjà envoyée », re-GET).
- **États** : loading ; empty (0 ligne → CTA catalogue) ; error (introuvable → retour liste ; erreurs de ligne en toast) ; success.
- **Navigation** : entrante — E09, E10, E14 (acceptation partielle), bandeau Accueil ; sortante — E12 (en portant `kadhiaId`), E09, E10.
- **FR/AR/RTL** : montants et compteurs localisés.
- **Hors-ligne** : lecture seule du dernier état ; toute écriture désactivée (aucune écriture hors-ligne en V1, §6).
- **Analytics** : `kadhia_viewed` (nombre de lignes — jamais le contenu), `kadhia_deleted`.
- **Android/iOS** : dialogues de confirmation natifs respectifs.

### E12 — Rendez-vous et soumission

- **Objectif** : choisir le rendez-vous de retrait, ajouter la note au marchand, soumettre la Kadhia.
- **Préconditions** : connecté ; Kadhia draft non vide, identifiée par la navigation (`kadhiaId` explicite — corrige la dépendance PWA au contexte local).
- **Données affichées** : jours (Aujourd'hui / Demain / Après-demain), rendez-vous groupés matin/midi/après-midi/soir (`Africa/Tunis`), délai minimal de préparation (`booking_policy.minimum_pickup_lead_time_minutes`, `earliest_bookable_at`), récapitulatif (articles, total TND), zone de note.
- **Endpoints** : `GET /api/stores/{storeId}/pickup-slots?from=today&available=true` ; `POST /api/me/kadhias/{kadhiaId}/submit` (`{pickup_slot_id, notes}`).
- **Actions** : choisir un jour puis un rendez-vous ; saisir la note ; « Envoyer ma Kadhia » (verrou anti double tap) ; retour au catalogue.
- **Validations** : un rendez-vous sélectionné obligatoire ; Kadhia non vide.
- **États** : loading ; empty (« Aucun rendez-vous disponible », variante mentionnant le délai minimal) ; error — mapping des codes backend : `PICKUP_SLOT_FULL`, `PICKUP_SLOT_EXPIRED`, `PICKUP_SLOT_NOT_FOUND`, `PICKUP_SLOT_MINIMUM_LEAD_TIME_NOT_MET` (rechargement + focus liste), `KADHIA_EMPTY`, `KADHIA_NOT_FOUND`, `PRODUCT_UNAVAILABLE` (retour à E11), `STORE_SUSPENDED_FOR_SUBSCRIPTION` (cas #2) ; success → E14 (replace).
- **Navigation** : entrante — E11 ; sortante — E14 Suivi de commande, E11, E09.
- **FR/AR/RTL** : créneaux horaires et groupes de journée localisés.
- **Hors-ligne** : écran inaccessible (soumission = validation serveur obligatoire) ; bandeau réseau.
- **Analytics** : `slot_selected` (jour relatif, sans horaire précis), `kadhia_submitted` (store_id, nombre de lignes — jamais le contenu ni le total).
- **Android/iOS** : néant.

### E13 — Mes commandes

- **Objectif** : historique des commandes du client.
- **Préconditions** : connecté (sinon Connexion avec retour).
- **Données affichées** : cartes (code court, badge statut, supérette, total TND, date), mention « Action requise » sur `partially_accepted`.
- **Endpoints** : `GET /api/me/orders?page=&limit=`.
- **Actions** : ouvrir une commande ; pagination ; retry.
- **Validations** : aucune.
- **États** : loading ; empty (« Aucune commande — commencez une Kadhia ») ; error + retry ; success.
- **Navigation** : entrante — tab bar, E12 (après soumission), E16 ; sortante — E14.
- **FR/AR/RTL** : libellés de statut FR/AR (le backend fournit `status_label_fr` / `status_label_ar` sur le suivi ; sur la liste, mapping i18n local des statuts).
- **Hors-ligne** : historique récemment chargé consultable en lecture seule (§6).
- **Analytics** : `orders_list_viewed`.
- **Android/iOS** : néant.

### E14 — Suivi de commande

- **Objectif** : suivre une commande, l'annuler si `submitted`, réagir à une acceptation partielle, accéder au retrait.
- **Préconditions** : connecté, commande appartenant au client.
- **Données affichées** : badge statut, timeline des statuts, rendez-vous (date/heure), lignes et totaux TND, note client, motif marchand (`rejection_reason`), lignes refusées (`rejected_lines`) si acceptation partielle, **code de retrait à 4 chiffres** (`pickup_code`) quand `ready`.
- **Endpoints** : `GET /api/me/orders/{id}` ; `GET /api/me/orders/{orderId}/status` (polling léger sur les statuts actifs) ; `GET /api/me/orders/{orderId}/status-history` (timeline) ; `POST /api/me/orders/{orderId}/cancel`.
- **Actions** : rafraîchir ; annuler (si `submitted`, confirmation ; 409 → cas #11) ; « Modifier ma Kadhia » (si `partially_accepted`) ; « Afficher le QR de retrait » (si `ready` / `pickup_pending`).
- **Validations** : annulation limitée à `submitted` (règle serveur revalidée).
- **États** : loading ; error (introuvable → retour liste, cas #11 en conflit) ; success ; états finaux `completed` / `cancelled` sans CTA (parcours 3.8).
- **Navigation** : entrante — E13, E12, E16, notification push, deep link ; sortante — E15 Retrait, E11 (contexte acceptation partielle), E13.
- **FR/AR/RTL** : timeline miroir en AR ; libellés statut AR fournis par l'API de suivi.
- **Hors-ligne** : dernier état connu en lecture seule avec bandeau « non actualisé » ; actions désactivées.
- **Analytics** : `order_viewed` (statut), `order_cancelled`, `partial_acceptance_resumed`.
- **Android/iOS** : néant.

### E15 — Retrait

- **Objectif** : présenter le QR de retrait, suivre le scan marchand, confirmer la remise (double validation).
- **Préconditions** : connecté ; commande `ready` ou `pickup_pending` (sinon renvoi vers E14).
- **Données affichées** : QR (`qr_payload`), rappel du code à 4 chiffres en secours, supérette + adresse, rendez-vous, états de session (scannée / confirmée marchand / en attente de ma confirmation / terminée / expirée), luminosité de l'écran augmentée pendant l'affichage du QR.
- **Endpoints** : `GET /api/me/orders/{orderId}/pickup-session` ; `GET /api/me/orders/{orderId}/status` (polling ~4 s, comme en PWA) ; `PATCH /api/me/pickup-sessions/{id}/confirm`.
- **Actions** : « J'ai bien reçu ma commande » (confirmation client) ; retry.
- **Validations** : confirmation possible uniquement après confirmation marchand (`PICKUP_SESSION_NOT_MERCHANT_CONFIRMED` → message d'attente) ; erreurs 409 mappées (cas #12).
- **États** : loading ; error (session indisponible + retry ; expirée/déjà utilisée → cas #12) ; success (commande `completed` → écran de confirmation puis retour E14).
- **Navigation** : entrante — E14 ; sortante — E14, E13.
- **FR/AR/RTL** : instructions localisées ; QR neutre.
- **Hors-ligne** : le QR déjà chargé reste affiché (le marchand scanne avec son appareil connecté) ; la confirmation client requiert le réseau — message explicite.
- **Analytics** : `pickup_qr_displayed`, `pickup_confirmed_by_customer`.
- **Android/iOS** : API de luminosité écran différentes ; keep-awake pendant l'affichage.

### E16 — Notifications

- **Objectif** : centre de notifications in-app (source de vérité), relais des push.
- **Préconditions** : connecté (sinon Connexion avec retour).
- **Données affichées** : liste (titre, corps, heure relative, pastille non-lu), badge global.
- **Endpoints** : `GET /api/me/notifications?page=&unread=` ; `PATCH /api/me/notifications/{id}/read` ; `PATCH /api/me/notifications/read-all` ; enregistrement push natif — écart API §1.4 à cadrer en #565/#566 (`POST /api/me/push-subscriptions` est aujourd'hui au format Web Push).
- **Actions** : ouvrir une notification (marquée lue → commande liée) ; « Tout marquer lu » ; pagination.
- **Validations** : aucune.
- **États** : loading ; empty (« Aucune notification ») ; error + retry ; success.
- **Navigation** : entrante — cloche (toutes surfaces), tap sur un push ; sortante — E14.
- **FR/AR/RTL** : le backend n'expose que `title_fr` / `body_fr` — repli FR en AR, **point ouvert §8** ; heures relatives localisées (écart PWA corrigé : FR en dur).
- **Hors-ligne** : dernière page en cache lecture seule ; marquage lu désactivé.
- **Analytics** : `notification_opened` (type d'événement, sans contenu), `push_optin_accepted` / `push_optin_declined`.
- **Android/iOS** : canaux de notification Android (importance) vs catégories iOS ; opt-in explicite iOS, Android 13+ permission runtime.

### E17 — Profil et compte

- **Objectif** : gérer identité, langue, session et suppression du compte.
- **Préconditions** : connecté (sinon invitation Connexion / Inscription).
- **Données affichées** : avatar initiales, nom, email ; sélecteur de langue FR/AR ; version de l'app ; liens Commandes / Kadhias.
- **Endpoints** : `GET /api/me/profile` ; `PATCH /api/me/profile` (`first_name`, `last_name`) ; `DELETE /api/me/account`.
- **Actions** : modifier prénom/nom ; changer de langue (bascule RTL immédiate) ; se déconnecter (purge du token sécurisé) ; supprimer le compte (double confirmation avec saisie du mot « SUPPRIMER », puis déconnexion → Accueil).
- **Validations** : prénom et nom requis, ≤ 100 caractères.
- **États** : loading ; error (échec PATCH/DELETE avec message) ; success (confirmation visuelle).
- **Navigation** : entrante — tab bar ; sortante — E13, E10, Connexion (après déconnexion), Accueil (après suppression).
- **FR/AR/RTL** : la bascule de langue relaie `I18nManager` (peut requérir un redémarrage de l'app — comportement à documenter à l'usage).
- **Hors-ligne** : lecture seule ; modifications désactivées.
- **Analytics** : `language_changed` (fr|ar), `account_deleted`, `logout`.
- **Android/iOS** : suppression de compte = exigence des deux stores ; formulations de confirmation conformes aux guidelines respectives.

### E18 — Mise à jour obligatoire

- **Objectif** : bloquer une version d'app sous le seuil de version API minimale (ADR-0007).
- **Préconditions** : le contrôle de version au démarrage détecte une version insuffisante (endpoint public de version minimale — à définir en #565).
- **Données affichées** : message explicatif, bouton vers le store.
- **Endpoints** : endpoint public de version minimale (écart API assumé, #565).
- **Actions** : ouvrir Google Play / App Store.
- **Validations** : aucune ; écran non contournable.
- **États** : un seul état ; si le contrôle échoue (réseau), l'app continue et re-vérifie plus tard (le contrôle ne doit jamais bloquer hors-ligne).
- **Navigation** : entrante — démarrage ; sortante — store externe.
- **FR/AR/RTL** : standard.
- **Hors-ligne** : jamais affiché sur simple échec réseau.
- **Analytics** : `force_update_shown`.
- **Android/iOS** : liens store distincts (Play Store / App Store).

---

## 6. Politique cache / hors-ligne V1

Politique **minimaliste et honnête** — pas de moteur de synchronisation :

- **Lecture seule en cache** : dernier catalogue consulté par supérette, fiches
  supérettes, Mes Kadhias / détail Kadhia (dernier état), historique et détails
  de commandes récemment chargés, dernière page de notifications. Tout contenu
  servi depuis le cache porte un bandeau « données non actualisées ».
- **Aucune écriture hors-ligne** : pas de file d'attente locale. Ajout de ligne,
  note, soumission, annulation, confirmation de retrait et modifications de
  profil sont désactivés hors réseau, avec message explicite. Aucune soumission
  sans validation serveur.
- **Re-validation au retour réseau** : les écrans visibles re-fetchent leurs
  données à la reconnexion (et au retour au premier plan) ; prix, disponibilité
  et rendez-vous sont **toujours** revérifiés par l'API — jamais servis du cache
  pour une décision d'achat.
- **Conflits signalés, jamais écrasés** : un 409 ou un état serveur différent
  déclenche un re-GET et l'affichage de l'état serveur (cas #11).
- **Exception d'usage** : le QR de retrait déjà chargé reste affiché hors-ligne
  (le scan est fait par l'appareil du marchand) ; la confirmation client, elle,
  exige le réseau.
- Implémentation indicative (ADR-0007) : TanStack Query + persistance légère ;
  le JWT reste dans le stockage sécurisé, jamais dans le cache de données.

---

## 7. Analytics

Événements **non sensibles uniquement** : jamais de PII (email, nom, téléphone),
jamais de contenu de Kadhia (libellés produits, notes libres), jamais de montant
individuel, jamais de token. Identifiants techniques admis : `store_id`,
statut de commande, compteurs. Anonymisation et opt-out : décision humaine §8.

| Événement | Propriétés (non sensibles) |
|---|---|
| `app_opened` | source (direct, push, deep_link, qr) |
| `login_success` / `login_failure` | motif d'échec (invalid, ratelimited, network) |
| `register_success` / `register_failure` | motif |
| `password_reset_requested` / `password_reset_completed` | — |
| `qr_scan_started` / `qr_scan_succeeded` / `qr_scan_manual_fallback` | — |
| `store_opened_via_qr` / `store_opened_via_link` | store_id |
| `store_search_performed` | nombre de résultats |
| `store_detail_viewed` / `catalog_viewed` | store_id |
| `catalog_search` | nombre de résultats |
| `product_added` | store_id (sans identité produit ni prix) |
| `kadhia_list_viewed` | nombre de brouillons |
| `kadhia_viewed` / `kadhia_deleted` | nombre de lignes |
| `slot_selected` | jour relatif (today/tomorrow/later) |
| `kadhia_submitted` | store_id, nombre de lignes |
| `order_viewed` | statut |
| `order_cancelled` / `partial_acceptance_resumed` | — |
| `pickup_qr_displayed` / `pickup_confirmed_by_customer` | — |
| `notification_opened` | type d'événement métier |
| `push_optin_accepted` / `push_optin_declined` | — |
| `language_changed` | fr \| ar |
| `logout` / `account_deleted` | — |
| `force_update_shown` | — |
| `error_shown` | clé i18n de l'erreur, code HTTP (sans request_id ni payload) |

---

## 8. Dépendances et points ouverts

### Dépendances de cadrage

- **#565 (MOBILE-004 — audit API, auth, sessions)** : politique de session mobile
  (JWT 1h sans refresh : reconnexion vs refresh token) ; endpoint public de
  version API minimale (E18) ; confirmation au contrat des endpoints utilisés
  par la PWA mais absents de `docs/architecture/api-contract.md` — suppression
  de Kadhia, `GET /api/me/kadhias/{kadhiaId}/replacements`,
  `GET /api/me/stores/{storeId}/favorite-products`,
  `GET /api/me/stores/{storeId}/suggestions`,
  `PATCH /api/me/products/{merchantProductId}/favorite`,
  `POST /api/me/kadhias/{kadhiaId}/share-links`,
  `POST /api/me/kadhia-share-links/{token}/join`,
  `POST /api/me/orders/{orderId}/whatsapp-contact` (tous présents dans
  `apps/backend/src/ApiResource/`) ; format d'enregistrement push natif
  (FCM/APNs) vs `POST /api/me/push-subscriptions` actuel (Web Push) ;
  convention `request_id` sur les erreurs 5xx.
- **#566 (MOBILE-005 — push natif, QR, App/Universal Links)** : schéma des liens
  (QR magasin, lien de réinitialisation `portal`, notifications), configuration
  App Links / Universal Links, canaux/catégories de notification, deep link →
  écran cible avec reprise après login.
- **#567 (MOBILE-006 — sécurité, stores, QA, publication)** : comptes stores et
  identifiants définitifs (`tn.kadhia.client` — hypothèse ADR-0007), politique
  E2E (Maestro vs Detox), checklist de publication (dont suppression de compte
  in-app).

### Décisions humaines restantes

1. **Marque « Kadhia »** pour le nom d'app et les identifiants stores (hypothèse ADR-0007).
2. **Session mobile** : accepter la reconnexion horaire ou introduire un refresh token (arbitrage sécurité/UX, #565).
3. **Notifications en arabe** : le backend n'expose que `title_fr`/`body_fr` — accepter le repli FR en V1 ou ajouter `title_ar`/`body_ar` côté backend (écart produit, hors périmètre de cette issue).
4. **Outil analytics** et politique de consentement/opt-out (aucune donnée personnelle dans les événements, mais le choix de l'outil et l'hébergement restent à acter).
5. **Suggestions de remplacement, favoris produits, partage de Kadhia** : confirmer leur classement post-V1 ou les repêcher en V1 si l'audit #565 stabilise leurs contrats.
6. **Ordre de lancement** : ADR-0005 maintient Android marchand avant Android client — le backlog issu de ce cadrage est découpé par capacité, pas par plateforme.
