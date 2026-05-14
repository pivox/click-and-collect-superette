# US-053 — Consulter l'historique complet des commandes (marchand)

**Epic** : EPIC-005 — Validation marchand
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **retrouver n'importe quelle commande passée** (complétée, annulée, refusée),
afin de **répondre aux demandes de support et vérifier l'historique de ma supérette**.

---

## Préconditions

- Le marchand est connecté et propriétaire de la supérette.

---

## Scénario nominal

1. Le marchand accède à l'historique de commandes.
2. Il peut filtrer par : statut, date (période), numéro de commande, nom du client.
3. Il voit la liste paginée de toutes les commandes (actives et terminées).
4. Il clique sur une commande pour voir son détail complet.

---

## Règles métier

- Toutes les commandes sont visibles, quel que soit leur statut (`completed`, `cancelled`, `rejected`, etc.).
- Par défaut, les commandes des 30 derniers jours sont affichées.
- La recherche par numéro (`#0042`) ou nom client est disponible.
- La pagination est de 20 commandes par page.
- Le marchand ne voit que les commandes de ses propres supérettes.

---

## Critères d'acceptation

- [ ] L'historique affiche toutes les commandes, incluant `completed`, `cancelled`, `rejected`.
- [ ] Le marchand peut filtrer par statut et par période de dates.
- [ ] La recherche par numéro de commande (#0042) fonctionne.
- [ ] La recherche par nom du client fonctionne.
- [ ] La pagination est opérationnelle (20 items/page).
- [ ] Le détail d'une commande terminée est consultable.

---

## Notes techniques

**Endpoint :**
```http
GET /api/merchant/stores/{storeId}/orders?status=completed&from=2026-05-01&to=2026-05-31&q=Fatima&page=1
```

**Paramètres :**
- `status` : filtre optionnel (`submitted`, `accepted`, `completed`, `cancelled`, `rejected`, `all`)
- `from` / `to` : période ISO date
- `q` : recherche textuelle (numéro de commande ou nom client)
- `page` : pagination (défaut 1)

**Note sur l'implémentation actuelle :** `MerchantOrderCollectionProvider` filtre probablement sur les commandes actives. Supprimer cette restriction et ajouter les paramètres de filtre optionnels.

**Recherche full-text :** en MVP, `LIKE '%Fatima%'` sur `users.name` et `orders.order_number` suffisent. Pas d'Elasticsearch dans le MVP.
