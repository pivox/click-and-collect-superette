# Rapport de clôture Sprint Auth

## Résumé

Sprint Auth clôture le socle compte client du MVP Click & Collect Supérette Tunisie.

Le backend permet maintenant à un visiteur de créer un compte client, de se connecter avec JWT, de consulter et modifier son profil client, puis de récupérer l'accès à son compte via un reset password à token opaque.

Le sprint ne démarre aucune fonctionnalité Sprint 4. Il stabilise uniquement les prérequis Auth nécessaires à la reprise du parcours client, de la Kadhia, des commandes et du retrait sécurisé.

---

## PR livrées

| PR | Sujet | Statut |
| --- | --- | --- |
| #71 | Documentation fondation Sprint Auth | Mergée |
| #72 | AUTH-001 — Inscription client | Mergée |
| #73 | AUTH-002 — Profil client connecté | Mergée |
| #74 | AUTH-003 — Reset password | Mergée |
| Cette PR | AUTH-004 — Audit et clôture documentaire | En cours |

---

## Endpoints vérifiés

Routes vérifiées avec `php bin/console debug:router --env=test`.

| Méthode | Route | Accès | Réponse attendue |
| --- | --- | --- | --- |
| POST | `/api/auth/register/customer` | Public | `201` avec JWT et utilisateur public |
| POST | `/api/auth/login` | Public | `200` avec JWT Lexik |
| GET | `/api/me/profile` | `ROLE_CUSTOMER` | `200` profil client |
| PATCH | `/api/me/profile` | `ROLE_CUSTOMER` | `200` profil client mis à jour |
| POST | `/api/auth/password-reset/request` | Public | `202` réponse neutre |
| POST | `/api/auth/password-reset/confirm` | Public | `204 No Content` |
| POST | `/api/auth/forgot-password` | Public | `202` alias documenté |
| POST | `/api/auth/reset-password` | Public | `204 No Content` alias documenté |

---

## Règles métier validées

### Inscription client

- L'inscription publique crée uniquement un compte client.
- Le rôle métier est forcé à `ROLE_CUSTOMER`.
- Le payload ne permet pas de choisir `ROLE_ADMIN` ou `ROLE_MERCHANT`.
- L'email est trimé, normalisé en minuscules et unique.
- Le mot de passe est hashé par le password hasher Symfony.
- Le mot de passe et son hash ne sont jamais retournés.
- Le compte créé peut se connecter via `POST /api/auth/login`.

### Profil client

- `/api/me/profile` est réservé à `ROLE_CUSTOMER`.
- Le profil retourné est celui de l'utilisateur authentifié.
- Aucun identifiant utilisateur n'est accepté dans l'URL.
- `PATCH /api/me/profile` modifie uniquement `first_name`, `last_name`, `name` et `phone`.
- `id`, `email`, `roles` et `password` ne sont pas modifiables par le profil.
- Aucun champ sensible n'est sérialisé.

### Reset password

- La demande retourne toujours `202`, même si l'email n'existe pas.
- L'email est normalisé avant recherche.
- Un token est créé uniquement pour un compte client existant.
- Le token brut n'est pas stocké en base.
- Le token hashé est unique.
- Le token expire après 1 heure par défaut.
- Le token est à usage unique.
- Un nouveau reset consomme les anciens tokens actifs du même utilisateur.
- Le nouveau mot de passe remplace l'ancien hash.
- Le nouveau mot de passe permet la connexion.
- L'ancien mot de passe ne permet plus la connexion après confirmation.

---

## Décisions sécurité

- Les routes Auth publiques sont explicitement listées dans `security.yaml`.
- Les routes `/api/me/*` restent protégées par `ROLE_CUSTOMER`.
- Les routes marchand et admin ne sont pas modifiées.
- `PasswordResetToken.tokenHash` contient uniquement un hash SHA-256 du token opaque.
- Le token brut n'est transmis qu'au service d'envoi.
- En test, l'envoi est remplacé par `TestPasswordResetTokenSender` pour vérifier la livraison sans dépendre d'une infrastructure email.
- La réponse de reset request reste neutre pour éviter l'énumération d'emails.

---

## Vérifications exécutées

Commandes exécutées pour AUTH-004 :

| Commande | Résultat |
| --- | --- |
| `php bin/console debug:router --env=test \| rg "auth\|profile\|password-reset\|login"` | OK, routes Auth listées |
| `php bin/phpunit tests/Functional/Api/CustomerRegistrationApiTest.php` | OK, 15 tests, 61 assertions |
| `php bin/phpunit tests/Functional/Api/CustomerProfileApiTest.php` | OK, 17 tests, 84 assertions |
| `php bin/phpunit tests/Functional/Api/PasswordResetApiTest.php` | OK, 18 tests, 79 assertions |
| `php bin/phpunit` | OK, 497 tests, 1918 assertions, 20 notices PHPUnit |
| `vendor/bin/phpstan analyse --memory-limit=512M` | OK, no errors |
| `vendor/bin/php-cs-fixer fix --dry-run --diff` | OK, no diff ; avertissement local PHP 8.4 vs contrainte projet PHP 8.2 |
| `git diff --check` | OK |

Les logs `[error]` visibles pendant PHPUnit correspondent aux cas négatifs attendus par les tests fonctionnels.

```bash
cd apps/backend
php bin/console debug:router --env=test | rg "auth|profile|password-reset|login"
php bin/phpunit tests/Functional/Api/CustomerRegistrationApiTest.php
php bin/phpunit tests/Functional/Api/CustomerProfileApiTest.php
php bin/phpunit tests/Functional/Api/PasswordResetApiTest.php
php bin/phpunit
vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run --diff
git diff --check
```

---

## Hors périmètre

- Inscription marchand publique.
- Gestion admin des comptes.
- Changement d'email connecté.
- Changement de mot de passe depuis profil connecté.
- Suppression de compte.
- OAuth, SSO, 2FA.
- Gestion avancée des sessions ou appareils.
- Templates email avancés.
- SMS, WhatsApp, push mobile.
- Fonctionnalités Sprint 4.

---

## Limites restantes

- L'envoi email de reset est volontairement minimal côté backend : le service de production actuel utilise l'envoi natif PHP tant qu'un mailer produit n'est pas configuré.
- La documentation ne considère pas les notifications client/marchand comme livrées par Sprint Auth.
- Les sujets de retrait sécurisé, QR de retrait, double validation et finalisation de commande restent Sprint 4.

---

## Suite recommandée

Reprendre Sprint 4 sur une base Auth propre :

1. relier les parcours client existants à des comptes réels plutôt qu'aux fixtures ;
2. finaliser les sessions de retrait ;
3. sécuriser le QR code de retrait ;
4. ajouter la double validation client + marchand ;
5. finaliser le statut `completed`.
