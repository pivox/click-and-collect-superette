# US-077 — Paiement manuel abonnement marchand

**Epic** : EPIC-017 — Abonnement & monétisation
**Sprint** : Sprint 11 — Activation commerciale
**Priorité** : Must Have
**État au 2026-06-04** : cadrée, implémentation backend à faire après validation US-076

---

## Récit

En tant qu'**administrateur plateforme**,
je veux **enregistrer et valider un paiement manuel d'abonnement marchand par espèces ou virement**,
afin de **réactiver ou maintenir l'abonnement d'un marchand sans paiement en ligne**.

---

## Décision de cadrage

L'implémentation de US-077 ne doit pas créer une fondation abonnement concurrente.

La fondation US-074 / US-075 livre l'entité `Subscription`, ses statuts de lifecycle et sa phase tarifaire. US-077 ne doit donc pas recréer ces concepts : elle doit s'y brancher et ajouter uniquement la traçabilité du paiement manuel.

L'implémentation reste à faire après le cadrage US-076, car le paiement manuel doit pouvoir être rapproché d'un document mensuel ou d'une période facturée clairement définie.

Sans cette décision, créer directement `Payment` ou `SubscriptionPayment` risquerait de figer un modèle difficile à raccorder aux futurs reçus / factures.

---

## Hors périmètre

- Aucun paiement en ligne.
- Aucune carte bancaire.
- Aucun wallet ou paiement mobile.
- Aucune relance email ou automatisation de recouvrement.
- Aucun reçu fiscal complet tant que US-076 n'est pas cadrée fiscalement.

---

## Contrat `Subscription` disponible pour US-077

### Entité `Subscription`

Contrat de fondation sur lequel US-077 doit s'appuyer :

| Champ | Type attendu | Rôle |
|---|---|---|
| `id` | UUID | Identifiant stable de l'abonnement. |
| `merchant` | `User` marchand | Marchand propriétaire de l'abonnement. |
| `lifecycle` | enum | `active`, `payment_due`, `grace_period`, `suspended`, `cancelled`. |
| `pricing_phase` | enum | `trial`, `promo`, `standard`. |
| `current_period_started_at` | datetime immutable | Début de période couverte. |
| `current_period_ends_at` | datetime immutable | Fin de période couverte. |
| `monthly_price_tnd` | decimal string 3 décimales | Montant mensuel attendu en TND, cohérent avec les prix existants (`"10.000"`). |
| `currency` | string | Toujours `TND` pour le MVP. |
| `created_at` | datetime immutable | Création. |
| `updated_at` | datetime immutable | Dernière modification. |

### Règle métier minimale

Un service domaine abonnement doit exposer une opération testable du type :

```php
$subscriptionPaymentApplicationService->confirmManualPayment(
    subscription: $subscription,
    payment: $payment,
    validatedBy: $admin,
);
```

Effets attendus :

- le paiement passe à `confirmed` ;
- l'abonnement est couvert pour la période déclarée ;
- si le lifecycle est `payment_due`, `grace_period` ou `suspended`, il revient à `active` selon la règle existante ;
- si le lifecycle est `cancelled`, l'opération échoue sauf décision produit explicite ;
- une trace admin est créée dans `AdminAuditLog`.

---

## Modèle `SubscriptionPayment` attendu après déblocage

### Champs

| Champ | Type attendu | Rôle |
|---|---|---|
| `id` | UUID | Identifiant du paiement. |
| `subscription` | `Subscription` | Abonnement concerné. |
| `merchant` | `User` marchand | Dénormalisation utile pour filtre admin, cohérente avec `subscription.merchant`. |
| `amount_tnd` | decimal string 3 décimales | Montant encaissé en TND. |
| `currency` | string | Toujours `TND`. |
| `period_start` | datetime immutable | Début de période payée. |
| `period_end` | datetime immutable | Fin de période payée. |
| `method` | enum | `cash` ou `bank_transfer`. |
| `reference` | string nullable | Référence de virement, note caisse ou identifiant interne optionnel. |
| `paid_at` | datetime immutable | Date réelle de paiement déclarée par l'admin. |
| `status` | enum | `pending`, `confirmed`, `refused`. |
| `created_by_admin` | `User` admin | Admin ayant enregistré le paiement. |
| `validated_by_admin` | `User` admin nullable | Admin ayant confirmé ou refusé. |
| `validated_at` | datetime immutable nullable | Date de décision admin. |
| `refusal_reason` | text nullable | Motif obligatoire en cas de refus. |
| `created_at` | datetime immutable | Création. |
| `updated_at` | datetime immutable | Dernière modification. |

