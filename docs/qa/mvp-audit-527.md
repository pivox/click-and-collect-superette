# Audit MVP — Issue #527

Date d'audit : 18 juin 2026
Issue mère : https://github.com/pivox/click-and-collect-superette/issues/527
Nature : audit fonctionnel et technique, sans correction applicative.

## 1. Résumé exécutif

- Statut global : orange.
- Recommandation : go sous conditions.

Top 5 risques MVP :

1. La suite backend complète échoue en environnement local alors que les tests frontend et le lint passent.
2. La suite backend complète a été lancée avec un override local non commité de `FRONTEND_URL`, ce qui réduit la lisibilité du résultat QA.
3. Les tests admin de génération de mot de passe temporaire marchand passent isolément mais échouent dans la suite complète, ce qui indique un risque d'ordre de tests ou d'état partagé.
4. Les parcours complets client + marchand + admin n'ont pas été rejoués en live avec données seedées pendant cet audit.
5. Les sessions front reposent sur `localStorage`/cookies par portail; la séparation réelle client / marchand / admin est protégée côté API, mais l'UX des tokens périmés ou mal rangés reste à durcir.

Top 5 bugs bloquants :

1. Aucun bug P0 de commande client ou login n'a été confirmé par reproduction live pendant cette passe.
2. BUG-MVP-001 : la suite backend complète échoue sur 8 failures.
3. BUG-MVP-002 : les tests admin `temporary-password` échouent dans la suite complète mais passent isolément.
4. À vérifier : aucun parcours navigateur complet n'a été exécuté dans cette passe pour confirmer absence de blocage runtime.
5. Note QA locale : les tests QR / partage Kadhia doivent être relus avec la configuration `FRONTEND_URL` commitée.

## 2. Méthodologie

Fichiers lus :

- `AGENTS.md`
- `AI_CONTEXT.md`
- `README.md`
- `Codex/instructions.md`
- `Codex/workflows.md`
- `Codex/checklist.md`
- `docs/architecture/api-contract.md`
- `docs/product/mvp-functional-audit.md`
- `docs/qa/client-journey-simulation-report.md`
- `docs/qa/client-journey-simulation-report-v2.md`
- `docs/front/logging.md`
- fichiers frontend client sous `apps/frontend/src/app/(client)` et services associés
- fichiers frontend marchand sous `apps/frontend/src/app/merchant`, `components/merchant`, services associés
- fichiers frontend admin sous `apps/frontend/src/app/admin`, `components/admin`, services associés
- ressources, processors, providers, entités, sécurité et services backend sous `apps/backend/src`
- tests backend sous `apps/backend/tests/Functional/Api` et `apps/backend/tests/Unit`
- tests frontend sous `apps/frontend/src/tests`

Fichiers demandés mais absents :

- `docs/project/source-of-truth.md`
- `docs/roadmap/mvp-roadmap.md`

Endpoints identifiés :

