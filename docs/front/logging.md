# Logging frontend — Click & Collect Supérette

## Pourquoi logger côté front

Les erreurs navigateur sont souvent invisibles côté backend. Un utilisateur qui plante sur le
parcours Kadhia n'envoie aucune requête — seule une capture front peut révéler le problème.

Ce système permet de :

- détecter les erreurs JavaScript globales (runtime, promesses rejetées) ;
- tracer les échecs d'appels API critiques (création commande, créneaux, scan QR) ;
- corréler un log front avec les logs Monolog backend via un identifiant commun.

## Niveaux de logs

| Niveau | Usage |
|--------|-------|
| `error` | Action front échouée, parcours utilisateur bloqué |
| `warning` | Anomalie surveillée, parcours peut continuer |
| `info` | Événement ponctuel, reste local (console uniquement) |
| `debug` | Développement uniquement, reste local |

En production, seuls `warning` et `error` sont envoyés au backend.

## Événements recommandés

### Erreurs globales (capturées automatiquement)

- `front_runtime_error` — erreur JavaScript non gérée
- `front_unhandled_promise_rejection` — promesse rejetée non gérée

### Authentification

- `auth_login_failed`
- `auth_refresh_failed`
- `auth_logout_failed`

### Parcours client

- `store_search_failed`
- `product_search_failed`
- `add_to_cart_failed`
- `cart_sync_failed`
- `checkout_slot_loading_failed`
- `checkout_slot_unavailable`
- `order_creation_failed`
- `order_status_refresh_failed`

### Parcours marchand

- `merchant_order_accept_failed`
- `merchant_order_refuse_failed`
- `merchant_scan_qr_failed`
- `merchant_catalog_update_failed`
- `merchant_image_upload_failed`

## Comment utiliser clientLogger

```ts
import { clientLog } from '@/lib/logger/clientLogger';

// Erreur bloquante
clientLog('error', 'order_creation_failed', 'POST /api/me/orders returned 500', {
  route: '/checkout',
  userRole: 'client',
  requestId: error.config?.headers?.['X-Client-Request-Id'],
  statusCode: 500,
  durationMs: 842,
});

// Avertissement non bloquant
clientLog('warning', 'checkout_slot_unavailable', 'Selected slot is no longer available', {
  route: '/checkout',
  merchantId: 'mer-123',
});
```

## Données autorisées dans le contexte

```
route, userRole, userId, merchantId, orderId,
requestId, statusCode, durationMs,
appVersion, environment, url, createdAt
```

## Données interdites

Ne jamais inclure dans les logs :

```
password, token, jwt, secret, otp, authorization,
refreshToken, apiKey, numéro de téléphone complet,
email complet, adresse complète, contenu libre saisi,
body complet d'une requête, headers complets,
contenu brut d'un fichier importé
```

Le service `clientLogger` supprime automatiquement les clés interdites connues, mais
la règle s'applique aussi aux clés personnalisées que tu ajoutes au contexte.

## Corrélation avec le backend via X-Client-Request-Id

Chaque requête Axios envoyée par `apiClient` porte un header :

```
X-Client-Request-Id: <uuid v4 généré par crypto.randomUUID()>
```

Ce header est automatiquement ajouté par le request interceptor dans `src/lib/api.ts`.

Côté backend, un `CorrelationIdSubscriber` lit ce header sur chaque requête et l'injecte
dans le contexte Monolog via `CorrelationIdProcessor`. Tous les logs Monolog de la requête
contiendront donc `extra.correlation_id`.

Pour corréler un log front avec les logs backend, inclure ce request ID dans le contexte :

```ts
catch (err) {
  clientLog('error', 'checkout_slot_loading_failed', 'Failed to load slots', {
    requestId: (err as AxiosError).config?.headers?.['X-Client-Request-Id'] as string,
    statusCode: (err as AxiosError).response?.status,
  });
}
```

Résultat attendu :

```
[front] checkout_slot_loading_failed requestId=req-abc → envoyé au backend
[backend] Monolog — correlation_id=req-abc sur l'appel /api/stores/{id}/pickup-slots
```

## Channel Monolog dédié

Les logs front reçus sur `POST /api/client-logs` sont écrits dans le channel `front`.

En production, ils sont dans :

```
var/log/front.prod.log   (niveau minimum : warning)
```

En développement, ils remontent dans le handler `main` (tous les niveaux).

## Lien avec l'issue #177

Ce système complète l'instrumentation Monolog backend introduite dans l'issue #177.
L'objectif est de pouvoir tracer un incident de bout en bout :

```
log front checkout_slot_loading_failed requestId=req-abc
  → requête GET /api/stores/{id}/pickup-slots X-Client-Request-Id=req-abc
  → log Monolog backend correlation_id=req-abc
  → exception ou service concerné
```
