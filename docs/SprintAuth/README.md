# Sprint Auth — Authentification et compte client

## Statut global

**Statut backend : à coder.**

Sprint Auth livre le minimum nécessaire pour qu'un visiteur devienne un client autonome : inscription, connexion, consultation/modification de son profil et récupération de mot de passe.

Ce sprint est prioritaire avant la suite fonctionnelle du parcours client et du retrait sécurisé : sans compte client réel, les parcours Kadhia, commande, QR de retrait et notifications reposent encore trop sur des comptes créés manuellement ou des fixtures de démonstration.

Le sprint reste strictement MVP : pas d'inscription marchand publique, pas d'administration des comptes, pas de SSO, pas d'OAuth, pas de 2FA, pas de gestion avancée des appareils connectés.

---

## Objectif produit

Permettre à un client final de :

1. créer son compte ;
2. se connecter avec son email et son mot de passe ;
3. consulter son profil ;
4. modifier ses informations personnelles utiles au click & collect ;
5. demander une réinitialisation de mot de passe ;
6. définir un nouveau mot de passe via un token temporaire.

---

## Position dans la roadmap

Sprint Auth couvre les user stories :

| US | Sujet | Priorité | Statut |
| --- | --- | --- | --- |
| US-034 | S'inscrire en tant que client | P0 | À coder |
| US-035 | Consulter et modifier son profil client | P0 | À coder |
| US-046 | Réinitialiser son mot de passe oublié | P0 | À coder |

Sprint Auth doit être traité avant la reprise complète de Sprint 4, même si la base `PickupSession` / `Notification` a déjà été amorcée.

---

## Parcours cible

```text
Visiteur
-> inscription client
-> connexion JWT
-> accès à /api/me/profile
-> modification éventuelle du profil
-> parcours Kadhia / commande / retrait sécurisé
```

Parcours mot de passe oublié :

```text
Client oublie son mot de passe
-> demande de reset avec son email
-> réponse neutre, même si l'email n'existe pas
-> réception d'un lien contenant un token temporaire
-> saisie du nouveau mot de passe
-> token consommé
-> connexion possible avec le nouveau mot de passe
```

---

## Décisions produit

- L'inscription publique crée uniquement des comptes `ROLE_CUSTOMER`.
- Aucun endpoint public ne permet de créer un marchand ou un administrateur.
- L'email est l'identifiant de connexion.
- L'email doit être unique, trimé et normalisé en minuscules avant persistance et authentification.
- La règle MVP de mot de passe est identique pour l'inscription et le reset : minimum 8 caractères.
- Les contrats JSON de ce sprint utilisent les noms publics déjà documentés (`name`, `phone`, `new_password`). Si une propriété PHP nécessite un nom JSON différent, l'implémentation doit utiliser `#[SerializedName]` explicitement plutôt que compter sur un `NameConverter` global implicite.
- La réponse de demande de reset password ne doit jamais révéler si un email existe.
- Le profil client appartient strictement à l'utilisateur connecté.
- Le client ne peut pas modifier son rôle, son identifiant technique, son email de façon implicite, ni ses états internes.
- Le changement d'email est hors périmètre MVP initial, sauf décision explicite ultérieure.
- Les notifications de reset utilisent l'email ; SMS, WhatsApp et push mobile sont hors périmètre.

---

## Endpoints cibles

### Auth publique

```http
POST /api/auth/register/customer
POST /api/auth/login
```

`POST /api/auth/login` est la route JWT configurée dans le backend actuel. Sprint Auth doit garantir que le compte créé par `register/customer` peut se connecter avec ce mécanisme.

### Profil client connecté

```http
GET   /api/me/profile
PATCH /api/me/profile
```

### Mot de passe oublié

```http
POST /api/auth/forgot-password
POST /api/auth/reset-password
```

---

## Contrats API proposés

### POST /api/auth/register/customer

Body :

```json
{
  "email": "client@example.com",
  "password": "secret123",
  "name": "Haythem Mabrouk",
  "phone": "+21600000000"
}
```

Réponse `201` :

```json
{
  "token": "<jwt>",
  "user": {
    "id": "user-uuid",
    "email": "client@example.com",
    "name": "Haythem Mabrouk",
    "phone": "+21600000000"
  }
}
```

Erreurs attendues :

| Cas | HTTP | Code métier |
| --- | --- | --- |
| Email invalide | 422 | `AUTH_INVALID_EMAIL` |
| Mot de passe trop faible | 422 | `AUTH_WEAK_PASSWORD` |
| Email déjà utilisé | 409 | `AUTH_EMAIL_ALREADY_EXISTS` |
| Payload invalide | 422 | `VALIDATION_FAILED` |

---