- Auth : `POST /api/auth/login`, `POST /api/auth/register/customer`, `POST /api/auth/forgot-password`, `POST /api/auth/reset-password`, `GET/PATCH /api/me/profile`
- Client supérette : `GET /api/stores/search`, `GET /api/stores/{storeId}`, `GET /api/stores/by-qr/{qrCodeToken}`, `GET /api/stores/{storeId}/catalog`, `GET /api/stores/{storeId}/pickup-slots`, `POST /api/me/stores/{storeId}/visit`
- Kadhia : `POST /api/me/stores/{storeId}/kadhias`, `GET/PATCH/DELETE /api/me/kadhias/{kadhiaId}`, `PUT/DELETE /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}`, `POST /api/me/kadhias/{kadhiaId}/submit`
- Commandes client : `GET /api/me/orders`, `GET /api/me/orders/{id}`, `POST /api/me/orders/{orderId}/cancel`, `GET /api/me/orders/{orderId}/status`, `GET /api/me/orders/{orderId}/status-history`
- Retrait client : `GET /api/me/orders/{orderId}/pickup-session`, `PATCH /api/me/pickup-sessions/{id}/confirm`
- Marchand commandes : `GET /api/merchant/stores/{storeId}/orders`, `GET /api/merchant/stores/{storeId}/orders/{orderId}`, `POST /accept`, `POST /reject`, `POST /partially-accept`, `POST /start-preparation`, `PATCH /lines/{merchantProductId}/preparation`, `POST /mark-ready`
- Retrait marchand : `POST /api/merchant/pickup-sessions/scan`, `PATCH /api/merchant/pickup-sessions/{id}/confirm`, `PATCH /api/merchant/pickup-sessions/{id}/force-complete`, `POST /api/merchant/stores/{storeId}/orders/redeem-by-code`, `POST /api/merchant/stores/{storeId}/orders/{orderId}/validate-manually`
- Catalogue marchand : `GET /api/merchant/stores/{storeId}/catalog`, `PATCH /api/merchant/catalog/{merchantProductId}`, `PATCH /api/merchant/stores/{storeId}/products/bulk-availability`
- Créneaux : `GET/POST/PATCH/DELETE /api/merchant/stores/{storeId}/pickup-slots`, règles, fermetures et horaires associés
- Notifications : `GET/PATCH /api/me/notifications`, `GET/PATCH /api/merchant/notifications`
- Admin : marchands, supérettes, référentiel produit, propositions, audit, ops, beta metrics, activation checklist
- Observabilité : `GET /api/health`, `POST /api/client-logs`, `GET /api/admin/ops/messenger`

Parcours audités :

- authentification client, marchand, admin ;
- profil client et profil marchand ;
- recherche supérette et accès QR magasin ;
- catalogue public client et catalogue marchand ;
- multi-Kadhia et partage Kadhia ;
- créneaux de retrait ;
- soumission de commande ;
- acceptation, refus, acceptation partielle ;
- préparation ligne par ligne ;
- QR de retrait, code de retrait 4 chiffres, validation manuelle ;
- notifications client et marchand ;
- backoffice admin ;
- logs frontend/backend et healthcheck.

Tests existants consultés :

- `SubmitOrderApiTest`, `KadhiaApiTest`, `MerchantOrderApiTest`, `MerchantPickupCodeApiTest`, `MerchantPickupSession*ApiTest`, `CustomerPickupSession*ApiTest`
- `CustomerRegistrationApiTest`, `PasswordResetApiTest`, `CustomerProfileApiTest`
- `StoreSearchApiTest`, `StorePublicApiTest`, `PublicStoreCatalogApiTest`
- `MerchantCatalogApiTest`, `PickupSlotApiTest`, `MerchantNotificationApiTest`, `CustomerNotificationApiTest`
- `HealthCheckApiTest`, `ClientLogTest`, `AdminMessengerMonitoringApiTest`
- tests frontend `client.*`, `merchant.*`, `admin.*`, `auth.*`, `clientLogger.test.ts`

Limites de l'audit :

- Audit majoritairement statique + tests automatisés existants.
- Aucun scénario Playwright live complet n'a été relancé dans cette passe.
- Les endpoints ont été identifiés par lecture de code et tests, pas par export OpenAPI complet.
- La donnée de démonstration réelle, les comptes de test, les emails Mailpit et les workers async n'ont pas été validés manuellement de bout en bout.
- Les changements locaux non liés dans `docker-compose.yml` et `scripts/ngrok-frontend.sh` ont été laissés intacts.

Ce qui n'a pas pu être vérifié sans environnement live :

- Scan QR réel depuis mobile.
- Parcours complet client puis traitement marchand sur vraie donnée seedée.
- Envoi/réception effective des emails de reset password.
- Comportement worker Messenger sur délai réel.
- Lisibilité responsive mobile de tous les écrans.
- Corrélation réelle logs front/backend sur incident navigateur.

