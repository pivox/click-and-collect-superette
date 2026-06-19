# QA Sprint 15 — Première connexion marchand

Issue : #517 — QA première connexion marchand.

## État des préconditions

- #512 invitation email marchand : mergée via PR #546.
- #513 accès temporaire première connexion marchand : mergée via PR #548.
- #515 écran frontend d'invitation : couvert par la PR dédiée #515.

## Périmètre vérifié

Cette QA couvre les deux mécanismes backend livrés pour la première connexion
marchand :

- invitation email marchand via token opaque, expirant et à usage unique ;
- accès temporaire par mot de passe provisoire, avec changement obligatoire.

Le vocabulaire métier reste inchangé : le marchand finalise son accès avant
d'ouvrir son espace supérette, son catalogue, ses commandes et ses rendez-vous de
retrait.

## Scénarios backend couverts

| Scénario | Couverture |
|---|---|
| Création d'invitation admin | `MerchantInvitationApiTest::testAdminCanCreateMerchantInvitationWithoutExposingSecret` |
| Refus non admin | `MerchantInvitationApiTest::testNonAdminCannotCreateMerchantInvitation` |
| Token hashé et secret non exposé | `MerchantInvitationApiTest::testAdminCanCreateMerchantInvitationWithoutExposingSecret` |
| Vérification token valide | `MerchantInvitationApiTest::testPublicVerifyAcceptsValidInvitationTokenWithoutExposingSecret` |
| Finalisation token valide | `MerchantInvitationApiTest::testMerchantCanCompleteInvitationAndLoginWithDefinitivePassword` |
| Marchand actif après finalisation | `MerchantInvitationApiTest::testMerchantCanCompleteInvitationAndLoginWithDefinitivePassword` |
| Token expiré refusé | `MerchantInvitationApiTest::testExpiredInvitationTokenIsRejected` |
| Token utilisé refusé | `MerchantInvitationApiTest::testUsedInvitationTokenIsRejected` |
| Réutilisation après finalisation refusée | `MerchantInvitationApiTest::testCompletedInvitationTokenCannotBeReusedToChangePasswordAgain` |
| Token révoqué refusé | `MerchantInvitationApiTest::testRevokedInvitationTokenIsRejected` |
| Token inconnu refusé | `MerchantInvitationApiTest::testUnknownInvitationTokenIsRejected` |
| Renvoi révoque l'ancien lien | `MerchantInvitationApiTest::testResendInvitationRevokesPreviousActiveInvitation` |
| Mot de passe provisoire généré | `MerchantAdminApiTest::testAdminCanGenerateTemporaryPasswordForMerchant` |
| Régénération remplace l'accès précédent | `MerchantAdminApiTest::testTemporaryPasswordResetReplacesPreviousTemporaryAccess` |
| Ancien mot de passe remplacé | `MerchantAdminApiTest::testTemporaryPasswordReplacesOldMerchantPasswordForLogin` |
| Audit sans secret | `MerchantAdminApiTest::testTemporaryPasswordResetCreatesAuditLogWithoutPlainPassword` |
| Connexion temporaire expose `password_change_required` | `MerchantFirstLoginApiTest::testLoginAndMerchantMeExposePasswordChangeRequired` |
| Mot de passe temporaire expiré refusé au login | `MerchantFirstLoginApiTest::testExpiredTemporaryPasswordCannotLogin` |
| Expiration non révélée si mauvais mot de passe | `MerchantFirstLoginApiTest::testExpiredTemporaryPasswordLoginDoesNotRevealStateWithWrongPassword` |
| Accès historique sans expiration refusé | `MerchantFirstLoginApiTest::testLegacyRequiredPasswordChangeWithoutTemporaryExpirationIsRejected` |
| Finalisation expirée refusée | `MerchantFirstLoginApiTest::testExpiredTemporaryPasswordCannotBeFinalized` |
| Route métier bloquée avant finalisation | `MerchantFirstLoginApiTest::testMerchantWithRequiredPasswordChangeCannotAccessBusinessEndpoint` |
| Modification compte bloquée avant finalisation | `MerchantFirstLoginApiTest::testMerchantWithRequiredPasswordChangeCannotPatchMerchantMe` |
| Finalisation temporaire valide | `MerchantFirstLoginApiTest::testSuccessfulFirstLoginPasswordChangeClearsFlagAndAllowsBusinessEndpoint` |
| Accès normal après finalisation | `MerchantFirstLoginApiTest::testSuccessfulFirstLoginPasswordChangeClearsFlagAndAllowsBusinessEndpoint` |
| Endpoint première connexion refusé si non requis | `MerchantFirstLoginApiTest::testFirstLoginEndpointRequiresPendingPasswordChange` |

## Scénarios frontend couverts

| Scénario | Couverture |
|---|---|
| Écran invitation avec token valide | `merchant.invitation.test.tsx` |
| Écran invitation token manquant | `merchant.invitation.test.tsx` |
| Écran invitation token expiré | `merchant.invitation.test.tsx` |
| Écran invitation token utilisé | `merchant.invitation.test.tsx` |
| Écran invitation token révoqué | `merchant.invitation.test.tsx` |
| Écran invitation token invalide | `merchant.invitation.test.tsx` |
| Validation mot de passe invitation | `merchant.invitation.test.tsx` |
| Finalisation invitation et redirection login | `merchant.invitation.test.tsx` |
| Appels API invitation publics sans redirection auth | `merchant.invitation.service.test.ts` |
| Écran mot de passe provisoire affiché | `merchant.first-login.test.tsx` |
| Déconnexion depuis première connexion | `merchant.first-login.test.tsx` |
| Validation confirmation différente | `merchant.first-login.test.tsx` |
| Validation longueur mot de passe | `merchant.first-login.test.tsx` |
| Erreur API 422 contextualisée | `merchant.first-login.test.tsx` |
| Erreur API inattendue générique | `merchant.first-login.test.tsx` |
| Succès, purge champs et redirection dashboard | `merchant.first-login.test.tsx` |
| Redirection après login avec `password_change_required` | `merchant.auth-first-login.test.tsx` |
| Redirection depuis dashboard marchand bloqué | `merchant.auth-first-login.test.tsx` |
| Intercepteur 403 sans boucle sur `/merchant/premiere-connexion` | `api.interceptor.test.ts` |

## Complément #515

La PR #515 ajoute l'écran frontend d'invitation email et les tests associés. Le
rapport QA couvre désormais les deux modes de première connexion côté frontend :
invitation email et mot de passe provisoire.

## Commandes de vérification exécutées

```bash
docker compose exec backend php bin/phpunit tests/Functional/Api/MerchantInvitationApiTest.php tests/Functional/Api/MerchantFirstLoginApiTest.php tests/Functional/Api/MerchantAdminApiTest.php
docker compose run --rm frontend npm run test:run -- src/tests/merchant.invitation.test.tsx src/tests/merchant.invitation.service.test.ts src/tests/merchant.first-login.test.tsx src/tests/merchant.auth-first-login.test.tsx src/tests/api.interceptor.test.ts
docker compose exec backend vendor/bin/php-cs-fixer fix --dry-run --diff tests/Functional/Api/MerchantInvitationApiTest.php
docker compose run --rm frontend npm run lint
```

Note d'environnement : `docker compose exec backend composer install` puis
`make jwt-keys` ont été nécessaires localement pour restaurer les dépendances de
test et les clés JWT ignorées par Git avant l'exécution PHPUnit.
