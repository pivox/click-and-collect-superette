# US-009 — Créer et gérer les supérettes (admin)

**Epic** : EPIC-009 — Administration plateforme
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Must Have

---

## Récit

En tant qu'**administrateur plateforme**,
je veux **créer, modifier, activer et désactiver les supérettes**,
afin de **contrôler quelles supérettes sont accessibles aux clients et aux marchands**.

---

## Préconditions

- L'administrateur est connecté avec le rôle `ROLE_ADMIN`.

---

## Scénario nominal — Création d'une supérette

1. L'administrateur accède à la section « Supérettes ».
2. Il clique sur « Créer une supérette ».
3. Il saisit : nom, slug (généré automatiquement), adresse, ville, téléphone.
4. Il associe un compte marchand propriétaire (optionnel à la création).
5. Le système génère un `qrCodeToken` opaque unique (UUID v4).
6. La supérette est créée en statut `active`.
7. L'administrateur peut télécharger le QR code au format PNG pour impression.

---

## Scénario nominal — Modification

1. L'administrateur sélectionne une supérette.
2. Il modifie : nom, adresse, ville, téléphone.
3. Il sauvegarde.

---

## Scénario nominal — Désactivation

1. L'administrateur désactive une supérette.
2. La supérette n'est plus accessible via son QR code.
3. Les commandes en cours ne sont pas annulées automatiquement.

---

## Scénario nominal — Association d'un marchand propriétaire

1. L'administrateur sélectionne une supérette.
2. Il clique sur « Associer un marchand ».
3. Il sélectionne un compte marchand existant.
4. Le marchand devient propriétaire et peut gérer le catalogue et les créneaux.

---

## Scénario nominal — Régénération du QR code

1. En cas de compromission ou d'impression défectueuse, l'administrateur peut régénérer le `qrCodeToken`.
2. L'ancien token est immédiatement invalidé.
3. Un nouveau QR code est disponible au téléchargement.

---

## Règles métier

- Le `slug` doit être unique et URL-safe (minuscules, tirets, sans accents).
- Le `qrCodeToken` est opaque et ne doit pas exposer l'identifiant interne de la supérette.
- Une supérette désactivée (`active = false`) retourne 404 sur les routes publiques QR et catalogue.
- Une supérette peut exister sans marchand propriétaire (compte non encore créé).
- Un marchand ne peut être propriétaire que d'une seule supérette dans le MVP (à assouplir post-MVP).

---

## Critères d'acceptation

- [ ] L'administrateur peut créer une supérette avec nom, adresse et ville.
- [ ] Un `qrCodeToken` unique est généré automatiquement.
- [ ] Le QR code est téléchargeable au format PNG.
- [ ] L'administrateur peut modifier les informations d'une supérette.
- [ ] L'administrateur peut désactiver / réactiver une supérette.
- [ ] L'administrateur peut associer un marchand existant comme propriétaire.
- [ ] L'administrateur peut régénérer le `qrCodeToken`.
- [ ] La liste des supérettes est filtrables par statut (active, inactive) et paginée.

---

## Notes techniques

**Endpoints :**
```http
GET    /api/admin/stores
POST   /api/admin/stores
PATCH  /api/admin/stores/{storeId}
PATCH  /api/admin/stores/{storeId}/deactivate
PATCH  /api/admin/stores/{storeId}/activate
PATCH  /api/admin/stores/{storeId}/owner       { "merchantId": "<uuid>" }
POST   /api/admin/stores/{storeId}/regenerate-qr
GET    /api/admin/stores/{storeId}/qr.png      (retourne le PNG du QR code)
```

**Payload POST :**
```json
{
  "name": "Supérette Ezzahra",
  "address": "Rue de la République, Ezzahra",
  "city": "Ben Arous",
  "phone": "+21671000000"
}
```

- Le slug est dérivé du nom (slugify) et suffixé si doublon.
- Le `qrCodeToken` est généré par `Uuid::v4()->toRfc4122()`.
- Sécurité : `^/api/admin` → `ROLE_ADMIN` (security.yaml).
- QR code PNG : bibliothèque PHP `endroid/qr-code` ou retour du token et rendu côté frontend.