## 3. Matrice des parcours MVP

| Domaine | Parcours | Rôle | Statut | Gravité | Preuve | Issue à créer |
|---|---|---|---|---|---|---|
| Authentification | Inscription client | Client | OK | majeure | `CustomerRegistrationApiTest`, `client.register-page.test.tsx`, `/api/auth/register/customer` | Non |
| Authentification | Login JWT client | Client | OK | majeure | `auth.service.ts`, `ClientAuthContext.tsx`, tests auth | Non |
| Authentification | Login marchand | Marchand | OK | majeure | `MerchantAuthContext.tsx`, `merchant.login.test.tsx`, `/api/merchant/me` | Non |
| Authentification | Login admin | Admin | OK | majeure | `AdminAuthContext.tsx`, `admin.login.test.tsx`, middleware admin | Non |
| Authentification | Reset password multi-rôles | Client/Marchand/Admin | OK | majeure | `PasswordResetApiTest`, `forgot-password.test.tsx`, `reset-password.test.tsx` | Non |
| Profil client | Lire/modifier profil | Client | OK | majeure | `/api/me/profile`, `CustomerProfileApiTest`, `updateProfile()` | Non |
| Profil marchand | Lire/modifier compte | Marchand | OK | majeure | `MerchantAccountApiTest`, `merchant.account.test.tsx` | Non |
| Backoffice admin | Marchands/supérettes/référentiel/audit/ops | Admin | À vérifier | majeure | tests admin présents, mais PHPUnit complet échoue | BUG-MVP-002 |
| Recherche supérette | Recherche publique | Client | OK | majeure | `GET /api/stores/search`, `StoreSearchApiTest`, `client.store-search*` | Non |
| Catalogue public client | Liste produits supérette | Client | OK | bloquante | `PublicStoreCatalogApiTest`, `catalog.service.ts`, `client.catalog*` | Non |
| Catalogue marchand | Liste et édition produits | Marchand | OK | majeure | `MerchantCatalogApiTest`, `merchant.catalogue.test.tsx` | Non |
| Multi-Kadhia | Plusieurs Kadhia et Kadhia partagée | Client | OK | majeure | `KadhiaApiTest`, `SubmitOrderApiTest`, `client.kadhia*` | Non |
| Créneaux de retrait | Liste publique + CRUD marchand | Client/Marchand | OK | bloquante | `PickupSlotApiTest`, `merchant.creneaux.test.tsx`, `client.slot-page.test.tsx` | Non |
| Soumission commande | Soumettre Kadhia | Client | OK | bloquante | `SubmitOrderProcessor`, `SubmitOrderApiTest` | Non |
| Acceptation commande | Accepter une commande | Marchand | OK | bloquante | `MerchantAcceptOrderProcessor`, `MerchantOrderApiTest` | Non |
| Refus commande | Refuser une commande | Marchand | OK | bloquante | `MerchantRejectOrderProcessor`, `MerchantOrderApiTest` | Non |
| Acceptation partielle | Accepter partiellement | Marchand/Client | OK | bloquante | `MerchantPartiallyAcceptOrderProcessor`, `SubmitOrderApiTest` | Non |
| Préparation ligne par ligne | Marquer lignes préparées | Marchand | OK | majeure | `MerchantPrepareOrderLineProcessor`, `MerchantOrderApiTest` | Non |
| QR retrait | Session QR client | Client/Marchand | À vérifier | majeure | `PickupSession`, tests pickup ; résultat full suite à relire avec la configuration `FRONTEND_URL` commitée | Note QA locale |
| Code retrait 4 chiffres | Affichage et validation code | Client/Marchand | OK | majeure | `Order::markReady`, `MerchantPickupCodeApiTest`, `merchant.retrait.test.tsx` | Non |
| Validation manuelle marchand | Valider sans QR/code | Marchand | OK | majeure | `MerchantValidateManuallyProcessor`, `merchant-pickup.service.ts`, `MerchantPickupCodeApiTest` | Non |
| Notifications client | In-app client | Client | OK | mineure | `CustomerNotificationApiTest`, `client-notifications.service.ts` | Non |
| Notifications marchand | In-app marchand | Marchand | OK | mineure | `MerchantNotificationApiTest`, `merchant.notifications*` | Non |
| Logs frontend/backend | Corrélation et endpoint front logs | Tous | À vérifier | majeure | `docs/front/logging.md`, `ClientLogTest`, `clientLogger.test.ts` | Issue P2 logging live |
| Healthcheck / erreurs API | Healthcheck public + erreurs API | Ops | OK | majeure | `HealthCheckApiTest`, `HealthCheckControllerTest`, `GET /api/health` | Non |

