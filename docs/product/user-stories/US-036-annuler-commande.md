# US-036 — Annuler une commande soumise

**Epic** : EPIC-004 — Rendez-vous et soumission de commande
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **annuler une commande que j'ai soumise mais que le marchand n'a pas encore traitée**,
afin de **corriger une erreur sans avoir à contacter la supérette**.

---

## Préconditions

- Le client est connecté.
- La commande est en statut `submitted` (avant toute réponse du marchand).

---

## Scénario nominal

1. Le client accède à l'écran de suivi de sa commande.
2. La commande est en statut `submitted`.
3. Le bouton « Annuler ma commande » est visible.
4. Le client confirme l'annulation (popup de confirmation).
5. La commande passe en statut `cancelled`.
6. Le créneau de retrait est libéré (`bookedCount` décrémenté).
7. La Kadhia associée reste `submitted` (archivée).
8. Le client voit la commande en statut « Annulée » dans son historique.

---

## Scénarios alternatifs

**Commande déjà acceptée** :
- Le bouton « Annuler » n'est pas affiché.
- Si le client tente l'appel directement : erreur 409 « La commande a déjà été traitée. »

**Commande en préparation ou prête** :
- L'annulation est impossible. Le client doit contacter la supérette.

---

## Règles métier

- L'annulation client n'est possible **qu'en statut `submitted`**.
- L'annulation libère le créneau réservé (`pickup_slot.booked_count - 1`).
- Une commande annulée ne peut pas être rouverte.
- La Kadhia source reste en statut `submitted` pour référence dans l'historique.
- Si le client veut recommander, il crée une nouvelle Kadhia.

---

## Critères d'acceptation

- [ ] Le client peut annuler une commande `submitted` depuis l'écran de suivi.
- [ ] Un popup de confirmation est affiché avant l'annulation.
- [ ] La commande passe en `cancelled` et apparaît dans l'historique.
- [ ] Le créneau libéré devient à nouveau disponible pour d'autres clients.
- [ ] Une commande `accepted`, `preparing` ou `ready` ne peut pas être annulée par ce flux.
- [ ] La réponse confirme l'annulation avec le statut `cancelled`.

---

## Notes techniques

**Endpoint :**
```http
POST /api/me/orders/{orderId}/cancel
```

**Réponse 200 :**
```json
{
  "id": "<uuid>",
  "status": "cancelled",
  "updated_at": "2026-05-15T14:30:00+01:00"
}
```

- Garde métier : `Order::cancel()` lève `LogicException('ORDER_CANNOT_BE_CANCELLED')` si le statut ne fait pas partie de `[draft, submitted, accepted]`. Pour le client, seul `submitted` est autorisé via le voter.
- Libération du créneau : UPDATE atomique `booked_count = GREATEST(booked_count - 1, 0)` comme pour le resubmit.
- Sécurité : le client ne peut annuler que ses propres commandes (`ROLE_CUSTOMER` + ownership check).