### Invariants

- `amount_tnd` est strictement positif.
- `currency` vaut `TND`.
- `method` est limité à `cash` et `bank_transfer`.
- `period_end` est strictement après `period_start`.
- `paid_at` ne doit pas être dans le futur.
- Un paiement `confirmed` ou `refused` ne peut plus changer de statut.
- Le refus ne modifie pas l'abonnement.
- La confirmation applique l'effet sur l'abonnement via un service domaine, jamais depuis le controller.

---

## Endpoints admin attendus après déblocage

Tous les endpoints sont strictement réservés à `ROLE_ADMIN`.

```http
POST /api/admin/subscription-payments
```

Crée un paiement manuel en statut `pending`.

Payload minimal :

```json
{
  "subscription_id": "<uuid>",
  "amount_tnd": "10.000",
  "period_start": "2026-09-01T00:00:00+01:00",
  "period_end": "2026-10-01T00:00:00+01:00",
  "method": "bank_transfer",
  "reference": "VIR-2026-00042",
  "paid_at": "2026-09-02T10:30:00+01:00"
}
```

```http
PATCH /api/admin/subscription-payments/{paymentId}/confirm
```

Valide le paiement, renseigne `validated_by_admin` / `validated_at`, applique l'effet sur `Subscription`, puis journalise l'action admin `subscription_payment.confirmed`.

```http
PATCH /api/admin/subscription-payments/{paymentId}/refuse
```

Refuse le paiement avec `refusal_reason`, ne modifie pas l'abonnement, puis journalise `subscription_payment.refused`.

```http
GET /api/admin/subscription-payments?merchant=<uuid>&status=pending&page=1
GET /api/admin/subscription-payments/{paymentId}
```

Lecture admin paginée et détail, sans exposer de données internes inutiles.

---

## Représentation API minimale

```json
{
  "id": "<uuid>",
  "subscription_id": "<uuid>",
  "merchant": {
    "id": "<uuid>",
    "email": "marchand@example.test"
  },
  "amount_tnd": "10.000",
  "currency": "TND",
  "period_start": "2026-09-01T00:00:00+01:00",
  "period_end": "2026-10-01T00:00:00+01:00",
  "method": "bank_transfer",
  "reference": "VIR-2026-00042",
  "paid_at": "2026-09-02T10:30:00+01:00",
  "status": "confirmed",
  "validated_by_admin": {
    "id": "<uuid>",
    "email": "admin@example.test"
  },
  "validated_at": "2026-09-02T10:35:00+01:00"
}
```

Ne pas exposer :

- hash, rôles complets ou détails internes de `User` ;
- métadonnées techniques de Doctrine ;
- détails fiscaux non cadrés par US-076.

---

## Tests attendus après déblocage

### Tests métier

- Confirmer un paiement `pending` passe le paiement à `confirmed`.
- Confirmer un paiement applique la règle de retour à `active` pour `payment_due`, `grace_period` ou `suspended`.
- Confirmer un paiement sur abonnement `cancelled` échoue.
- Refuser un paiement ne modifie pas l'abonnement.
- Confirmer ou refuser deux fois le même paiement échoue.

### Tests fonctionnels admin

- Un admin peut créer un paiement manuel `cash`.
- Un admin peut créer un paiement manuel `bank_transfer` avec référence.
- Un admin peut confirmer un paiement et reçoit le payload attendu.
- Un admin peut refuser un paiement avec motif.
- Un marchand ou client reçoit `403` sur tous les endpoints admin.
- Un anonyme reçoit `401`.
- Les validations payload renvoient `422` pour montant invalide, méthode invalide, période incohérente ou paiement futur.

---

## Critères d'acceptation

- [ ] US-074 et US-075 livrent une entité `Subscription` et les statuts attendus.
- [ ] Une entité `SubscriptionPayment` trace montant TND, période, moyen, référence, date de paiement et validateur admin.
- [ ] Les endpoints admin permettent de créer, confirmer, refuser et consulter les paiements manuels.
- [ ] La confirmation applique l'effet sur l'abonnement via un service domaine.
- [ ] La sécurité admin est stricte.
- [ ] Une entrée `AdminAuditLog` est créée à la confirmation et au refus.
- [ ] Aucun paiement en ligne n'est introduit.
- [ ] Aucune relance email n'est introduite.