## 4. Bugs détectés

### BUG-MVP-001 — La suite backend complète échoue

- Domaine : Qualité backend / CI locale.
- Gravité : majeur.
- Rôle concerné : tous, impact indirect.
- Préconditions : lancer la suite complète backend dans l'environnement Docker Compose local audité.
- Étapes de reproduction :
  1. Exécuter `docker compose exec backend php bin/phpunit`.
  2. Attendre la fin des 1531 tests.
- Résultat attendu : suite verte.
- Résultat observé : 8 failures sur 1531 tests.
- Fichiers suspects :
  - `apps/backend/tests/Functional/Api/KadhiaApiTest.php`
  - `apps/backend/tests/Functional/Api/MerchantAdminApiTest.php`
  - `apps/backend/tests/Functional/Api/MerchantStoreQrApiTest.php`
  - `docker-compose.yml` modifié localement pour `FRONTEND_URL=http://192.168.1.48:3000`
- Endpoint suspect : plusieurs, voir BUG-MVP-002 ; les failures QR/share relèvent d'un override local de configuration.
- Risque métier : confiance réduite avant démo ; une CI rouge peut masquer une vraie régression MVP.
- Proposition d'issue GitHub : `[P1] Stabiliser la suite backend complète avant démo MVP`
- Priorité : P1

### BUG-MVP-002 — Tests admin `temporary-password` instables dans la suite complète

- Domaine : Backoffice admin.
- Gravité : majeur.
- Rôle concerné : admin / marchand.
- Préconditions : suite backend complète.
- Étapes de reproduction :
  1. Exécuter `docker compose exec backend php bin/phpunit`.
  2. Observer les failures `MerchantAdminApiTest` autour de `temporary-password`.
  3. Exécuter isolément `docker compose exec backend php bin/phpunit tests/Functional/Api/MerchantAdminApiTest.php`.
- Résultat attendu : `POST /api/admin/merchants/{merchantId}/temporary-password` retourne 200 pour admin, 403 pour non-admin, 422 pour non-marchand.
- Résultat observé :
  - En suite complète : plusieurs assertions reçoivent 404 ou un login ancien mot de passe reste à 200.
  - En test isolé : `MerchantAdminApiTest` passe, 37 tests / 214 assertions.
- Fichiers suspects :
  - `apps/backend/src/ApiResource/AdminMerchantOutput.php`
  - `apps/backend/src/Processor/AdminResetMerchantTemporaryPasswordProcessor.php`
  - `apps/backend/tests/Functional/Api/MerchantAdminApiTest.php`
  - couche de fixtures / isolation `FunctionalApiTestCase`
- Endpoint suspect : `POST /api/admin/merchants/{merchantId}/temporary-password`
- Risque métier : fonctionnalité admin #530 instable ou test non isolé ; risque de faux vert/faux rouge CI.
- Proposition d'issue GitHub : `[P1] Isoler les failures order-dependent du reset mot de passe temporaire marchand`
- Priorité : P1

### NOTE-QA-001 — URLs QR / partage et override local de `FRONTEND_URL`

