# US-040 — Consulter l'historique des transitions de statut d'une commande

**Epic** : EPIC-005 — Validation marchand
**Sprint** : Sprint 3 — Parcours marchand core
**Priorité** : Must Have

---

## Récit

En tant que **client ou marchand**,
je veux **voir la liste horodatée de tous les changements de statut d'une commande**,
afin de **comprendre le déroulement exact de la commande et faciliter le support en cas de litige**.

---

## Préconditions

- L'utilisateur est connecté.
- La commande lui appartient (client) ou appartient à sa supérette (marchand).

---

## Scénario nominal

1. L'utilisateur consulte le détail d'une commande.
2. Il voit un fil chronologique :
   - `submitted` — 14/05/2026 à 10:32
   - `accepted` — 14/05/2026 à 10:45
   - `preparing` — 14/05/2026 à 11:00
   - `ready` — 14/05/2026 à 11:28
   - `completed` — 14/05/2026 à 11:35
3. Chaque transition est datée et peut inclure une note (ex : raison de refus).

---

## Règles métier

- Chaque transition de statut est enregistrée avec un horodatage.
- Une transition ne peut pas être supprimée (immuable une fois créée).
- La note est optionnelle (utilisée pour le motif de refus ou de refus partiel).
- L'historique est accessible au client pour ses commandes et au marchand pour les commandes de sa supérette.

---

## Critères d'acceptation

- [ ] Chaque changement de statut est enregistré avec la date et l'heure exactes.
- [ ] Le client voit l'historique de ses propres commandes.
- [ ] Le marchand voit l'historique des commandes de sa supérette.
- [ ] La note de refus (ou refus partiel) apparaît dans la transition correspondante.
- [ ] L'historique est retourné dans l'ordre chronologique (du plus ancien au plus récent).

---

## Notes techniques

**Nouvelle entité `OrderStatusLog` :**
```text
order_status_logs
- id (uuid)
- order_id
- status (enum OrderStatus)
- note (varchar 500, nullable)
- created_at
INDEX(order_id, created_at)
```

**Endpoints :**
```http
GET /api/me/orders/{orderId}/status-history
GET /api/merchant/stores/{storeId}/orders/{orderId}/status-history
```

**Réponse GET 200 :**
```json
{
  "order_id": "<uuid>",
  "transitions": [
    { "status": "submitted", "note": null, "at": "2026-05-14T10:32:00+01:00" },
    { "status": "accepted",  "note": null, "at": "2026-05-14T10:45:00+01:00" },
    { "status": "preparing", "note": null, "at": "2026-05-14T11:00:00+01:00" },
    { "status": "ready",     "note": null, "at": "2026-05-14T11:28:00+01:00" },
    { "status": "completed", "note": null, "at": "2026-05-14T11:35:00+01:00" }
  ]
}
```

- Insérer un `OrderStatusLog` dans chaque Processor de transition (Accept, Reject, StartPreparation, MarkReady, Cancel, Complete, PartiallyAccept).
- Le `submitted_at` manquant sur `Order` (écart Sprint 2) peut être remplacé par la lecture du premier `OrderStatusLog` avec statut `submitted`.
