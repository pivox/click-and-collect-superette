# US-005 — Accepter ou refuser une commande

**Epic** : EPIC-005 — Validation marchand
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **accepter ou refuser une commande soumise par un client**,
afin de **gérer ma capacité et ne préparer que ce que je peux honorer**.

---

## Préconditions

- Une commande est en statut `submitted`.
- Le marchand est connecté à son backoffice.
- Le créneau de retrait associé à la commande est encore valide.

---

## Scénario nominal — Acceptation

1. Le marchand voit la commande dans la liste des commandes en attente.
2. Il consulte le détail : liste des produits, quantités, prix, créneau demandé.
3. Il clique sur « Accepter ».
4. Le système passe la commande en statut `accepted`.
5. Une notification est envoyée au client : « Votre commande a été acceptée. »
6. La commande est déplacée vers la file de préparation.

---

## Scénario nominal — Refus

1. Le marchand voit la commande dans la liste des commandes en attente.
2. Il consulte le détail.
3. Il clique sur « Refuser ».
4. Le système demande une raison (sélection parmi une liste + champ libre optionnel).
5. La commande passe en statut `rejected`.
6. Une notification est envoyée au client avec la raison du refus.
7. Le créneau est libéré.

---

## Scénarios alternatifs

**Commande annulée par le client avant décision** :
- La commande disparaît de la liste en attente.
- Le marchand voit une mention « Annulée par le client » si elle était visible.

**Délai de réponse dépassé** :
- Si le marchand ne répond pas dans un délai configuré (ex : 30 min), la commande passe automatiquement en `rejected` avec raison « Délai de réponse dépassé ».

---

## Règles métier

- Seul le marchand propriétaire de la supérette peut valider ou refuser.
- Le marchand ne peut pas modifier le contenu de la commande lors de la validation.
- Le refus libère le créneau de retrait.
- Les raisons de refus prédéfinies : stock insuffisant, créneau non disponible, commande incomplète, autre.

---

## Critères d'acceptation

- [ ] Le marchand voit clairement les commandes en attente sur son tableau de bord.
- [ ] Le détail d'une commande affiche tous les produits, quantités et prix en TND.
- [ ] L'acceptation passe la commande en `accepted` et notifie le client.
- [ ] Le refus exige une raison et passe la commande en `rejected`.
- [ ] Le refus libère le créneau de retrait.
- [ ] Le marchand ne peut pas accepter/refuser une commande déjà traitée.

---

## Notes techniques

- Endpoint : `PATCH /api/merchant/orders/{id}/accept`
- Endpoint : `PATCH /api/merchant/orders/{id}/reject` avec `{ reason: string, comment?: string }`
- La notification client peut être synchrone (Mercure) ou asynchrone (Messenger + push).
- La transition de statut est gérée par un `OrderStatusTransitionService`.
