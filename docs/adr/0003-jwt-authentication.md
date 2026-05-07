# ADR 0003 — Authentification JWT stateless

## Statut

Accepté.

## Contexte

L'API doit sécuriser trois rôles distincts : client (`ROLE_CUSTOMER`), marchand (`ROLE_MERCHANT`), administrateur (`ROLE_ADMIN`).

Le frontend est une PWA servie séparément du backend. L'authentification doit fonctionner sans état serveur (stateless) pour simplifier le déploiement et la scalabilité future.

## Décision

L'authentification est implémentée avec **JSON Web Tokens (JWT)** via `lexik/jwt-authentication-bundle`.

## Flux d'authentification

```
POST /api/auth/login
  { email, password }
  → { token: "eyJ...", expires_in: 3600 }

Toutes les requêtes protégées :
  Authorization: Bearer eyJ...
```

## Configuration

- **Durée du token** : 3600 secondes (1 heure).
- **Algorithme** : RS256 (clé privée / clé publique RSA).
- **Clés** : générées localement avec `make jwt-keys`, jamais committées dans le dépôt.
- **Refresh token** : non implémenté dans le MVP — le client se reconnecte à expiration.

## Routes publiques (sans token)

- `POST /api/auth/login`
- `GET /api/stores/by-qr/{token}` — accès QR code magasin
- `GET /api/stores/{id}/catalog` — catalogue visible
- `GET /api/stores/{storeId}/theme` — thème visuel de la supérette (voir ADR-0004)

## Séparation des rôles

| Rôle | Préfixe API | Attribution |
|---|---|---|
| `ROLE_CUSTOMER` | `/api/orders`, `/api/kadhia` | Auto à l'inscription client |
| `ROLE_MERCHANT` | `/api/merchant/**` | Attribué par l'admin |
| `ROLE_ADMIN` | `/api/admin/**` | Compte admin plateforme |

## Justification vs alternatives

| Option | Raison d'écart |
|---|---|
| Session cookie | Non adapté à une API consommée par PWA et futures apps mobiles |
| OAuth2 / SSO | Sur-ingénierie pour le MVP. Peut être ajouté en post-MVP |
| API Key simple | Pas de notion de rôle ni d'expiration native |

## Conséquences

- Les clés JWT (`config/jwt/private.pem` et `public.pem`) doivent être générées sur chaque environnement avec `make jwt-keys`.
- Le fichier `config/jwt/` est dans `.gitignore`.
- En cas de compromission d'un token, il n'est pas révocable avant expiration dans le MVP (à traiter via refresh tokens ou une liste noire en post-MVP).
- Le frontend stocke le token en `localStorage` (acceptable pour le MVP — migrer vers `httpOnly cookie` en production si l'exposition XSS devient un risque identifié).
