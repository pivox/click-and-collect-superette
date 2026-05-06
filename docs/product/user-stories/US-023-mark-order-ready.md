# US-023 — Déclarer une commande prête

**Epic** : EPIC-006 — Préparation de commande
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand ou employé préparateur**,
je veux **déclarer qu'une commande est prête à être retirée**,
afin de **notifier le client qu'il peut venir récupérer sa Kadhia**.

---

## Préconditions

- La commande est en statut `preparing`.
- Toutes les lignes de la commande ont été cochées comme préparées (via US-006).

---

## Scénario nominal

1. Le préparateur a coché toutes les lignes de la commande.
2. Le bouton « Déclarer prête » devient actif.
3. Le préparateur appuie sur « Déclarer prête ».
4. Le système demande une confirmation rapide.
5. La commande passe en statut `ready`.
6. Le client est notifié : « Votre Kadhia est prête ! Présentez-vous avec votre QR code de retrait. »
7. La commande est déplacée dans la file « Prêtes à retirer ».

---

## Scénarios alternatifs

**Le préparateur déclare prête sans avoir tout coché** :
- Dans le MVP, le bouton reste désactivé tant que toutes les lignes ne sont pas cochées.
- En cas de produit manquant signalé, le marchand peut forcer le passage en prête avec une note.

---

## Règles métier

- Une commande ne peut passer en `ready` que depuis le statut `preparing`.
- La date et l'heure de passage en `ready` sont enregistrées.
- La notification client est obligatoire à cette transition.
- Le créneau de retrait n'est pas modifiable à ce stade.

---

## Critères d'acceptation

- [ ] Le bouton « Déclarer prête » est actif uniquement quand toutes les lignes sont cochées.
- [ ] La commande passe bien en `ready` après confirmation.
- [ ] Le client reçoit une notification au moment du passage en `ready`.
- [ ] La commande apparaît dans la file « Prêtes à retirer » du tableau de bord marchand.
- [ ] La date et l'heure de passage en `ready` sont visibles dans le détail de la commande.

---

## Notes techniques

- Endpoint : `PATCH /api/merchant/orders/{id}/mark-ready`
- Notification client via Symfony Messenger : email ou push.
- La transition est validée par le `OrderStatusTransitionService` (guard : statut == `preparing`).