- Domaine : QR magasin / partage Kadhia / configuration.
- Gravité : note QA locale, pas un bug applicatif confirmé.
- Rôle concerné : client, marchand, admin.
- Préconditions : `FRONTEND_URL=http://192.168.1.48:3000` dans l'environnement backend local.
- Étapes de reproduction :
  1. Exécuter `docker compose exec backend php bin/phpunit`.
  2. Observer les failures `KadhiaApiTest::testCreateShareLinkReturnsReusableActiveLinkForMember`.
  3. Observer les failures `MerchantStoreQrApiTest::testMerchantOwnerReadsQrCode` et `testTargetUrlIsAbsoluteFrontendStoreUrl`.
- Résultat attendu : avec la configuration commitée, URLs en `http://localhost:3000/...` selon tests.
- Résultat observé : avec l'override local non commité, URLs en `http://192.168.1.48:3000/...`.
- Fichiers suspects :
  - `docker-compose.yml` modifié localement
- Endpoint suspect :
  - `POST /api/me/kadhias/{kadhiaId}/share-links`
  - `GET /api/merchant/stores/{storeId}/qr-code`
- Analyse : `KadhiaShareLinkService` et `MerchantStoreQrTargetUrlFactory` doivent utiliser l'origine configurée. L'écart observé vient donc de l'environnement de test local, pas d'un défaut produit confirmé.
- Action : relancer les tests avec la configuration commitée ou documenter explicitement l'override LAN avant d'ouvrir une issue produit.
- Priorité : aucune issue P1 proposée à ce stade.

## 5. Points techniques à risque

- Auth JWT et redirections login : les interceptors redirigent sur 401 par portail, mais les 403 ne déclenchent pas de nettoyage automatique. À vérifier pour tokens expirés, mauvais rôle et cookies admin/merchant périmés.
- Séparation client / marchand / admin : l'API applique `ROLE_CUSTOMER`, `ROLE_MERCHANT`, `ROLE_ADMIN`; côté front, les tokens sont stockés dans `localStorage` par clé (`jwt_token`, `merchant_token`, `admin_token`) et cookies admin/marchand.
- Gestion des erreurs API : plusieurs écrans affichent des messages propres, mais certains tests frontend montrent des `console.error` attendus et des warnings React.
- États métier incohérents : l'entité `Order` centralise les transitions; les tests couvrent les statuts principaux. À vérifier en live sur transitions rapides marchand.
- Données en localStorage : Kadhia active, contexte supérette et tokens reposent sur `localStorage`; risque de désynchronisation après suppression, changement de compte ou multi-onglets.
- Concurrence / double submit : `SubmitOrderProcessor` protège la capacité créneau via `UPDATE ... booked_count < capacity`; idempotence couverte quand la Kadhia n'est plus draft. À vérifier en charge réelle.
- Idempotence de soumission commande : présente côté backend pour Kadhia déjà soumise avec commande active.
- Cohérence Kadhia ↔ Order : tests de soumission, acceptation partielle et resoumission présents; à vérifier manuellement dans le parcours UI complet.
- Logs et correlation id : `X-Client-Request-Id`, `CorrelationIdSubscriber`, `CorrelationIdProcessor`, `POST /api/client-logs` présents; corrélation live non vérifiée.
- Healthcheck : `GET /api/health` public et testé.
- Workers async / notifications : Messenger Doctrine configuré, worker local actif pendant l'audit; délais réels non rejoués.

## 6. Issues atomiques proposées

### Issue proposée : [P1] Stabiliser la suite backend complète avant démo MVP

Body :

- Contexte : l'audit #527 a lancé `docker compose exec backend php bin/phpunit`.
- Problème : la suite complète échoue avec 8 failures, même si des tests ciblés passent isolément.
- Étapes de reproduction : lancer la suite backend complète dans Docker Compose.
- Attendu : 0 failure sur la suite complète.
- Critères d'acceptation : `php bin/phpunit` passe dans le conteneur backend ; les causes sont documentées.
- Tests attendus : suite backend complète.
- Hors périmètre : refonte métier, paiement, livraison, marketplace.

