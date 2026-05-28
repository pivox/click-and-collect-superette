# Design — Retrait par code 4 chiffres (issue #193)

**Date :** 2026-05-28  
**Statut :** Approuvé  
**Issue :** https://github.com/pivox/click-and-collect-superette/issues/193

---

## Contexte

Le retrait par QR code est le parcours principal. Quand le QR ne peut pas être scanné (caméra défaillante, client sans connexion, batterie vide), le retrait est bloqué.

Cette feature introduit **3 modes de validation retrait** pour le marchand :

| Mode | Statut | Description |
|------|--------|-------------|
| QR code | Existant | Scan du token UUID via caméra |
| Code 4 chiffres | Nouveau | Client communique un code à 4 chiffres au marchand |
| Validation manuelle | Nouveau | Marchand valide manuellement sans code ni QR |

---

## Décisions d'architecture

- **Approche A retenue** : code stocké sur l'entité `Order`, endpoint dédié, `PickupSession` inchangé.
- **Pas de rate limiting** : pas de compteur de tentatives ni de blocage temporaire. Code incorrect → erreur simple.
- **Code généré à `ready`** : visible pour le client dès que la commande est prête.
- **Transition directe** : `ready → completed` sans passer par `pickup_pending`.

---

## Section 1 — Modèle de données

### Ajout sur `Order`

```php
#[ORM\Column(length: 4, nullable: true)]
private ?string $pickupCode = null;
```

- Généré dans `Order::markReady()` via `\random_int(1000, 9999)` (string paddé sur 4 chiffres)
- Mis à `null` après utilisation (code consommé)
- Non exposé dans les réponses liste

### Nouvelle logique domaine sur `Order`

```php
public function generatePickupCode(): void
// Appelé dans markReady(), génère le code 4 chiffres

public function redeemByCode(string $code): void
// Vérifie que la commande est en statut ready
// Compare le code
// Si correct : pickupCode = null, transition ready → completed
// Si incorrect : lève une LogicException
```

### Migration Doctrine

`Version20260528XXXXXX.php`

```sql
-- up()
ALTER TABLE orders ADD pickup_code VARCHAR(4) DEFAULT NULL;

-- down()
ALTER TABLE orders DROP COLUMN pickup_code;
```

---

## Section 2 — Endpoints backend

### Mode 2 — Code 4 chiffres (nouveau)

```
POST /api/merchant/stores/{storeId}/orders/redeem-by-code
Authorization: Bearer <merchant_token>
Content-Type: application/json

{ "pickupCode": "4281" }
```

**Réponse succès `200` :**
```json
{ "orderId": "...", "status": "completed" }
```

**Erreurs :**
- `403` — marchand ne possède pas cette supérette
- `404` — aucune commande `ready` avec ce code pour ce shop (code incorrect ou commande non éligible)

**Logique :**
1. Vérifier ownership via `MerchantShopAccessChecker`
2. Trouver la commande `ready` du shop avec ce `pickupCode`
3. Déléguer à `Order::redeemByCode()`
4. Persister la transition
5. Tracer `withdrawal_validated_by_code` dans `OrderStatusLog`

---

### Mode 3 — Validation manuelle (nouveau)

```
POST /api/merchant/stores/{storeId}/orders/{orderId}/validate-manually
Authorization: Bearer <merchant_token>
Content-Type: application/json

{ "note": "Client présent, QR inaccessible" }
```

**Réponse succès `200` :**
```json
{ "orderId": "...", "status": "completed" }
```

**Erreurs :**
- `403` — ownership
- `404` — commande introuvable
- `409` — commande non en statut `ready`
- `422` — note manquante, vide ou inférieure à 5 caractères

**Logique :**
1. Vérifier ownership
2. Vérifier que la commande est en `ready`
3. Transition `ready → completed`
4. Tracer `withdrawal_validated_manually` dans `OrderStatusLog` avec `{ note: "..." }` en metadata

---

### Exposition du code côté client

Le champ `pickupCode` est ajouté au serialization group du détail de commande client :

- Exposé uniquement quand `status = ready`
- Absent des réponses liste (`/api/me/orders`)
- Absent quand la commande est dans un autre statut

---

## Section 3 — Frontend

### Client — `/orders/[orderId]`

Quand `status = ready`, afficher sous le QR code existant :

```
┌─────────────────────────────┐
│  Code de retrait            │
│                             │
│       [ 4  2  8  1 ]        │
│                             │
│  À communiquer au marchand  │
│  si le QR code ne fonctionne│
│  pas                        │
└─────────────────────────────┘
```

- Bloc discret, lecture seule
- Grand affichage chiffre par chiffre pour lisibilité en magasin
- Non affiché pour les autres statuts

### Marchand — écran retrait `/merchant/retrait`

Sélecteur de mode à 3 onglets :

```
┌──────────┬──────────────┬──────────────────┐
│  QR Code │ Code 4 chiff │  Manuel          │
└──────────┴──────────────┴──────────────────┘
```

**Onglet QR** : comportement actuel (inchangé)

**Onglet Code 4 chiffres :**
- Champ numérique 4 cases (clavier numérique sur mobile)
- Bouton "Valider"
- Code incorrect → message d'erreur rouge sous le champ
- Succès → feedback visuel + redirection

**Onglet Manuel :**
- Liste des commandes `ready` du jour pour ce shop
- Sélection d'une commande
- Champ note obligatoire
- Bouton "Valider manuellement"

---

## Section 4 — Tests backend

| Scénario | Endpoint | Statut attendu |
|---|---|---|
| Code correct, commande `ready` | redeem-by-code | `200` → `completed` |
| Code incorrect | redeem-by-code | `404` |
| Commande déjà `completed` | redeem-by-code | `404` |
| Commande `preparing` (pas `ready`) | redeem-by-code | `404` |
| Marchand n'appartient pas au shop | redeem-by-code | `403` |
| Validation manuelle, commande `ready`, note présente | validate-manually | `200` → `completed` |
| Validation manuelle sans note | validate-manually | `422` |

---

## Section 5 — Audit trail

Deux nouvelles valeurs dans `OrderStatusLog` :

| Action | Metadata |
|---|---|
| `withdrawal_validated_by_code` | `{}` |
| `withdrawal_validated_manually` | `{ "note": "..." }` |

---

## Périmètre hors scope

- Rate limiting / blocage après N tentatives : non inclus
- Expiration du code (TTL) : non inclus, le code reste valide tant que la commande est `ready`
- Notification push au client après validation : non inclus dans cette feature
- Mode `pickup_pending` : non utilisé dans les nouveaux modes (transition directe vers `completed`)
