# Sécurité, stores, QA et stratégie de publication mobile (MOBILE-006, #567)

> Cadrage documentaire. **Aucun code, aucun compte créé, aucun secret, certificat ni
> credential dans ce dépôt.** Ce document clôt la chaîne de cadrage mobile
> (#562 ADR-0007 → #563 client → #564 marchand → #565 audit API → #566 push/liens)
> et consolide les décisions transversales exigées avant tout bootstrap du dépôt
> `click-and-collect-mobile`. Les gardes backend citées ont été vérifiées par
> l'audit #565 (`docs/mobile/api-readiness.md`) ; les gaps backend sont portés par
> **#616** (refresh token + révocation), **#617** (request_id), **#618** (version
> minimale / maintenance), **#620** (mobile_device + push natif) et **#622**
> (rate limiting, créée par ce cadrage).
>
> Référentiel de sécurité : **OWASP MASVS v2, niveau visé L1** (plus les contrôles
> MASA pertinents pour la revue Google Play). C'est une **cible d'auto-évaluation**,
> pas une certification : aucun audit MASA ni test d'intrusion n'a été réalisé, et
> ce document ne le prétend nulle part.

---

## 1. Modèle de menace

Portée : les deux apps (Client, Marchand), Android et iOS, plus leurs interactions
avec le backend Symfony (source de vérité unique — `.claude/rules/security.md`).