### GET /api/me/profile

Réponse `200` :

```json
{
  "id": "user-uuid",
  "email": "client@example.com",
  "name": "Haythem Mabrouk",
  "phone": "+21600000000"
}
```

Règles :

- accès réservé à un utilisateur connecté ;
- réponse limitée au profil de l'utilisateur courant ;
- aucun champ sensible retourné : mot de passe hashé, reset token, champs internes.

---

### PATCH /api/me/profile

Body :

```json
{
  "name": "Haythem Mabrouk",
  "phone": "+21611111111"
}
```

Réponse `200` :

```json
{
  "id": "user-uuid",
  "email": "client@example.com",
  "name": "Haythem Mabrouk",
  "phone": "+21611111111"
}
```

Champs modifiables MVP :

- `name` ;
- `phone`.

Champs non modifiables dans ce sprint :

- `email` ;
- `roles` ;
- `password` ;
- `id` ;
- tout champ d'audit ou d'état interne.

---

### POST /api/auth/forgot-password

Body :

```json
{
  "email": "client@example.com"
}
```

Réponse `202` neutre :

```json
{
  "message": "Si un compte existe pour cet email, un lien de réinitialisation sera envoyé."
}
```

Règles :

- toujours retourner une réponse neutre ;
- ne pas révéler si l'email existe ;
- créer un token uniquement si un utilisateur existe ;
- invalider les anciens tokens actifs du même utilisateur ;
- envoyer un email de reset en environnement où le mailer est configuré.

---

### POST /api/auth/reset-password

Body :

```json
{
  "token": "reset-token-opaque",
  "new_password": "newSecret123"
}
```

Réponse `204` sans body.

Erreurs attendues :

| Cas | HTTP | Code métier |
| --- | --- | --- |
| Token inconnu | 400 | `AUTH_RESET_TOKEN_INVALID` |
| Token expiré | 400 | `AUTH_RESET_TOKEN_EXPIRED` |
| Token déjà utilisé | 400 | `AUTH_RESET_TOKEN_ALREADY_USED` |
| Mot de passe trop faible | 422 | `AUTH_WEAK_PASSWORD` |

---

## Modèle de données

### User existant

Sprint Auth doit réutiliser l'entité `User` existante.

Champs attendus côté profil client :

- `id` ;
- `email` ;
- `roles` ;
- `password` / hash interne ;
- `name` ;
- `phone` ;
- `createdAt` ;
- `updatedAt`.

L'entité `User` actuelle possède déjà `name`, `phone`, `createdAt` et `updatedAt`. AUTH-001 ne doit donc pas introduire `firstName` / `lastName` ni migrationner le profil client sans décision produit explicite.

### PasswordResetToken

Nouvelle entité proposée : `PasswordResetToken`.

Champs :

| Champ | Type | Règle |
| --- | --- | --- |
| `id` | UUID | Identifiant technique |
| `user` | ManyToOne `User` | Utilisateur concerné |
| `tokenHash` | string unique | Hash du token, jamais le token brut |
| `expiresAt` | datetime immutable | Expiration courte, par défaut 1 heure |
| `consumedAt` | datetime immutable nullable | Renseigné après utilisation |
| `createdAt` | datetime immutable | Création du token |

Décision sécurité : le token brut est envoyé uniquement par email et n'est jamais stocké en clair en base.

La durée d'expiration doit être configurable par environnement, par exemple via un paramètre Symfony `app.password_reset_token_ttl` exprimé en secondes, avec une valeur MVP par défaut de 3600 secondes.

---

## Règles métier à tester

### Inscription

- Un visiteur peut créer un compte client valide.
- Le compte créé possède uniquement `ROLE_CUSTOMER`.
- Le mot de passe est hashé.
- L'email est normalisé en minuscules.
- Deux comptes ne peuvent pas partager le même email.
- Un payload contenant `roles`, `id` ou tout champ interdit ne permet pas d'élever les privilèges.
- Le compte créé peut se connecter via le flux JWT existant.

### Profil

- Un client connecté peut lire son profil.
- Un client connecté peut modifier les champs autorisés.
- Un utilisateur anonyme reçoit `401`.
- Un utilisateur connecté ne peut jamais lire ou modifier le profil d'un autre utilisateur via `/api/me/profile`.
- Les champs sensibles ne sont jamais sérialisés.

### Mot de passe oublié

- La demande de reset retourne toujours `202`, email existant ou non.
- Un token est créé uniquement pour un email existant.
- Le token expire après la durée configurée.
- Le token est à usage unique.
- Un token consommé ne peut pas être rejoué.
- Un nouveau reset invalide les anciens tokens actifs.
- Le nouveau mot de passe permet la connexion.
- L'ancien mot de passe ne permet plus la connexion.

