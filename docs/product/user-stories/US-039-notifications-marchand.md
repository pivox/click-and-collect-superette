# US-039 — Recevoir des notifications pour les nouvelles commandes (marchand)

**Epic** : EPIC-014 — Notifications MVP
**Sprint** : Sprint 4 — Retrait sécurisé
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **être notifié immédiatement lorsqu'une nouvelle commande est soumise pour ma supérette**,
afin de **la traiter rapidement et respecter le créneau de retrait du client**.

---

## Préconditions

- Le marchand est connecté à son backoffice.
- La supérette est active.

---

## Scénario nominal

1. Un client soumet une commande.
2. Une notification est créée pour le marchand propriétaire de la supérette.
3. Le marchand voit le badge de notifications s'incrémenter.
4. Il clique sur la notification et est dirigé vers le détail de la commande.
5. Il peut accepter, refuser ou accepter partiellement depuis cet écran.

---

## Notifications déclenchées

| Événement | Titre FR | Corps FR |
|---|---|---|
| Nouvelle commande soumise | « Nouvelle commande » | « [Client] vient de soumettre une commande pour le créneau [heure]. » |
| Commande re-soumise après acceptation partielle | « Commande resoumise » | « [Client] a modifié et resoumis sa commande #[ref]. » |

---

## Règles métier

- La notification est créée sur le compte `User` du propriétaire de la supérette (`Shop.owner`).
- Si la supérette n'a pas de propriétaire associé, la notification est ignorée silencieusement.
- Le marchand ne voit que les notifications de ses propres supérettes.
- La même entité `Notification` que pour les clients est utilisée (polymorphisme par `user_id`).

---

## Critères d'acceptation

- [ ] Une notification est créée pour le marchand à chaque nouvelle soumission.
- [ ] La notification contient le créneau, le nom du client et la référence de commande.
- [ ] Le marchand peut lister et marquer ses notifications comme lues.
- [ ] Un badge affiche le nombre de commandes non lues.
- [ ] La notification est cliquable et redirige vers le détail de la commande.

---

## Notes techniques

**Endpoints partagés avec US-038 (même entité `Notification`) :**
```http
GET   /api/merchant/notifications?page=1&unread=true
PATCH /api/merchant/notifications/{id}/read
PATCH /api/merchant/notifications/read-all
```

- Même entité `Notification` que pour les clients (`user_id` discriminant).
- Créé par `NotificationService::createForOrderTransition()` à la soumission d'une commande.
- `order_id` lié à la commande concernée.
- En production post-MVP : remplacer le polling par Mercure (Server-Sent Events) pour les notifications marchands en temps réel.
