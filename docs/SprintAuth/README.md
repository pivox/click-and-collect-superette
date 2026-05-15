# Sprint Auth — Authentification et compte client

## Statut global

**Statut backend : ✅ terminé.**

Sprint Auth livre le socle client minimal requis pour reprendre le parcours client et le retrait sécurisé sur une base authentifiée : inscription client publique, connexion JWT, profil client connecté et réinitialisation de mot de passe.

Le sprint reste strictement MVP : pas d'inscription marchand publique, pas d'administration des comptes, pas de SSO, pas d'OAuth, pas de 2FA, pas de gestion avancée des appareils connectés.

---

## Position dans la roadmap

| US | Sujet | Priorité | Statut | PR |
| --- | --- | --- | --- | --- |
| US-034 | S'inscrire en tant que client | P0 | ✅ Livré | #72 |
| US-035 | Consulter et modifier son profil client | P0 | ✅ Livré | #73 |
| US-046 | Réinitialiser son mot de passe oublié | P0 | ✅ Livré | #74 |
| AUTH-004 | Documentation et audit sécurité Auth | P0 | ✅ Cette PR | Cette PR |

Sprint Auth était prioritaire avant la reprise complète de Sprint 4. Le backend Auth est maintenant considéré prêt pour la suite MVP.

---

## Endpoints livrés

Routes vérifiées avec `php bin/console debug:router --env=test`.

### Auth publique

```http
POST /api/auth/register/customer
POST /api/auth/login
```

`POST /api/auth/login` est la route JWT existante. Un compte créé par `POST /api/auth/register/customer` peut se connecter via ce mécanisme.

### Profil client connecté

```http
GET   /api/me/profile
PATCH /api/me/profile
```

Ces routes sont réservées à `ROLE_CUSTOMER`.

### Mot de passe oublié

Routes canoniques :

```http
POST /api/auth/password-reset/request
POST /api/auth/password-reset/confirm
```

Alias documentés et exposés :

```http
POST /api/auth/forgot-password
POST /api/auth/reset-password
```

---

## Contrats API livrés

### POST /api/auth/register/customer

Body :

```json
{
  "email": "client@example.com",
  "password": "secret123",
  "first_name": "Haythem",
  "last_name": "Mabrouk",
  "phone": "+21600000000"
}
```

Le champ `name` reste accepté pour compatibilité documentaire, mais le contrat recommandé utilise `first_name` et `last_name`.

Réponse `201` :

```json
{
  "token": "<jwt>",
  "user": {
    "id": "user-uuid",
    "email": "client@example.com",
    "roles": ["ROLE_CUSTOMER", "ROLE_USER"],
    "first_name": "Haythem",
    "last_name": "Mabrouk",
    "name": "Haythem Mabrouk",
    "phone": "+21600000000"
  }
}
```

Règles livrées :

- création publique limitée à `ROLE_CUSTOMER` ;
- `ROLE_USER` est ajouté par le modèle Symfony comme rôle de base ;
- `roles`, `id`, `password_hash` et champs internes du payload ne permettent aucune élévation de privilèges ;
- email trimé, normalisé en minuscules et unique ;
- mot de passe hashé, jamais retourné par l'API ;
- connexion JWT validée après inscription.

Erreurs couvertes :

| Cas | HTTP |
| --- | --- |
| Email invalide | 422 |
| Mot de passe vide ou trop faible | 422 |
| Email déjà utilisé | 409 |
| Payload invalide | 422 |

---

### GET /api/me/profile

Réponse `200` :

```json
{
  "id": "user-uuid",
  "email": "client@example.com",
  "roles": ["ROLE_CUSTOMER", "ROLE_USER"],
  "first_name": "Haythem",
  "last_name": "Mabrouk",
  "name": "Haythem Mabrouk",
  "phone": "+21600000000"
}
```

Règles livrées :

- accès réservé à `ROLE_CUSTOMER` ;
- réponse limitée à l'utilisateur authentifié ;
- aucun `userId` accepté dans l'URL ;
- aucun champ sensible retourné : mot de passe, hash, token de reset, champs internes.

---

### PATCH /api/me/profile

Body recommandé :

```json
{
  "first_name": "Haythem",
  "last_name": "Mabrouk",
  "phone": "+21611111111"
}
```

Réponse `200` :

```json
{
  "id": "user-uuid",
  "email": "client@example.com",
  "roles": ["ROLE_CUSTOMER", "ROLE_USER"],
  "first_name": "Haythem",
  "last_name": "Mabrouk",
  "name": "Haythem Mabrouk",
  "phone": "+21611111111"
}
```

Champs modifiables :

- `first_name` ;
- `last_name` ;
- `name` pour compatibilité ;
- `phone`.

Champs non modifiables dans Sprint Auth :

