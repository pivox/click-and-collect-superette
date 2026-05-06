# US-022 — Consulter la liste des commandes soumises

**Epic** : EPIC-005 — Validation marchand
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **voir la liste de toutes les commandes soumises dans ma supérette**,
afin de **traiter chaque commande rapidement et dans l'ordre d'arrivée**.

---

## Préconditions

- Le marchand est connecté à son backoffice.
- Au moins une commande est en statut `submitted` pour sa supérette.

---

## Scénario nominal

1. Le marchand accède à son tableau de bord.
2. Il voit la liste des commandes groupées par statut : En attente, Acceptées, En préparation, Prêtes.
3. Chaque commande affiche : numéro, heure de soumission, créneau de retrait, nombre de produits, montant total TND.
4. Les commandes « En attente » sont en premier et mises en évidence.
5. Le marchand clique sur une commande pour en voir le détail.

---

## Scénarios alternatifs

**Aucune commande en attente** :
- La section « En attente » affiche : « Aucune nouvelle commande. »

**Nouvelle commande reçue en temps réel** :
- Une notification visuelle (badge, son, alerte) informe le marchand sans qu'il recharge la page.

---

## Règles métier

- Le marchand ne voit que les commandes de sa supérette.
- Les commandes finalisées (`completed`, `cancelled`, `rejected`) sont dans un onglet « Historique ».
- Les commandes actives sont triées par créneau de retrait croissant.
- Le tableau de bord affiche uniquement les commandes du jour et du lendemain par défaut.

---

## Critères d'acceptation

- [ ] La liste affiche bien les commandes de la supérette du marchand connecté.
- [ ] Les commandes sont groupées par statut.
- [ ] Chaque commande affiche numéro, heure, créneau, nombre de produits et total TND.
- [ ] Le clic sur une commande ouvre son détail.
- [ ] Les nouvelles commandes sont visibles sans rechargement manuel (polling ou Mercure).
- [ ] L'historique des commandes traitées est accessible depuis un onglet dédié.

---

## Notes techniques

- Endpoint liste : `GET /api/merchant/orders?status=submitted,accepted,preparing,ready`
- Endpoint historique : `GET /api/merchant/orders?status=completed,rejected,cancelled`
- Temps réel : polling toutes les 30 secondes dans le MVP (Mercure en post-MVP si adoption validée).
- Sécurité : le filtre `shop_id` est appliqué côté serveur depuis le contexte de l'utilisateur connecté.