---

## Tests attendus

| Zone | Test cible |
| --- | --- |
| API inscription | `CustomerRegistrationApiTest` |
| API profil | `CustomerProfileApiTest` |
| API reset password | `PasswordResetApiTest` |
| Domaine token | `PasswordResetTokenTest` ou test Doctrine dédié |
| Sécurité | tests 401/403, non-exposition des champs sensibles |

Commandes de vérification attendues :

```bash
cd apps/backend
vendor/bin/phpunit tests/Functional/Api/CustomerRegistrationApiTest.php
vendor/bin/phpunit tests/Functional/Api/CustomerProfileApiTest.php
vendor/bin/phpunit tests/Functional/Api/PasswordResetApiTest.php
vendor/bin/phpunit
vendor/bin/phpstan analyse --memory-limit=512M
vendor/bin/php-cs-fixer fix --dry-run --diff
```

---

## Découpage recommandé en PR atomiques

### AUTH-001 — Inscription client

Objectif : exposer `POST /api/auth/register/customer`.

Périmètre :

- DTO d'entrée inscription ;
- processor/service de création client ;
- hash du mot de passe ;
- rôle forcé `ROLE_CUSTOMER` ;
- normalisation email ;
- tests fonctionnels inscription + connexion JWT.

Hors périmètre : profil, reset password, inscription marchand/admin.

---

### AUTH-002 — Profil client connecté

Objectif : exposer `GET/PATCH /api/me/profile`.

Périmètre :

- output profil client ;
- DTO patch profil ;
- provider `/me/profile` ;
- processor de mise à jour ;
- tests sécurité et sérialisation.

Note implémentation API Platform : si `CustomerProfileOutput` est le `#[ApiResource]`, utiliser `fromClass: CustomerProfileOutput::class` dans `uriVariables`. Ne pas pointer vers `User::class`, afin d'éviter les erreurs de génération d'IRI sur un DTO.

Hors périmètre : changement email, changement mot de passe, suppression compte.

---

### AUTH-003 — Password reset foundation

Objectif : ajouter `PasswordResetToken` et le flux de reset.

Périmètre :

- entité `PasswordResetToken` ;
- repository ;
- migration ;
- génération token opaque ;
- stockage hashé ;
- expiration ;
- consommation unique ;
- endpoints `POST /api/auth/forgot-password` et `POST /api/auth/reset-password` ;
- tests fonctionnels et domaine.

Migration : suivre le protocole projet Doctrine. Générer le diff, relire la migration avant commit, valider le schéma, implémenter `up()` et `down()`, et prévoir les index nécessaires sur `user_id` et `expires_at` pour les recherches de tokens actifs ou expirés.

Hors périmètre : templates email avancés, SMS, push, 2FA.

---

### AUTH-004 — Documentation et audit sécurité Auth

Objectif : clôturer Sprint Auth.

Périmètre :

- aligner `docs/roadmap/mvp-roadmap.md` si nécessaire ;
- compléter `docs/architecture/api-contract.md` ;
- rapport de clôture `docs/SprintAuth/completion-report.md` ;
- mettre à jour `AI_CONTEXT.md` avec `PasswordResetToken` dans les entités métier de référence ;
- vérifier les routes ;
- vérifier la non-régression des parcours Sprint 2/3/4 déjà présents.

---

## Hors périmètre Sprint Auth

- Inscription marchand publique.
- Gestion admin des comptes marchands.
- Suspension / activation des comptes.
- Changement d'email.
- Suppression de compte client.
- 2FA.
- OAuth / Google / Apple.
- Gestion des sessions multi-devices.
- Rotation / révocation avancée des JWT.
- Historique des connexions.
- RGPD avancé et purge utilisateur, prévu plus tard dans les sujets production/localisation.

---

## Définition de fini Sprint Auth

Le backend Sprint Auth est considéré terminé lorsque :

1. un visiteur peut créer un compte client via `POST /api/auth/register/customer` ;
2. le compte créé possède uniquement `ROLE_CUSTOMER` ;
3. le client peut se connecter avec le JWT existant ;
4. le client peut consulter son profil via `GET /api/me/profile` ;
5. le client peut modifier les champs autorisés via `PATCH /api/me/profile` ;
6. le client peut demander une réinitialisation de mot de passe sans fuite d'information ;
7. le client peut définir un nouveau mot de passe avec un token valide ;
8. les tokens de reset sont expirables, hashés et à usage unique ;
9. les champs sensibles ne sont jamais retournés par l'API ;
10. les tests fonctionnels couvrent happy paths, erreurs métier, sécurité et non-régression JWT ;
11. la documentation API et la roadmap sont alignées après livraison.
