# US-042 — Identifier une commande par un numéro lisible

**Epic** : EPIC-004 — Rendez-vous et soumission de commande
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client et marchand**,
je veux **identifier une commande par un numéro court et lisible** (ex : `#0042`),
afin de **pouvoir communiquer verbalement lors du retrait sans lire un UUID**.

---

## Préconditions

- Une commande est soumise.

---

## Scénario nominal

1. Le client soumet sa commande.
2. La confirmation affiche : « Commande **#0042** enregistrée pour le créneau 10h–11h. »
3. Le marchand voit dans sa liste : `#0042 — Fatima B. — 10h–11h — 12,500 TND`.
4. Au retrait, le marchand dit : « Commande 42, c'est prêt ! »

---

## Règles métier

- Le numéro est un entier séquentiel **par supérette** (pas global à la plateforme).
- Format d'affichage : `#` + numéro sur 4 chiffres minimum (`#0001`, `#0042`, `#1234`).
- Le numéro est attribué à la soumission (statut `submitted`), pas à la création du draft.
- Deux supérettes différentes peuvent avoir une commande `#0042` sans conflit.
- Le numéro n'est jamais réutilisé même après annulation.

---

## Critères d'acceptation

- [ ] Chaque commande soumise reçoit un numéro séquentiel par supérette.
- [ ] Le numéro est visible sur la confirmation de commande côté client.
- [ ] Le numéro est visible dans la liste et le détail côté marchand.
- [ ] Deux commandes de la même supérette n'ont jamais le même numéro.
- [ ] Le numéro est exposé dans le QR code de retrait (affiché sous le QR).

---

## Notes techniques

**Champ à ajouter sur `Order` :**
```php
#[ORM\Column(nullable: true)]
private ?int $orderNumber = null;
```

**Contrainte unique :** `UNIQUE(shop_id, order_number)`.

**Attribution dans `SubmitOrderProcessor` :**
```sql
SELECT COALESCE(MAX(order_number), 0) + 1
FROM orders
WHERE shop_id = :shopId
```
Exécuté dans la même transaction que la soumission pour éviter les doublons concurrents.

**Migration :**
```sql
ALTER TABLE orders ADD COLUMN order_number INTEGER DEFAULT NULL;
CREATE UNIQUE INDEX UNIQ_ORDERS_SHOP_NUMBER ON orders (shop_id, order_number);
```

**Exposition dans les outputs :**
```json
{ "order_number": 42, "order_number_display": "#0042" }
```
`order_number_display` est calculé côté API : `sprintf('#%04d', $order->getOrderNumber())`.
