# US-006 — Préparer une commande ligne par ligne

**Epic** : EPIC-006 — Préparation de commande
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand ou employé préparateur**,
je veux **préparer une commande acceptée en cochant chaque produit au fur et à mesure**,
afin de **m'assurer que tous les articles sont bien rassemblés avant le retrait**.

---

## Préconditions

- La commande est en statut `accepted`.
- Le marchand ou l'employé est connecté au backoffice.
- Le détail de la commande est accessible (liste des lignes Kadhia).

---

## Scénario nominal

1. Le marchand ou l'employé ouvre la commande depuis la file de préparation.
2. La commande passe automatiquement en statut `preparing` à l'ouverture.
3. Le système affiche la liste des lignes : produit, quantité à préparer.
4. Le préparateur coche chaque ligne au fur et à mesure.
5. Une ligne cochée est visuellement barrée ou cochée.
6. Lorsque toutes les lignes sont cochées, le bouton « Déclarer prête » devient actif.
7. Le préparateur appuie sur « Déclarer prête ».
8. La commande passe en statut `ready`.
9. Le client est notifié : « Votre Kadhia est prête à être retirée. »

---

## Scénarios alternatifs

**Produit introuvable en stock** :
- Le préparateur peut signaler un produit manquant via l'interface.
- Une alerte est remontée au marchand pour action (substitution ou contact client).
- Dans le MVP : le préparateur peut ignorer et déclarer prête malgré tout (comportement à affiner en post-MVP).

**Interruption en cours de préparation** :
- Si le préparateur quitte la page, l'état des coches est conservé côté serveur.
- La commande reste en `preparing`.

---

## Règles métier

- Une commande ne peut pas passer en `ready` si elle n'est pas en `preparing`.
- Seul le marchand ou un employé de la supérette peut préparer une commande.
- La date de passage en `ready` est enregistrée pour traçabilité.

---

## Critères d'acceptation

- [ ] L'ouverture d'une commande `accepted` la passe en `preparing`.
- [ ] Chaque ligne de la Kadhia est affichée avec le nom du produit et la quantité.
- [ ] Le préparateur peut cocher chaque ligne individuellement.
- [ ] Le bouton « Déclarer prête » n'est actif que lorsque toutes les lignes sont cochées.
- [ ] Le passage en `ready` notifie le client.
- [ ] L'état des coches est persisté entre les sessions.

---

## Notes techniques

- Endpoint : `PATCH /api/merchant/orders/{id}/start-preparation`
- Endpoint : `PATCH /api/merchant/orders/{id}/mark-ready`
- État des coches : stocké sur `OrderLine.prepared = boolean`.
- Notification client via Symfony Messenger (email ou push selon préférence).