Gardes backend **existantes** invoquées dans la colonne « Mitigations » (toutes
vérifiées par #565) :

- **G-retrait** : retrait sécurisé par double validation client + marchand ;
  token de `PickupSession` opaque UUID v4 unique, usage unique, **TTL 24 h** ;
  scan idempotent ; force-complete verrouillé 300 s avec note obligatoire.
- **G-ownership** : `MerchantShopAccessChecker::denyUnlessMerchantOwnsShop()` —
  **membership relue à chaque requête** (403 immédiat après révocation) ; côté
  client, filtrage par utilisateur courant (404 sans fuite).
- **G-état** : transitions de commande gardées par statut (409 stables) ; prix
  snapshotés à la ligne de Kadhia ; capacité de créneau par UPDATE atomique.
- **G-opacité** : `qrCodeToken` et token de session opaques, jamais d'ID interne
  dans les routes publiques ; payload push minimal (`type`, `notification_id`,
  `order_id`, `route` — #566 §6).

| # | Menace | Scénario concret Kadhia | Impact | Mitigations existantes | Mitigations à venir | Résiduel accepté |
|---|---|---|---|---|---|---|
| 1 | Vol ou perte du téléphone | Le téléphone d'un marchand, resté connecté, est volé ; le voleur ouvre l'app et voit les commandes du jour | Lecture des commandes/coordonnées clients actives ; actions marchand possibles | Verrouillage OS (hors de notre contrôle) ; JWT 1 h ; G-ownership ; G-retrait (le voleur ne peut pas finaliser sans le client) | #616 : révocation des refresh tokens par reset de mot de passe = révocation globale V1 ; post-V1 : liste/révocation par appareil (`refresh_tokens` + `mobile_device` #620) | Fenêtre ≤ 1 h d'accès en lecture si l'appareil est déverrouillé ; actée (MASVS-AUTH) |
| 2 | Extraction du stockage local | Un appareil client est branché à un poste ; on tente de lire les tokens et le cache | Session rejouée ; lecture du cache (catalogue, commandes) | PWA : localStorage (hors périmètre mobile) ; backend sans état côté app | Tokens **exclusivement** en expo-secure-store (Keychain/Keystore, §2.1) ; cache TanStack Query sans token ni donnée sensible persistée (§2.7) | Cache métier en clair (libellés produits, statuts) lisible sur appareil compromis : donnée de faible sensibilité, accepté |
| 3 | Interception réseau | Wi-Fi public d'un café à Tunis ; un attaquant en position MITM écoute le trafic de l'app client | Vol de JWT, lecture des commandes | TLS obligatoire de fait (JWT Bearer) ; ATS iOS et `cleartextTrafficPermitted=false` Android interdisent l'HTTP | Aucun pinning V1 (décision §2.3) ; réévaluation post-bêta | MITM par CA compromise ou proxy racine installé sur l'appareil : hors modèle V1 (MASVS-NETWORK-1 satisfait, -2 non revendiqué) |
| 4 | Token JWT/refresh compromis | Un JWT client fuit (log tiers, appareil compromis) ; l'attaquant appelle l'API | Usurpation du compte pendant la durée de vie du token | JWT RS256 stateless TTL 1 h (ADR-0003) ; G-ownership à chaque requête ; un JWT client ne donne aucun accès marchand/admin (rôles séparés) | #616 : refresh opaque persisté, **rotation à usage unique** (réutilisation → révocation de chaîne = détection de vol), révocation au logout/reset/suspension/suppression | JWT résiduel ≤ 1 h après révocation : acté par #616 comme acceptable MVP |
| 5 | Deep link malveillant | Un lien `https://<domaine>/orders/{uuid-deviné}` est envoyé à un client ; ou un site tiers forge `/merchant/orders/...` | Ouverture d'écran non légitime, phishing de contexte | Principe #566 §1 : **un lien n'autorise rien** — le backend revalide auth, rôle, ownership, état à chaque requête ; 404 sans fuite ; motifs de chemins disjoints client/marchand (App Links/AASA) | Domaine canonique + `assetlinks.json`/AASA vérifiés par OS (bloqués par la décision domaine, §3.4) | Un lien peut toujours ouvrir l'app sur un écran d'erreur propre : comportement voulu |
| 6 | QR rejoué ou falsifié | Un client photographie le QR de retrait d'un autre ; ou un faux QR magasin est collé sur la vitrine | Retrait frauduleux ; redirection vers un faux magasin | **G-retrait** : le QR de retrait ne finalise rien seul (double validation, usage unique, TTL 24 h, revalidation serveur complète au scan — #566 §7.6) ; QR magasin : token opaque, résolution serveur, scan interne limité au domaine canonique (#566 §7.5) | Régénération admin du token magasin (S5-005) en cas de compromission, avec réimpression | Un faux QR **externe** (photo système) pointant hors domaine ouvre le navigateur, pas l'app : risque de phishing web générique, hors périmètre app |
| 7 | Appareil rooté / jailbreaké | Un marchand utilise un Android rooté d'occasion ; le Keystore est affaibli | Extraction possible des tokens malgré le secure store | Aucune donnée d'un tiers sur l'appareil au-delà du périmètre du compte connecté ; G-ownership côté serveur | **Pas de blocage V1** (décision §2.5) : détection best-effort informative si triviale à activer, jamais bloquante | Compromission locale d'un appareil rooté : assumée — la défense est serveur (MASVS-RESILIENCE non revendiqué au-delà de L1) |
| 8 | Binaire modifié / repackagé | Une APK « Kadhia » modifiée circule hors Play Store avec un endpoint attaquant | Vol d'identifiants à la connexion | Distribution officielle uniquement (Play/App Store) ; Play App Signing + signature iOS : un binaire modifié n'a pas notre signature ; aucun secret dans le binaire à extraire (§2.6) | R8/minification Android par défaut (EAS) — obfuscation légère, pas de RASP ni d'anti-tamper V1 (§2.4) | Le sideload Android d'une APK falsifiée reste possible pour un utilisateur qui l'installe volontairement : accepté, communication « app officielle » en bêta terrain |
| 9 | Secret inclus dans l'application | Une clé serveur serait embarquée par erreur dans le bundle JS | Compromission d'un service backend | Clés RS256 privées côté serveur uniquement (`config/jwt/`, gitignoré) ; l'API mobile ne requiert **aucune** API key (#565 §2.14) | Règle §2.6 : Expo public env only + scan de secrets en CI (gate G2) | URL d'API et clés **publiques** (Sentry DSN, projectId EAS) visibles dans le binaire : par nature publiques, accepté |
| 10 | Logs contenant des données sensibles | Un crash pendant la soumission logue le payload avec la note client et le token | Fuite PII/tokens vers logs locaux ou Sentry | Backend : `ClientLogProcessor` masque déjà les tokens (dont `refresh_token`) ; canaux Monolog dédiés | Politique de rédaction §2.8 : jamais de token/PII/contenu Kadhia dans les logs app ni les breadcrumbs Sentry ; revue des scrubbers au gate G3 | Métadonnées techniques (route, code erreur, versions) journalisées : nécessaires au support, non sensibles |
| 11 | Capture d'écran / notification sur écran verrouillé | Le QR de retrait est capturé et partagé ; un push s'affiche sur l'écran verrouillé d'un téléphone posé sur un comptoir | Rejeu du QR ; lecture d'infos de commande par un tiers | Push : **payload minimal déjà acté** (#566 §6 — jamais de contenu Kadhia, montant, coordonnée, token, code) ; G-retrait : une capture du QR ne donne que le retrait de **sa propre** commande | §2.10 : captures autorisées partout **sauf** écran QR de retrait client (`FLAG_SECURE` / équivalent Expo) | Le titre du push (« Votre commande est prête ») visible sur écran verrouillé : information non sensible, assumée |
| 12 | Application ancienne incompatible | Un client garde 6 mois une vieille version dont les contrats d'erreur ont changé | Comportements faux, écrans cassés, tickets support | Évolutions backend additives (politique implicite, #565 §6) ; type de push inconnu → liste des notifications, jamais de crash (#566 §7.4) | **#618** : `GET /api/mobile/config` (version minimale + maintenance) + écrans E18 client / E17 marchand de mise à jour obligatoire (§2.12) | Entre la rupture et le seuil relevé, une version obsolète peut mal se comporter : fenêtre courte pilotée par le rollout |
| 13 | Dépendance / SDK tiers compromis | Un paquet npm de la chaîne RN est compromis (typosquatting, post-install) | Exfiltration depuis l'app ou la CI | Périmètre SDK volontairement minimal (Expo, Sentry — §6) ; aucune donnée personnelle envoyée aux SDK (§6.4) | §2.11 : lockfile committé, versions épinglées, `npm audit`/scan en CI (gate G2), politique ADR-0007 « Expo SDK courant ou n-1 », toute lib native hors config plugin justifiée par écrit | Compromission d'une dépendance de confiance avant détection publique : risque supply chain irréductible, mitigé par le lockfile et la surface minimale |
| 14 | Abus d'API automatisé | Un script force en boucle `POST /api/auth/login` ou énumère `GET /api/stores/by-qr/{token}` | Credential stuffing, énumération, saturation | Tokens opaques à grand espace (G-opacité) ; 202 neutre sur password-reset (pas de divulgation d'existence de compte) | **#622** (créée par ce cadrage) : `login_throttling` + rate limiting sur register/reset/client-logs/by-qr — **P1, bloquant gate G4 (bêta publique)** | Jusqu'à #622 : endpoints publics non limités, accepté en interne/staging uniquement |
| 15 | Changement de compte sur un appareil partagé | Deux employés d'une supérette partagent une tablette ; l'un se déconnecte, l'autre se connecte | Le second voit des restes de session du premier (cache, push) | Push : upsert du device par token — l'appareil suit son utilisateur courant, jamais deux comptes (#566 §3 décision 2) ; comptes marchands individuels (MERCHANT-TEAM) — le partage de compte est contraire à la politique | §2.9 : logout = purge locale complète (secure store, caches TanStack Query, état de navigation) + révocation du refresh token (#616) + `DELETE /api/mobile/devices/{id}` best-effort (#620) | Logout hors ligne : la révocation serveur part au retour réseau ; purge locale immédiate dans tous les cas |
| 16 | Restauration d'une sauvegarde contenant une ancienne session | Un client restaure son iPhone depuis une sauvegarde d'il y a 3 mois ; un vieux refresh token réapparaît | Résurrection d'une session révoquée | — | #616 : rotation à usage unique — un token déjà consommé → 401 + révocation de chaîne → re-login ; §2.13 : entrée secure store **exclue des sauvegardes** (`kSecAttrAccessibleWhenUnlockedThisDeviceOnly` ; Keystore Android non exportable par conception, app exclue de l'auto-backup pour ses données) | Restauration sur le **même** appareil d'un refresh jamais consommé et non expiré : fenêtre théorique fermée par l'exclusion de backup |

### Checklist MASVS (auto-évaluation cible, sans certification)

| Domaine MASVS | Cible | Couverture |
|---|---|---|
| MASVS-STORAGE | L1 | §2.1, §2.7, §2.13 (secure store, pas de token hors Keychain/Keystore, exclusion backup) |
| MASVS-CRYPTO | L1 | Aucune crypto maison : TLS + Keychain/Keystore OS ; JWT vérifié serveur |
| MASVS-AUTH | L1 | #616 (refresh, rotation, révocation) ; gardes serveur à chaque requête |
| MASVS-NETWORK | L1 | §2.2–2.3 (TLS strict, pas de cleartext ; pinning non revendiqué) |
| MASVS-PLATFORM | L1 | #566 (liens/QR n'autorisent rien, payload push minimal), §2.10 (FLAG_SECURE ciblé) |
| MASVS-CODE | L1 | §2.11 (dépendances), §2.6 (pas de secret), R8 par défaut |
| MASVS-RESILIENCE | non visé | Position honnête §2.4–2.5 : pas de RASP, root non bloquant |
| MASVS-PRIVACY / MASA | pertinent | §6.4 (minimisation), matrice données (issue §3, à finaliser avec les fiches stores), Data safety Play / Privacy Nutrition Labels alimentés par ce document |

---

## 2. Décisions de sécurité obligatoires

Toutes les cases de l'issue, tranchées. Ce qui dépend d'un gap backend référence
son issue ; rien ici n'exige de nouveau développement backend au-delà de
#616/#617/#618/#620/#622.

| # | Sujet | Décision |
|---|---|---|
| 2.1 | **Stockage sécurisé** | **expo-secure-store** (Keychain iOS / Keystore Android) pour JWT + refresh token, dans le package `@kadhia/auth` — conforme ADR-0007 et #565 §2.12. Aucune alternative. |
| 2.2 | **Aucun token en clair** | Interdit dans AsyncStorage, les préférences, le cache TanStack Query, les logs, les breadcrumbs Sentry et les événements analytics (règle déjà actée #565 §2.13). Vérifié par revue au gate G3. |
| 2.3 | **TLS / pinning** | HTTPS strict : ATS iOS sans exception, `usesCleartextTraffic=false` Android, aucune exception cleartext même en dev (les environnements de dev sont servis en HTTPS — ngrok/staging). **Pas de certificate pinning V1** : le bénéfice (MITM par CA compromise) ne justifie pas le risque opérationnel — un renouvellement de certificat mal épinglé **bricke** les deux apps chez tous les utilisateurs, sans équipe d'astreinte pour gérer une rotation d'épingle. Réévaluation **post-bêta** (gate G5 passé), avec pinning sur clé publique de backup si adopté. |
| 2.4 | **Obfuscation / anti-tamper** | Position honnête : **R8/minification Android activés par défaut** dans les builds EAS de production — c'est de la réduction de taille et une obfuscation légère, pas une protection. **Aucun RASP, aucun anti-tamper, aucun packer V1** : le bundle JS reste analysable ; la sécurité ne repose jamais sur le secret du client (toutes les gardes sont serveur). Ne rien promettre de plus dans les fiches stores. |
| 2.5 | **Root / jailbreak** | **Détection non bloquante V1.** Justification : (a) toutes les décisions critiques sont revalidées serveur (G-ownership, G-retrait, G-état) ; (b) le marché tunisien compte des appareils Android d'occasion/rootés côté marchand — bloquer exclurait des utilisateurs légitimes sans arrêter un attaquant outillé (bypass trivial) ; (c) aucune promesse absolue possible (exigence de l'issue). Si `expo-device`/équivalent expose l'info sans coût : log technique non bloquant, pour mesurer avant de décider plus. |
| 2.6 | **Secrets** | **Aucun secret serveur dans le binaire** : le mobile n'embarque que des valeurs publiques (URL API par profil EAS, `projectId` Expo, DSN Sentry). Clés RS256, credentials FCM/APNs, secrets backend : jamais côté app (FCM/APNs déposés dans EAS, le backend n'en détient aucun — #566 §4.1). Variables **Expo public env only** (`EXPO_PUBLIC_*`), profils EAS par environnement, jamais de secret dans le dépôt (ADR-0007). Scan de secrets en CI (gate G2). |
| 2.7 | **Minimisation du cache** | Cache TanStack Query en mémoire par défaut ; persistance disque limitée aux données froides non sensibles (catalogue, thème, horaires — politique §6 des cadrages #563/#564). Jamais persistés : tokens, coordonnées client (côté marchand), `qr_payload`, `pickup_code`. Purge complète au logout (§2.9). **Pas de chiffrement local additionnel** : rien de sensible ne quitte le secure store, un second chiffrement n'aurait pas de clé mieux protégée que Keychain/Keystore — non justifié, conforme au critère de l'issue. |
| 2.8 | **Logs et rédaction** | Politique : niveau `warn`+ en production ; **jamais** de token, mot de passe, PII, contenu de Kadhia, note libre, code de retrait dans un log ou breadcrumb. Scrubbers Sentry (`beforeSend` + `beforeBreadcrumb`) : suppression des en-têtes `Authorization`, des bodies de requête, des URL avec token. Remontée backend via `POST /api/client-logs` (existant, `ClientLogProcessor` masque déjà les tokens). `console.log` strippés des builds de production. |
| 2.9 | **Logout / changement de compte** | Logout = **purge locale totale** (secure store, caches TanStack Query, état de navigation, badge) **+ révocation serveur du refresh token** (`POST /api/auth/logout`, #616) **+ révocation du device push** (`DELETE /api/mobile/devices/{id}`, best-effort, #620). Le logout aboutit toujours localement, même hors ligne (les révocations serveur partent en best-effort). Changement de compte = logout complet puis login — jamais de multi-comptes simultanés dans une app. |
| 2.10 | **Captures d'écran** | **Autorisées partout, sauf l'écran QR de retrait client (E15)** : `FLAG_SECURE` Android / protection équivalente Expo sur cet écran uniquement. Justification : le QR est le seul artefact rejouable affiché ; le reste (catalogue, commandes) est la donnée de l'utilisateur lui-même, et bloquer les captures globalement dégrade le support (« envoyez-nous une capture »). Défense en profondeur : même capturé, le QR reste protégé par G-retrait (usage unique + double validation). Écran de scan marchand (E07) : rien de secret affiché, pas de restriction. |
| 2.11 | **Dépendances / SDK** | Inventaire tenu dans le dépôt mobile (`docs/` du dépôt) ; lockfile committé et versions épinglées ; `npm audit` + scan de secrets en CI (gate G2) ; politique ADR-0007 : Expo SDK courant ou n-1, upgrade par vague Expo, toute lib native hors config plugin justifiée par écrit. SDK tiers V1 : **Expo (+ modules officiels) et Sentry uniquement** — pas de SDK analytics tiers tant que la décision outil (§9, DH-6) n'est pas prise. |
| 2.12 | **Application obsolète** | Gate par **#618** : `GET /api/mobile/config` consulté au démarrage ; version < minimale → écran de mise à jour obligatoire (E18 client / E17 marchand), non contournable ; mode `maintenance` → écran dédié. Échec réseau du check : l'app démarre (fail-open — la disponibilité prime, le backend garde ses propres gardes). |
| 2.13 | **Sauvegardes** | **Secure store exclu des sauvegardes** : iOS `kSecAttrAccessibleWhenUnlockedThisDeviceOnly` (l'entrée ne migre ni par backup ni vers un autre appareil — déjà proposé par #565 §2.10) ; Android : clés Keystore non exportables par conception + exclusion des données app de l'auto-backup (`android:allowBackup=false` ou règles d'exclusion). Complété par la rotation à usage unique de #616 (un token restauré déjà consommé est mort). |
| 2.14 | **Deep links** | Revalidation serveur systématique (acté #566 §1/§7) : un lien ou QR transporte au plus un identifiant opaque ; auth, rôle, ownership, état revérifiés à chaque requête. Motifs de chemins disjoints entre les deux apps. |
| 2.15 | **Révocation de clés / certificats** | Procédure documentée dans le dépôt mobile au bootstrap : compromission clé Android → Play App Signing permet la rotation de la clé d'upload (la clé de signature reste chez Google) ; iOS → révocation du certificat de distribution dans l'Apple Developer Portal + regénération via EAS ; token compromis → #616 (chaîne) ; `qrCodeToken` magasin compromis → régénération admin S5-005 + réimpression. |
| 2.16 | **Réponse à incident mobile** | Plan minimal V1, proportionné à l'équipe : détection (alertes Sentry §6 + `GET /api/admin/ops/messenger` #353) → qualification (request_id #617 pour corréler) → réaction graduée : correctif OTA EAS Update (JS uniquement, dans les limites stores) < build expédié en rollout accéléré < relèvement de la version minimale #618 (coupe les versions compromises) < mode `maintenance` #618 (coupe tout). Contact sécurité publié sur la page support (§3.5). |

---

## 3. Comptes, identité et signature

### 3.1 Propriété des comptes (décision humaine à acter — G0)

- **Comptes Apple Developer et Google Play Console détenus par le propriétaire
  du produit** (l'entité exploitante), jamais par un prestataire ou un développeur
  individuel — décision déjà formalisée par l'ADR-0007, **à acter humainement**
  (création des comptes = dépense + identité légale).
- Authentification forte (2FA) obligatoire sur les deux consoles ; au moins deux
  administrateurs humains (pas de compte unique orphelin) ; les développeurs
  reçoivent des rôles limités (upload/release), jamais Owner.
- Informations légales, nom d'éditeur, pays, contacts de revue : à renseigner par
  le propriétaire à la création (bloc de la décision G0).
- Comptes de démonstration pour la revue Apple/Google : un compte client et un
  compte marchand seedés sur l'environnement de production (ou staging public
  stable), avec un QR magasin de démonstration — à préparer au gate G5.

### 3.2 Identifiants d'applications (hypothèse à valider — G0)

| Application | Nom public (hypothèse) | Package Android | Bundle ID iOS |
|---|---|---|---|
| Client | Kadhia | `tn.kadhia.client` | `tn.kadhia.client` |
| Marchand | Kadhia Marchand | `tn.kadhia.merchant` | `tn.kadhia.merchant` |

**Hypothèse de marque « Kadhia » (ADR-0007) à VALIDER avant toute réservation** :
les identifiants sont immuables après première publication. Icônes clairement
différenciées (le marchand ne doit jamais confondre les deux apps). Identifiants
de dev/staging par suffixe (`.dev`, `.staging`) via les profils EAS, pour
coexister sur un même appareil.

### 3.3 Signature (qui détient quoi)

| Artefact | Détenteur | Notes |
|---|---|---|
| Clé de signature Android (app signing key) | **Google (Play App Signing)** | Standard actuel ; permet la rotation de la clé d'upload en cas de compromission |
| Clé d'upload Android | Générée et hébergée par **EAS** ; propriété du compte Expo du **propriétaire du produit** | EAS héberge, ne possède pas (ADR-0007) ; export/sauvegarde hors EAS documentés au bootstrap |
| Certificats + provisioning profiles iOS | Apple Developer du propriétaire ; gérés via **EAS** | Idem : EAS héberge, le compte Apple reste souverain |
| Empreintes SHA-256 (App Links) | Dérivées de la clé de signature | Alimentent `assetlinks.json` — dépendent donc de la création effective des comptes |
| Credentials push FCM/APNs | Déposés dans **EAS** par environnement | Le backend n'en détient aucun (Expo Push Service, #566 §4.1) |

Sauvegarde et récupération : procédure écrite au bootstrap du dépôt mobile
(gate G2) — export des credentials EAS, coffre du propriétaire, test de
restauration. **Aucun certificat/keystore dans un dépôt Git, jamais.**

### 3.4 Domaine canonique (décision humaine BLOQUANTE — héritée de #566 §7.1)

Le nom de domaine public définitif (lié à la marque) conditionne :

- `/.well-known/assetlinks.json` et `apple-app-site-association` (App/Universal
  Links — vérifiés par l'OS à l'installation) ;
- `FRONTEND_URL` de production (#543, `docs/ops/frontend-url.md`) ;
- **les QR magasins imprimés en série** chez les marchands (route
  `/stores/by-qr/` gelée) ;
- les URLs publiques des fiches stores (§3.5).

**Aucun build store, aucun fichier `.well-known`, aucune impression en série
avant cette décision.** Placeholder documentaire : `https://app.example.tn`.

### 3.5 URLs publiques exigées par les stores

À servir sur le domaine canonique avant soumission (gate G5) : politique de
confidentialité (FR/AR), page support/contact, page de demande de suppression de
compte (exigence Google Play — le backend couvre déjà la suppression in-app :
`DELETE /api/me/account`, S7-003, constatée READY par #565).

---

## 4. Environnements et configuration

Trois environnements, portés par les **profils EAS** (ADR-0007) :

| | development | staging | production |
|---|---|---|---|
| URL API | backend local / tunnel HTTPS | staging dédié | production |
| Base de données | locale | **base staging séparée** | production |
| Identifiants apps | `tn.kadhia.*.dev` | `tn.kadhia.*.staging` | `tn.kadhia.client` / `.merchant` |
| Push | projet EAS/`projectId` dev | projet staging | projet production |
| Devices push | cloisonnés par base (décision 9 de #566 §3 : **pas de champ `environment`** — chaque backend a sa base, les `projectId` EAS rendent les tokens non interchangeables) | idem | idem |
| Comptes de test / seeds | fixtures locales | comptes + supérette + QR de démonstration seedés | comptes de revue stores uniquement |
| Observabilité | Sentry désactivé ou env `development` | Sentry env `staging` | Sentry env `production` + alertes |
| Distinction visuelle | badge/bannière d'environnement + icône marquée | idem | aucune |
| Secrets | **aucun dans le binaire ni le dépôt** (§2.6) : variables `EXPO_PUBLIC_*` par profil, secrets de build dans EAS | idem | idem |

Le staging doit être assez proche de la production pour valider push (worker
Messenger actif, S7-009), deep links (un domaine de staging si les liens doivent
être vérifiés en staging — sinon validation des links en production interne),
QR, emails, certificats et migrations. L'état réel du staging backend (hors
périmètre de l'audit #565) est une **vérification du gate G3**.

---

## 5. Stratégie QA

### 5.1 Pyramide de tests

| Niveau | Outil | Périmètre | Où |
|---|---|---|---|
| Unitaires | Jest | utilitaires `mobile-core` (dates Africa/Tunis, montants TND string 3 décimales, parsing QR), mapping `ApiError`, réducteurs d'état | CI sur chaque PR |
| Composants | React Native Testing Library | écrans critiques : Kadhia, soumission, retrait, première connexion marchand — FR **et** AR/RTL | CI sur chaque PR |
| Contrat API | SDK généré `@kadhia/api-sdk` (#565 §5 : openapi-typescript ; spec committé, diff = revue) | toute dérive du contrat casse la compilation TS ; diff `oasdiff` côté backend | CI (gate G2) |
| E2E mobile | **Maestro** (proposé par ADR-0007 ; arbitrage final Maestro vs Detox = point à revisiter de l'ADR, à trancher au bootstrap) | les 8 flows §5.2, sur staging | build staging (merge main) + gate G3 |
| Backend ciblé | PHPUnit existant | gardes citées au §1 (déjà couvertes par les suites fonctionnelles) | CI backend existante |
| Manuel terrain | checklist supérette pilote (alignée sur la checklist d'activation #356) | scan réel, réseau réel, appareils réels | gates G4/G5 |

### 5.2 Flows E2E prioritaires (8)

1. **Client — parcours cœur** : scan QR magasin → catalogue → création Kadhia →
   lignes → rendez-vous → soumission → suivi (`submitted`) (#563 §3.0).
2. **Marchand — parcours cœur** : notification nouvelle commande → détail →
   accept → start-preparation → coche des lignes → mark-ready (#564 §3.0).
3. **Retrait double validation** (le flow le plus critique, croise les deux
   apps) : client affiche le QR de retrait → marchand scanne → session
   `pickup_pending` → confirmation marchand → confirmation client → `completed`.
   Variantes : code 4 chiffres (`redeem-by-code`, finalisation directe) ; QR
   expiré/déjà utilisé → messages cas 8 #564.
4. **Acceptation partielle bout en bout** : marchand partially-accept → push
   client → correction de la Kadhia → nouvelle soumission (#563 §3.5, #564 §3.3).
5. **Auth client** : inscription → auto-login → logout (purge) → login → reprise
   d'un deep link `/orders/{id}` après login (#563 §3.2) ; token expiré en cours
   d'action → refresh silencieux (#616) ou re-login sans perte de contexte.
6. **Première connexion marchand** : mot de passe provisoire → verrouillage →
   changement → dashboard (#564 §3.1) ; compte révoqué en cours de session →
   403 propre (#564 §3.4, G-ownership).
7. **Dégradé réseau** : soumission en réseau lent (verrou UI, pas de double
   commande — #565 §4.1) ; scan retrait réseau coupé → message cas 11, token
   conservé, **jamais** de validation locale ; app tuée puis relancée en
   `pickup_pending` → reprise du polling.
8. **Version minimale / maintenance** : version < seuil → écran E18/E17 non
   contournable ; mode maintenance → écran dédié (#618) ; push de type inconnu →
   liste des notifications sans crash (#566 test 10).

### 5.3 Matrice d'appareils (réaliste marché tunisien — Android dominant)

| Appareil | Représente | Usage |
|---|---|---|
| Android entrée de gamme (2–3 Go RAM, Android 10–11 — ex. Samsung A0x/Redmi 9A) | le téléphone de comptoir marchand réel | E2E complet + performance démarrage/catalogue |
| Android milieu de gamme (ex. Redmi Note / Samsung A2x, Android 13) | le client type | E2E complet |
| Android récent (Android 14+) | validation des permissions modernes (`POST_NOTIFICATIONS` 13+) | E2E + push |
| iPhone à la version iOS minimale (ex. iPhone 8/SE2 sous iOS 15) | plancher iOS | E2E cœur + retrait |
| iPhone récent | validation courante | E2E complet |
| Petit écran (~5") et grand écran (6,7"+ / tablette Android comptoir) | mises en page | revue visuelle FR + AR |

**Versions minimales proposées** : **Android 8.0 (API 26)** et **iOS 15**.
Justification : Android 8+ couvre la quasi-totalité du parc actif tunisien, y
compris l'entrée de gamme d'occasion côté marchand, et reste le plancher
raisonnable des SDK Expo récents ; iOS 15 couvre tous les iPhone depuis le 6s et
est le plancher courant des SDK. Descendre plus bas coûte en support sans
utilisateurs mesurables ; monter plus haut exclurait des marchands réels. À
confirmer avec les données terrain de la bêta (gate G4).

Appareils **physiques** obligatoires pour : caméra/scan QR, push, App/Universal
Links, performance réelle sur entrée de gamme.

### 5.4 Dimensions transverses obligatoires

- **RTL arabe : obligatoire à chaque niveau** (composants et E2E) — pas une
  passe finale : chaque écran critique est testé FR et AR (I18nManager,
  miroir des layouts, montants TND restant lisibles en RTL).
- Réseau lent / intermittent / absent (profils de throttling ; scénarios §5.2 n°7).
- App premier plan / arrière-plan / tuée (notamment pendant `pickup_pending`).
- Token expiré, mauvais rôle (JWT client sur route marchand → 403), permissions
  caméra/push refusées (parcours de secours cas #13 #563), changement de compte,
  action simultanée PWA/mobile (reprise de préparation #564 §3.9 — idempotence
  vérifiée #565 §4.5), fuseau/heure incorrecte (jamais de validation d'expiration
  locale — #565 §2.11), mise à jour depuis la version précédente, installation
  propre et restauration de sauvegarde (§2.13).
- **Accessibilité minimale** : labels sur les éléments interactifs, tailles de
  texte dynamiques sans casse de layout, contrastes des thèmes supérette,
  cibles tactiles ≥ 44 pt — alignée sur le socle WCAG déjà livré côté PWA
  (S14-006/#459), vérifiée sur les écrans des parcours cœur.

---

## 6. Observabilité

1. **Crash reporting : Sentry via `sentry-expo`** (proposé). Justification :
   intégration Expo officielle, source maps EAS, gratuit au volume attendu, et
   compatible minimisation : scrubbers `beforeSend`/`beforeBreadcrumb` (§2.8),
   pas d'IP stockée (option), pas d'identifiant publicitaire. Environnements
   séparés (§4). Alternative auto-hébergée (GlitchTip) si l'hébergement des
   données hors UE/TN pose problème — à trancher avec DH-6 (§9).
2. **Erreurs non fatales** : remontées Sentry (niveau warning) + canal backend
   existant `POST /api/client-logs` (public, `ClientLogProcessor` masque les
   tokens) via le package `@kadhia/observability` (ADR-0007).
3. **Corrélation `request_id` (#617)** : le SDK envoie `X-Client-Request-Id` dès
   V1 (déjà accepté et journalisé par le backend) et lira `X-Request-Id` serveur
   quand #617 sera livré ; l'identifiant est joint aux événements Sentry et
   affiché sur les écrans d'erreur 5xx comme référence support — jamais dans les
   événements analytics (`error_shown` l'exclut explicitement, #563/#564 §7).
4. **Pas de données personnelles dans les événements** : règle des cadrages
   #563/#564 §7 reprise telle quelle — jamais de PII, contenu de Kadhia, montant
   individuel, note, token ou code de retrait ; identifiants techniques admis
   (`store_id`, statuts, compteurs). Version app + version OS **majeure** seules
   (pas de fingerprinting — aligné sur `os_major_version` de #566 §3).
5. **Métriques push** : côté backend, compteurs par device + logs canal
   `notification` + transport `failed` visibles via `GET /api/admin/ops/messenger`
   (#353), tel qu'acté par #620/#566 §4.3 ; côté app, `notification_opened` par
   type (métrique produit des cadrages). Taux de délivrance = tickets/receipts
   Expo (#620).
6. **Performance** : temps de démarrage et temps de chargement catalogue mesurés
   sur l'Android d'entrée de gamme de la matrice (§5.3) — seuils indicatifs :
   démarrage < 3 s, catalogue < 2 s en 3G ; suivis en bêta, pas de gate chiffré V1.
7. **Dashboards et alertes** : alerte Sentry sur pic de crashs et sur toute
   régression du crash-free < 99 % ; revue hebdomadaire pendant la bêta ;
   procédure support : référence = `request_id` (ou l'identifiant client en
   attendant #617).

---

## 7. Bêta et rollout progressif

Ordre conforme ADR-0005/ADR-0007 : **Android marchand → Android client → iOS
client → iOS marchand (si confirmé)**.

| Étape | Canal | Contenu |
|---|---|---|
| 1. Interne | Play **internal testing** + **TestFlight interne** | équipe + comptes seedés staging ; smoke tests + E2E |
| 2. Bêta fermée | Play **closed testing** + TestFlight externe (groupe restreint) | supérettes pilotes (marchand) puis clients invités — cohérent avec la **checklist d'activation supérette #356** : seules des supérettes « activables » (créneaux, catalogue, QR imprimé) entrent en pilote |
| 3. Bêta terrain croisée | idem | parcours réels client ↔ marchand dans les supérettes pilotes, dont **retrait double validation en conditions réelles** |
| 4. Production progressive | Play **staged rollout 10 % → 50 % → 100 %** ; iOS **phased release** (7 jours, pausable) | monitoring continu ; l'app marchande, au public restreint, peut passer plus vite les paliers |

**Critères de sortie de bêta (chiffrés, gate G4 → G5)** :

- **crash-free sessions > 99 %** sur 14 jours glissants (Sentry) ;
- **parcours cœur E2E verts** (les 8 flows §5.2) sur les builds candidats ;
- **retrait double validation validé en conditions réelles** dans au moins
  2 supérettes pilotes, sur au moins 20 retraits, sans échec non expliqué ;
- zéro bug bloquant ouvert sur les parcours cœur ; taux d'échec de scan QR
  terrain < 5 % (sinon : travail UX scan avant G5) ;
- **#622 livré** (rate limiting) avant toute ouverture au-delà du groupe fermé.

**Rollback** : pas de retour arrière binaire possible sur les stores → la
stratégie est : pause du rollout (Play/phased release) + correctif OTA EAS
Update pour le JS + build expédié si natif ; compatibilité descendante du
backend garantie (évolutions additives, #565 §6) ; en dernier recours,
relèvement de la version minimale #618 (§2.16).

---

## 8. Gates go/no-go

| Gate | Intitulé | Critères de sortie | Nature | Responsable |
|---|---|---|---|---|
| **G0** | Décisions fondatrices | ADR-0007 **accepté** (statut actuel : Proposé) ; marque « Kadhia » validée ; **domaine canonique acté** (§3.4) ; comptes Apple/Google créés au nom du propriétaire, 2FA, rôles attribués (§3.1) | **Décision humaine** | Propriétaire du produit |
| **G1** | Socle backend | **#616** (refresh + révocation), **#617** (request_id), **#618** (version minimale/maintenance), **#620** (mobile_device + push natif) livrés sur `main`, testés ; staging backend opérationnel avec worker Messenger | Vérification technique | Dev backend |
| **G2** | Bootstrap mobile conforme ADR | Dépôt `click-and-collect-mobile` : monorepo apps/packages conforme ADR-0007 ; SDK généré depuis `/api/docs.json` committé ; CI (lint, type-check, unit, diff contrat, scan dépendances/secrets) verte ; profils EAS 3 environnements sans secret embarqué ; procédure credentials/signature écrite (§3.3) | Vérification technique | Dev mobile |
| **G3** | Parcours cœur verts sur staging | Les 8 flows E2E §5.2 verts sur staging, sur la matrice d'appareils §5.3 (dont Android entrée de gamme physique) ; FR + AR/RTL ; revue de rédaction des logs/Sentry (§2.8) ; App/Universal Links vérifiés sur builds signés | Vérification technique | Dev mobile + QA |
| **G4** | Bêta fermée OK | Critères chiffrés §7 atteints ; **#622 livré** ; retours supérettes pilotes traités ; fiches stores prêtes (Data safety, Privacy labels, URLs publiques §3.5, comptes de revue) | Mixte : vérifications + **go humain** (lecture des retours terrain) | Propriétaire + QA |
| **G5** | Publication | Soumissions acceptées ; rollout progressif engagé (10 % Play / phased iOS) avec monitoring §6 actif ; plan de rollback §7 prêt ; passage au palier suivant seulement si crash-free ≥ 99 % maintenu | Mixte : vérifications + **go humain par palier** | Propriétaire du produit |

Chaque gate : entrée = gate précédent franchi ; preuves = liens CI, rapports
E2E, captures Sentry, comptes rendus pilotes ; un gate non franchi bloque, il ne
se contourne pas.

---

## 9. Récapitulatif des décisions humaines en attente (chaîne #562 → #567)

Sortie exécutive du cadrage mobile — tout le reste est décidé dans les documents
de la chaîne.

| # | Décision | Origine | Bloque | Qui |
|---|---|---|---|---|
| DH-1 | **Accepter l'ADR-0007** (statut : Proposé) | #562 | Tout bootstrap du dépôt mobile (G0, G2) | Propriétaire du produit |
| DH-2 | **Marque « Kadhia »** : nom public des apps, identifiants `tn.kadhia.client`/`tn.kadhia.merchant` | #562 (hypothèse), #563/#564 §8, §3.2 ici | Réservation des identifiants (immuables), fiches stores (G0) | Propriétaire |
| DH-3 | **Domaine canonique** de production | #566 §7.1, #543 | **Bloquant** : assetlinks/AASA, builds store, QR imprimés en série, URLs publiques (G0) | Propriétaire |
| DH-4 | **Création des comptes Apple Developer + Google Play** au nom de l'entité exploitante (dépense + identité légale + 2FA + rôles) | Issue #567 §4, §3.1 ici | Signature, empreintes App Links, soumission (G0) | Propriétaire |
| DH-5 | **Notifications en arabe** : accepter le repli FR en V1 ou brancher `title_ar`/`body_ar` (contenus déjà en base — #566 §2.1 ; lot A de #620 crée les types) | #563/#564 §8, #565 P2 | Qualité AR de la bêta (avant G4 souhaitable) | Produit |
| DH-6 | **Outil analytics + consentement/opt-out** (aucun SDK analytics embarqué tant que non tranché — §2.11 ; Sentry seul en attendant) | #563/#564 §8 | Métriques produit §8 de l'issue (avant G4) | Produit |
| DH-7 | **WhatsApp commande (#378)** : trancher le produit avant tout repêchage mobile | #564 §8 | Rien de bloquant (OUT_OF_SCOPE V1) | Produit |
| DH-8 | **Périmètres reportés à confirmer** : équipe/export CSV maintenus web (marchand) ; favoris/suggestions/partage Kadhia post-V1 (client) ; multi-boutique hors V1 | #563/#564 §8 | Backlog de build (G2) | Produit |
| DH-9 | **Ordre de lancement** : confirmer Android marchand d'abord (ADR-0005) avec la capacité de support terrain | #562, #563/#564 §8 | Plan de bêta §7 | Propriétaire |
| DH-10 | **Go G4 (sortie de bêta) et G5 (publication, chaque palier)** — gates explicitement humains | §8 ici | Publication | Propriétaire |

Décisions **tranchées par ce document** (n'attendent plus personne) : pas de
pinning V1 (§2.3), pas de RASP/anti-tamper (§2.4), root/jailbreak non bloquant
(§2.5), captures autorisées sauf QR de retrait (§2.10), pas de chiffrement local
additionnel (§2.7), secure store hors sauvegardes (§2.13), Sentry proposé (§6),
Android 8+/iOS 15 proposés (§5.3), Maestro proposé (§5.1, arbitrage final au
bootstrap), critères de sortie de bêta chiffrés (§7), gates G0–G5 (§8).

---

## Hypothèses et limites

- MASVS L1 est une **auto-évaluation cible** : aucun audit externe, test
  d'intrusion ni certification MASA n'est réalisé ni revendiqué.
- Les versions minimales OS (§5.3) et les seuils chiffrés (§7) sont des
  propositions à confronter aux données terrain de la bêta.
- L'état réel du staging backend n'a pas été audité (hors périmètre #565) :
  c'est une vérification du gate G1/G3, pas un acquis.
- Le pipeline CI/CD détaillé (fournisseur de build : EAS acté par ADR-0007 ;
  versionCode/changelogs/rétention d'artefacts) se précise au bootstrap du dépôt
  mobile (gate G2), dans le cadre fixé ici : approbation manuelle avant
  production, dépendances verrouillées, OTA limité au JS, aucun secret en clair.
- La matrice détaillée vie privée/SDK (issue §3) sera finalisée avec les fiches
  stores (Data safety / Privacy Nutrition Labels) au gate G4, sur la base de la
  minimisation actée ici (§2, §6) et par #566 (§3, §6) ; le backend couvre déjà
  la suppression de compte in-app (S7-003) et la purge des devices push (#620).
