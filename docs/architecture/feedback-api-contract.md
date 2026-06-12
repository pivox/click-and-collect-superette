# Contrat API — Module Feedback

Issue liée : #482 — S15-011 — Module feedback activable par rôle et page  
Statut : contrat cible, à aligner avec l'implémentation finale  
Date : 2026-06-12

---

## 1. Conventions

```text
Format : JSON
Identifiants : UUID
Authentification : JWT
Routes /api/admin/* : ROLE_ADMIN
Routes /api/feedback/* : utilisateur connecté selon configuration du module
```

Le module Feedback doit respecter les rôles existants :

```text
ROLE_CUSTOMER
ROLE_MERCHANT
ROLE_ADMIN
```

---

## 2. Lire la configuration courante

```http
GET /api/feedback/settings/current?appArea=merchant&appSubArea=merchant_orders
```

Objectif : permettre au frontend de savoir si le bouton Feedback doit être affiché pour l'utilisateur courant.

### Réponse `200`

```json
{
  "enabled": true,
  "appArea": "merchant",
  "appSubArea": "merchant_orders",
  "allowedFeedbackTypes": ["bug", "idea", "confusing", "other"],
  "requireAuthenticatedUser": true
}
```

### Règles

```text
- La réponse est filtrée selon le rôle courant.
- Si le module est désactivé, enabled = false.
- Si la zone demandée n'est pas autorisée, enabled = false.
- La route ne retourne pas la configuration admin complète.
```

---

## 3. Créer un feedback

```http
POST /api/feedback
```

Objectif : enregistrer un retour utilisateur contextualisé.

### Payload

```json
{
  "feedbackType": "bug",
  "message": "Le bouton valider n'est pas clair sur cette page.",
  "pageUrl": "https://app.clickcollect.tn/merchant/orders/123",
  "routeName": "merchant.orders.detail",
  "pageTitle": "Détail commande",
  "appArea": "merchant",
  "appSubArea": "merchant_orders",
  "storeId": "uuid-nullable",
  "locale": "fr",
  "viewport": {
    "width": 390,
    "height": 844
  }
}
```

### Réponse `201`

```json
{
  "id": "feedback-uuid",
  "status": "unread",
  "createdAt": "2026-06-12T12:00:00+02:00"
}
```

### Erreurs recommandées

```text
400 FEEDBACK_MESSAGE_REQUIRED
403 FEEDBACK_DISABLED
403 FEEDBACK_DISABLED_FOR_ROLE
403 FEEDBACK_DISABLED_FOR_AREA
422 FEEDBACK_MESSAGE_TOO_SHORT
422 FEEDBACK_MESSAGE_TOO_LONG
```

---

## 4. Administration — Paramètres feedback

```http
GET /api/admin/feedback/settings
PUT /api/admin/feedback/settings
```

### Réponse `GET 200`

```json
{
  "isEnabled": true,
  "enabledForAdmin": true,
  "enabledForCustomer": true,
  "enabledForMerchant": true,
  "enabledZones": ["client", "merchant", "admin"],
  "requireAuthenticatedUser": true,
  "updatedAt": "2026-06-12T12:00:00+02:00"
}
```

### Payload `PUT`

```json
{
  "isEnabled": true,
  "enabledForAdmin": true,
  "enabledForCustomer": true,
  "enabledForMerchant": true,
  "enabledZones": ["client", "merchant", "admin"],
  "requireAuthenticatedUser": true
}
```

### Règles

```text
- ROLE_ADMIN obligatoire.
- Les modifications peuvent être tracées dans l'audit trail admin si disponible.
- Une configuration absente doit retourner une configuration par défaut sûre : module désactivé.
```

---

## 5. Administration — Liste des feedbacks

```http
GET /api/admin/feedbacks?page=1&limit=20&status=unread&role=ROLE_MERCHANT&appArea=merchant
```

### Filtres cibles

```text
status = unread|read|resolved
role = ROLE_CUSTOMER|ROLE_MERCHANT|ROLE_ADMIN
feedbackType = bug|idea|confusing|other
appArea = client|merchant|admin
appSubArea
storeId
createdFrom
createdTo
```

### Réponse `200`

```json
{
  "items": [
    {
      "id": "feedback-uuid",
      "status": "unread",
      "feedbackType": "bug",
      "role": "ROLE_MERCHANT",
      "appArea": "merchant",
      "appSubArea": "merchant_orders",
      "pageTitle": "Détail commande",
      "messageExcerpt": "Le bouton valider n'est pas clair...",
      "user": {
        "id": "user-uuid",
        "name": "Marchand"
      },
      "store": {
        "id": "store-uuid",
        "name": "Hamza Market"
      },
      "createdAt": "2026-06-12T12:00:00+02:00"
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 20,
    "totalItems": 1,
    "totalPages": 1
  }
}
```

---

## 6. Administration — Détail feedback

```http
GET /api/admin/feedbacks/{feedbackId}
```

### Réponse `200`

```json
{
  "id": "feedback-uuid",
  "status": "read",
  "feedbackType": "bug",
  "message": "Le bouton valider n'est pas clair sur cette page.",
  "role": "ROLE_MERCHANT",
  "user": {
    "id": "user-uuid",
    "name": "Marchand",
    "email": "merchant@example.com"
  },
  "store": {
    "id": "store-uuid",
    "name": "Hamza Market"
  },
  "page": {
    "url": "https://app.clickcollect.tn/merchant/orders/123",
    "routeName": "merchant.orders.detail",
    "title": "Détail commande",
    "appArea": "merchant",
    "appSubArea": "merchant_orders"
  },
  "clientContext": {
    "locale": "fr",
    "viewportWidth": 390,
    "viewportHeight": 844
  },
  "adminNote": "Pris en compte.",
  "readAt": "2026-06-12T12:10:00+02:00",
  "resolvedAt": null,
  "createdAt": "2026-06-12T12:00:00+02:00",
  "updatedAt": "2026-06-12T12:10:00+02:00"
}
```

---

## 7. Administration — Actions de traitement

```http
PATCH /api/admin/feedbacks/{feedbackId}/read
PATCH /api/admin/feedbacks/{feedbackId}/unread
PATCH /api/admin/feedbacks/{feedbackId}/resolve
PATCH /api/admin/feedbacks/{feedbackId}/reopen
```

### Payload `resolve`

```json
{
  "adminNote": "Corrigé dans le wording de la page commande."
}
```

### Payload `reopen`

```json
{
  "adminNote": "Réouvert après nouveau retour terrain."
}
```

### Réponse `200`

```json
{
  "id": "feedback-uuid",
  "status": "resolved",
  "adminNote": "Corrigé dans le wording de la page commande.",
  "updatedAt": "2026-06-12T12:30:00+02:00"
}
```

---

## 8. Sécurité et garde-fous

```text
- Les endpoints admin sont strictement réservés à ROLE_ADMIN.
- La création d'un feedback respecte la configuration courante.
- Les données collectées automatiquement restent limitées au contexte utile.
- Le MVP ne prévoit pas de capture d'écran automatique.
- Le MVP ne prévoit pas de pièce jointe.
- Le MVP ne prévoit pas de session replay.
```

---

## 9. Notes d'implémentation

```text
- Prévoir une configuration par défaut désactivée.
- Prévoir une pagination sur la liste admin.
- Prévoir des index sur status, role, app_area, store_id et created_at.
- Prévoir une longueur maximale du message.
- Prévoir une réponse neutre et simple côté utilisateur après création.
```
