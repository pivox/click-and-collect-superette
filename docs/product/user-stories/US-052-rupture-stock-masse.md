# US-052 — Gérer les ruptures de stock en masse

**Epic** : EPIC-011 — Référentiel produit et catalogue marchand
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Should Have

---

## Récit

En tant que **marchand**,
je veux **marquer plusieurs produits comme indisponibles en une seule action**,
afin de **mettre à jour rapidement mon catalogue après une livraison partielle ou un imprévu**.

---

## Préconditions

- Le marchand est connecté et propriétaire de la supérette.
- Des produits de son catalogue sont concernés par une rupture.

---

## Scénario nominal

1. Le marchand accède à son catalogue.
2. Il active le mode « Gestion des disponibilités ».
3. Des cases à cocher apparaissent à côté de chaque produit.
4. Il coche les produits en rupture.
5. Il clique sur « Marquer comme indisponibles » (action groupée).
6. Les produits sélectionnés passent à `isAvailable = false`.
7. Ils disparaissent immédiatement du catalogue public.

---

## Scénario nominal — Remise en disponibilité

1. Le marchand filtre le catalogue sur « Produits indisponibles ».
2. Il coche les produits réapprovisionnés.
3. Il clique sur « Remettre en disponibilité ».
4. Les produits repassent à `isAvailable = true`.

---

## Règles métier

- L'action groupée s'applique uniquement aux produits du catalogue de la supérette du marchand.
- Un produit `isAvailable = false` n'apparaît plus dans le catalogue public mais reste dans les Kadhia existantes.
- Si un produit dans une Kadhia en `draft` passe en rupture, la validation à la soumission bloque et signale le produit (`PRODUCT_UNAVAILABLE`).
- Le nombre maximum de produits sélectionnés en une action : 50.

---

## Critères d'acceptation

- [ ] Le marchand peut sélectionner plusieurs produits et les marquer indisponibles en un clic.
- [ ] Les produits indisponibles disparaissent du catalogue public immédiatement.
- [ ] Le marchand peut filtrer son catalogue sur « Indisponibles » pour les remettre en stock.
- [ ] La sélection est limitée à 50 produits par action.
- [ ] Un message de confirmation indique combien de produits ont été mis à jour.

---

## Notes techniques

**Endpoint action groupée :**
```http
PATCH /api/merchant/stores/{storeId}/catalog/bulk-availability
```

**Payload :**
```json
{
  "merchant_product_ids": ["<uuid1>", "<uuid2>"],
  "is_available": false
}
```

**Réponse 200 :**
```json
{ "updated_count": 3 }
```

**Requête SQL :**
```sql
UPDATE merchant_products
SET is_available = :isAvailable, updated_at = NOW()
WHERE id IN (:ids) AND shop_id = :shopId
```
Utiliser `setParameter('ids', $ids, ArrayParameterType::STRING)` avec Doctrine DBAL.

**Sécurité :** vérifier que tous les `merchant_product_ids` appartiennent à la supérette du marchand avant la mise à jour (éviter la manipulation d'autres catalogues).
