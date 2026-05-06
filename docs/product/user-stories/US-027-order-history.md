# US-027 — Consulter l'historique des commandes

**Epic** : EPIC-007 — Retrait sécurisé
**Sprint** : Sprint 4 — Retrait sécurisé
**Priorité** : Should Have

---

## Récit

En tant que **client**,
je veux **consulter l'historique de mes commandes passées**,
afin de **retrouver facilement ce que j'avais commandé et dans quelle supérette**.

---

## Préconditions

- Le client est connecté.
- Le client a au moins une commande finalisée ou annulée.

---

## Scénario nominal

1. Le client accède à la section « Mes commandes » dans son profil.
2. Il voit la liste de ses commandes triées par date décroissante.
3. Chaque commande affiche : numéro, supérette, date, statut final, montant total TND.
4. Il clique sur une commande pour voir le détail (liste des produits, quantités, prix).
5. Il peut commander à nouveau depuis une commande passée (« Commander à nouveau »).

---

## Scénarios alternatifs

**Aucune commande** :
- L'historique affiche : « Vous n'avez pas encore de commande. »

**Commander à nouveau** :
- Le système crée une nouvelle Kadhia `draft` avec les mêmes produits, à condition qu'ils soient toujours disponibles dans la supérette.
- Les produits non disponibles sont signalés.

---

## Règles métier

- L'historique inclut toutes les commandes : `completed`, `rejected`, `cancelled`.
- Les commandes `draft` et `submitted` sont dans la vue « Commandes en cours », pas dans l'historique.
- Le détail de commande est accessible en lecture seule pour toutes les commandes terminées.
- La commande « à nouveau » crée une nouvelle Kadhia indépendante au prix actuel du marchand.

---

## Critères d'acceptation

- [ ] La liste affiche les commandes finalisées triées par date décroissante.
- [ ] Le détail d'une commande passée est accessible.
- [ ] La fonctionnalité « Commander à nouveau » crée une nouvelle Kadhia avec les produits disponibles.
- [ ] Les produits non disponibles lors d'une recomposition sont signalés.
- [ ] L'historique est paginé (20 commandes par page).

---

## Notes techniques

- Endpoint liste : `GET /api/orders?status=completed,rejected,cancelled&sort=createdAt:desc`
- Endpoint détail : `GET /api/orders/{id}`
- « Commander à nouveau » : `POST /api/kadhia/from-order/{orderId}` qui recrée une Kadhia draft en vérifiant la disponibilité actuelle de chaque produit.
