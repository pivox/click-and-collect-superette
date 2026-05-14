# US-045 — Voir les coordonnées du client dans une commande

**Epic** : EPIC-005 — Validation marchand
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **voir le nom et le numéro de téléphone du client dans le détail d'une commande**,
afin de **pouvoir le contacter si un produit est manquant ou si le retrait pose un problème**.

---

## Préconditions

- Le marchand est connecté et propriétaire de la supérette.
- Une commande est en statut `submitted`, `accepted`, `preparing` ou `ready`.

---

## Scénario nominal

1. Le marchand ouvre le détail d'une commande soumise.
2. Il voit : nom du client, téléphone du client (si renseigné).
3. Sur mobile, le numéro est cliquable (lien `tel:`).
4. Il appelle le client pour signaler une rupture de stock avant d'accepter partiellement.

---

## Règles métier

- Le nom du client est toujours visible dans le détail marchand.
- Le téléphone est affiché **uniquement si le client l'a renseigné dans son profil**.
- Si le téléphone est absent : afficher « Téléphone non renseigné ».
- Les coordonnées du client **ne sont pas visibles dans la liste des commandes**, uniquement dans le détail.
- Les coordonnées ne sont exposées qu'au marchand propriétaire de la supérette (`MerchantShopAccessChecker`).
- Les coordonnées ne sont plus visibles après le statut `completed` ou `cancelled` (commande terminée, relation déconnectée).

---

## Critères d'acceptation

- [ ] Le nom du client est visible dans le détail de commande côté marchand.
- [ ] Le téléphone est affiché si renseigné, avec lien `tel:` sur mobile.
- [ ] Un message clair s'affiche si le téléphone est absent.
- [ ] Les coordonnées sont absentes de la liste des commandes (vie privée).
- [ ] Un marchand ne peut pas voir les coordonnées de clients d'une autre supérette.

---

## Notes techniques

**Ajout dans `MerchantOrderOutput` :**
```php
public string $customerName,
public ?string $customerPhone,
```

**Dans `MerchantOrderCollectionProvider::toOutput()` :**
```php
customerName: $order->getCustomer()->getName(),
customerPhone: $order->getCustomer()->getPhone(),
```

- Uniquement dans le `GET /api/merchant/stores/{storeId}/orders/{orderId}` (item), pas dans la collection.
- Séparation via deux outputs ou via un groupe de sérialisation `merchant_order:detail`.