### Issue proposée : [P1] Isoler les failures order-dependent du reset mot de passe temporaire marchand

Body :

- Contexte : l'endpoint admin `POST /api/admin/merchants/{merchantId}/temporary-password` est déclaré et les tests passent isolément.
- Problème : en suite complète, plusieurs tests `MerchantAdminApiTest` reçoivent 404 ou un état de login incohérent.
- Étapes de reproduction : lancer la suite complète, puis relancer `MerchantAdminApiTest` seul.
- Attendu : mêmes résultats en suite complète et isolée.
- Critères d'acceptation : aucun état partagé ne fait varier ces tests ; 200/403/422 attendus selon rôle et cible.
- Tests attendus : `MerchantAdminApiTest` seul et suite complète.
- Hors périmètre : email d'invitation marchand.

### Suivi QA : relire les tests QR/share avec la configuration `FRONTEND_URL` commitée

Body :

- Contexte : les QR magasin et liens de partage Kadhia utilisent volontairement `FRONTEND_URL`.
- Problème : l'audit local a utilisé un override LAN non commité, ce qui a produit des URLs différentes des assertions `localhost`.
- Étapes de reproduction : relancer les tests QR/share avec la configuration commitée, puis seulement avec un override LAN documenté si nécessaire.
- Attendu : résultats QA séparant clairement comportement attendu et override local.
- Critères d'acceptation : conclusion du rapport confirmée avec la configuration commitée ou correction du rapport si les tests passent.
- Tests attendus : `KadhiaApiTest` et `MerchantStoreQrApiTest`.
- Hors périmètre : génération graphique du QR.

### Issue proposée : [P2] Durcir l'UX des tokens périmés ou mauvais rôle par portail

Body :

- Contexte : les portails client, marchand et admin stockent leurs tokens dans `localStorage` et cookies dédiés.
- Problème : le backend protège les routes, mais l'UX front peut garder un shell ou un état utilisateur jusqu'à l'erreur API.
- Étapes de reproduction : injecter un token expiré ou d'un mauvais rôle dans la clé d'un autre portail.
- Attendu : nettoyage immédiat et redirection claire vers le login du portail.
- Critères d'acceptation : tests front pour tokens expirés/mauvais rôle par portail.
- Tests attendus : `client.auth.context.test.tsx`, tests admin/merchant auth.
- Hors périmètre : refonte complète de l'auth.

### Issue proposée : [P2] Réduire les warnings React dans la suite frontend

Body :

- Contexte : `npm run test:run` passe, mais affiche des warnings `act(...)`, clés dupliquées et requête abortée.
- Problème : ces warnings réduisent le signal QA et peuvent masquer une future régression UX.
- Étapes de reproduction : lancer `docker compose exec frontend npm run test:run`.
- Attendu : suite verte avec sortie propre ou warnings explicitement maîtrisés.
- Critères d'acceptation : warnings React corrigés ou filtrés uniquement si volontaire.
- Tests attendus : suite frontend complète.
- Hors périmètre : changements métier.

### Issue proposée : [P2] Rejouer un scénario E2E live client + marchand + admin pour #527

Body :

- Contexte : l'audit statique et les tests unitaires/fonctionnels ne remplacent pas un parcours navigateur bout en bout.
- Problème : plusieurs points #527 nécessitent un environnement live pour confirmer QR, code retrait, notifications et logs.
- Étapes de reproduction : seed démo, compte client, compte marchand, compte admin, parcours complet jusqu'à retrait.
- Attendu : preuve vidéo/screenshots/logs et liste d'écarts dédiée.
- Critères d'acceptation : rapport E2E actualisé dans `docs/qa`.
- Tests attendus : Playwright ou scénario manuel documenté.
- Hors périmètre : correction des bugs découverts.

## 7. Tests recommandés

Tests backend existants à lancer :