- `id` ;
- `email` ;
- `roles` ;
- `password` ;
- champs d'audit et états internes.

---

### POST /api/auth/password-reset/request

Alias : `POST /api/auth/forgot-password`.

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

Règles livrées :

- réponse neutre pour email existant ou inexistant ;
- email trimé et normalisé avant recherche ;
- token créé uniquement pour un compte client existant ;
- un nouveau reset consomme les anciens tokens actifs du même utilisateur ;
- token brut transmis au service d'envoi, token hashé en base.

---

### POST /api/auth/password-reset/confirm

Alias : `POST /api/auth/reset-password`.

Body :

```json
{
  "token": "reset-token-opaque",
  "new_password": "newSecret123"
}
```

Réponse : `204 No Content`.

Règles livrées :

- token opaque hashé avec SHA-256 avant recherche ;
- token expiré après 1 heure par défaut (`app.password_reset_token_ttl: 3600`) ;
- token à usage unique ;
- token inconnu, expiré ou déjà consommé refusé ;
- nouveau mot de passe hashé avec le password hasher Symfony ;
- connexion possible avec le nouveau mot de passe ;
- ancien mot de passe refusé après reset.

---

## Modèle de données

### User

Sprint Auth réutilise `User` et expose uniquement la représentation client publique :

- `id` ;
- `email` ;
- `roles` ;
- `first_name` ;
- `last_name` ;
- `name` ;
- `phone`.

Les champs internes (`password`, hash, champs d'audit non utiles à l'UI) ne sont pas sérialisés dans les réponses Auth.

### PasswordResetToken

Entité livrée par AUTH-003 :

| Champ | Règle |
| --- | --- |
| `id` | UUID technique |
| `user` | relation obligatoire vers `User` |
| `tokenHash` | unique, hash du token brut |
| `expiresAt` | expiration obligatoire |
| `consumedAt` | renseigné après consommation ou invalidation |
| `createdAt` | date de création |

Index livrés :

- `user_id`, `consumed_at` pour les tokens actifs d'un utilisateur ;
- `expires_at` pour le suivi des expirations.

---

## Tests Auth réels

| Zone | Fichier |
| --- | --- |
| Inscription client | `apps/backend/tests/Functional/Api/CustomerRegistrationApiTest.php` |
| Profil client | `apps/backend/tests/Functional/Api/CustomerProfileApiTest.php` |
| Reset password | `apps/backend/tests/Functional/Api/PasswordResetApiTest.php` |

Ces tests couvrent notamment :

- inscription client valide ;
- rôle forcé `ROLE_CUSTOMER` et absence d'élévation vers `ROLE_ADMIN` / `ROLE_MERCHANT` ;
- non-exposition du mot de passe et du hash ;
- email normalisé et unique ;
- login JWT après inscription ;
- accès profil réservé à `ROLE_CUSTOMER` ;
- PATCH profil limité aux champs autorisés ;
- demande de reset neutre ;
- création de token uniquement pour les comptes clients ;
- stockage hashé du token ;
- expiration, consommation unique et invalidation des anciens tokens ;
- login avec nouveau mot de passe et refus de l'ancien après reset.

---

## Limites connues

- L'envoi réel du mail de reset reste minimal : `NativePasswordResetTokenSender` utilise l'envoi natif PHP tant qu'une infrastructure mailer produit n'est pas configurée.
- Aucun template email avancé n'est livré.
- Le changement d'email connecté est hors périmètre.
- Le changement de mot de passe depuis un profil connecté est hors périmètre.
- La suppression de compte client est hors périmètre.
- L'inscription marchand publique, OAuth, 2FA, SMS, WhatsApp, push mobile et gestion avancée des sessions restent hors périmètre MVP Auth.

---

## Définition de fini Sprint Auth

Le backend Sprint Auth est terminé lorsque :

1. un visiteur peut créer un compte client via `POST /api/auth/register/customer` ;
2. le compte créé possède `ROLE_CUSTOMER` sans élévation possible par payload ;
3. le client peut se connecter avec le JWT existant ;
4. le client peut consulter son profil via `GET /api/me/profile` ;
5. le client peut modifier les champs autorisés via `PATCH /api/me/profile` ;
6. le client peut demander une réinitialisation de mot de passe sans fuite d'information ;
7. le client peut définir un nouveau mot de passe avec un token valide ;
8. les tokens de reset sont expirables, hashés et à usage unique ;
9. les champs sensibles ne sont jamais retournés par l'API ;
10. les tests fonctionnels Auth couvrent happy paths, erreurs métier, sécurité et non-régression JWT ;
11. la documentation API et la roadmap sont alignées.

Ces critères sont validés par AUTH-004, sous réserve de la limite connue sur l'infrastructure d'envoi email production.
