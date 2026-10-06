# Issue mobile #26 — Révocation des sessions et concurrence

Le changement de mot de passe sélectionne les refresh tokens actifs pour les
révoquer. Une rotation concurrente peut avoir lu les anciens identifiants puis
persister son successeur après cette sélection : ce successeur échappait à la
révocation et permettait de prolonger la session.

Chaque `RefreshToken` conserve désormais une empreinte SHA-256 du hash de mot de
passe présent au moment de sa construction. Cette empreinte reste interne au
backend ; elle n'est ni renvoyée ni journalisée. À chaque rotation, elle est
comparée aux identifiants courants du compte. Une divergence ou une empreinte
absente révoque la famille concernée et renvoie le code générique existant
`AUTH_REFRESH_TOKEN_INVALID` (401), sans révoquer une nouvelle connexion valide
appartenant à une autre famille.

Une émission qui utilise un utilisateur chargé avant le changement conserve
l'ancienne empreinte : son token tardif échoue à la rotation suivante. Cela
couvre aussi l'établissement d'un mot de passe local sur un compte initialement
social. Les JWT déjà émis gardent leur durée de vie existante ; le contrôle
séparé des identifiants dans l'association sociale empêche leur utilisation
comme preuve après un changement de mot de passe.

## Migration et déploiement

`Version20261003130000` ajoute `refresh_tokens.credential_hash` et révoque les
anciens tokens sans empreinte. L'état actuel d'un compte ne prouve pas les
identifiants utilisés pour émettre un ancien token : aucun backfill n'est donc
inventé. Une reconnexion sera nécessaire lors du prochain refresh des sessions
mobiles préexistantes. Appliquer la migration avant le code qui lit la colonne.
Le rollback de schéma ne réactive jamais les tokens révoqués.

## Vérifications

Deux tests fonctionnels ont échoué avant correction : réponse 200 obtenue au
lieu de 401 pour un token omis par la révocation et pour un successeur dont la
persistance est retardée. Ils rejouent explicitement l'ordre des opérations ;
ils ne constituent pas un test de charge avec deux processus parallèles.

Les suites ciblées couvrent la rotation normale, le rejeu, la révocation,
les comptes inactifs/supprimés, le reset, les tokens anciens sans empreinte,
l'isolation des nouvelles connexions et les comptes sociaux sans mot de passe.
Le test de migration exécute les requêtes `up`/`down` dans une base SQLite
en mémoire et vérifie les timestamps de révocation.

Commandes (depuis la racine, via Docker Compose) :

```bash
docker compose exec -T backend php bin/phpunit tests/Unit/Entity/RefreshTokenTest.php tests/Unit/Service/RefreshTokenManagerTest.php tests/Unit/Migration/RefreshTokenCredentialMigrationTest.php
docker compose exec -T backend php bin/phpunit tests/Functional/Api/AuthRefreshTokenApiTest.php tests/Functional/Api/PasswordResetApiTest.php
docker compose exec -T backend vendor/bin/phpstan analyse src/Entity/RefreshToken.php src/Processor/AuthRefreshTokenProcessor.php --no-progress
```

Résultats unitaires/migration : 14 tests, 37 assertions. Suites API refresh et
reset : 43 tests, 268 assertions. PHPStan ciblé : aucune erreur ; CS Fixer ciblé
sur six fichiers : aucun changement demandé.