- `docker compose exec backend php bin/phpunit`
- `docker compose exec backend php bin/phpunit tests/Functional/Api/SubmitOrderApiTest.php`
- `docker compose exec backend php bin/phpunit tests/Functional/Api/MerchantOrderApiTest.php`
- `docker compose exec backend php bin/phpunit tests/Functional/Api/MerchantPickupCodeApiTest.php`
- `docker compose exec backend php bin/phpunit tests/Functional/Api/HealthCheckApiTest.php`
- `docker compose exec backend php bin/phpunit tests/Functional/Api/ClientLogTest.php`

Tests frontend existants à lancer :

- `docker compose exec frontend npm run test:run`
- `docker compose exec frontend npm run lint`

Tests manquants à créer :

- E2E live client : inscription, login, recherche supérette, catalogue, Kadhia, créneau, soumission.
- E2E live marchand : acceptation, refus, acceptation partielle, préparation ligne par ligne, ready.
- E2E live retrait : QR, scan marchand, confirmation client, confirmation marchand, code 4 chiffres, validation manuelle.
- E2E admin : création/édition marchand, reset mot de passe temporaire, supérette, checklist activation.
- Tests front tokens mauvais rôle/expirés pour client, marchand et admin.
- Tests de corrélation logs front/backend avec `X-Client-Request-Id`.

Scénarios manuels à exécuter :

- Scanner un QR magasin depuis mobile.
- Créer deux Kadhia draft pour deux supérettes et vérifier le bouton continuer.
- Soumettre deux fois rapidement la même Kadhia.
- Changer de créneau après acceptation partielle.
- Tenter un retrait avec QR expiré, code faux, code valide, validation manuelle.
- Vérifier notifications client et marchand après chaque transition.
- Vérifier les logs backend et front sur une erreur API volontaire.

Vérifications lancées pendant cet audit :

- `docker compose exec frontend npm run lint` : OK, aucun warning/error ESLint.
- `docker compose exec frontend npm run test:run` : OK, 102 fichiers de tests passés, 627 tests passés. Sortie avec warnings React et logs d'erreur simulés par certains tests.
- `docker compose exec backend php bin/phpunit` : KO, 1531 tests, 7236 assertions, 8 failures.
- `docker compose exec backend php bin/phpunit --filter 'MerchantAdminApiTest::testAdminCanGenerateTemporaryPasswordForMerchant|MerchantAdminApiTest::testNonAdminCannotGenerateTemporaryMerchantPassword'` : OK, 2 tests, 15 assertions.
- `docker compose exec backend php bin/phpunit tests/Functional/Api/MerchantAdminApiTest.php` : OK, 37 tests, 214 assertions.

## 8. Conclusion

Ce qui est prêt :

- Le socle MVP est largement implémenté côté backend et frontend.
- Les parcours principaux sont couverts par des ressources API explicites et beaucoup de tests.
- Le frontend passe lint + tests unitaires/composants/services.
- Les domaines Kadhia, commande, créneaux, préparation, QR/code retrait, notifications et healthcheck ont des preuves de code et tests.

Ce qui bloque :

- La suite backend complète n'est pas verte dans l'environnement audité.
- Les failures `temporary-password` sont instables selon exécution complète vs isolée.
- Les résultats QR/share doivent être relus avec la configuration `FRONTEND_URL` commitée, car l'audit a utilisé un override local non commité.

À faire avant prochaine démo :

- Rendre `docker compose exec backend php bin/phpunit` vert ou documenter précisément l'écart d'environnement.
- Relancer les tests QR/share avec la configuration `FRONTEND_URL` commitée avant d'ouvrir une issue produit.
- Rejouer un parcours live client + marchand + admin avec preuves.

Ce qui peut attendre :

- Nettoyage des warnings React de tests.
- Durcissement UX avancé des tokens mal rangés entre portails.
- Audit accessibilité et PWA, sauf si la démo exige explicitement ces sujets.
