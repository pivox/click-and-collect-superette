# US-037 — Accepter partiellement une commande

**Epic** : EPIC-005 — Validation marchand
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **indiquer quels produits d'une commande je peux honorer et lesquels sont indisponibles**,
afin de **ne pas bloquer le client ni préparer des articles manquants**.

---

## Préconditions

- Le marchand est connecté et propriétaire de la supérette.
- La commande est en statut `submitted`.

---

## Scénario nominal

1. Le marchand consulte une commande soumise.
2. Certains articles sont épuisés ou indisponibles.
3. Le marchand clique sur « Acceptation partielle ».
4. Il coche les lignes qu'il **ne peut pas** honorer, avec une raison par ligne (optionnel).
5. Il valide.
6. La commande passe en statut `partially_accepted`.
7. La Kadhia associée repasse en statut `draft` avec uniquement les lignes acceptées.
8. Le client est notifié : « Votre commande a été partiellement acceptée. Veuillez vérifier votre Kadhia. »
9. Le client peut modifier sa Kadhia `draft` (ajuster les quantités, remplacer des articles) et la resoumettre.
10. À la resoumission, la commande repasse en `submitted` avec les nouvelles lignes.

---

## Scénarios alternatifs

**Toutes les lignes refusées** :
- Cela équivaut à un refus complet → utiliser `POST .../reject` à la place.

**Aucune ligne refusée** :
- Cela équivaut à une acceptation complète → utiliser `POST .../accept` à la place.

---

## Règles métier

- L'acceptation partielle n'est possible que depuis `submitted`.
- La Kadhia source repasse en `draft` avec seulement les lignes acceptées (les lignes refusées sont supprimées de la Kadhia).
- La relation `Kadhia → Order` est conservée. La re-soumission met à jour la commande existante.
- Le créneau de retrait reste réservé après acceptation partielle.
- Si le client ne resoumets pas, la commande reste en `partially_accepted` indéfiniment.

---

## Critères d'acceptation

- [ ] Le marchand peut accepter une commande en sélectionnant les lignes refusées.
- [ ] La commande passe en `partially_accepted`.
- [ ] La Kadhia repasse en `draft` avec uniquement les lignes acceptées.
- [ ] Le client voit clairement quels articles ont été retirés.
- [ ] Le client peut modifier et resoumettre la Kadhia.
- [ ] La re-soumission met à jour la commande existante (pas de doublon).
- [ ] Un refus de toutes les lignes est refusé par ce flux (rediriger vers `/reject`).

---

## Notes techniques

**Endpoint :**
```http
POST /api/merchant/stores/{storeId}/orders/{orderId}/partially-accept
```

**Payload :**
```json
{
  "rejected_merchant_product_ids": ["<merchantProductId1>", "<merchantProductId2>"],
  "notes": "Rupture de stock Vitalait 1L."
}
```

**Réponse 200 :** `MerchantOrderOutput` avec statut `partially_accepted`.

**Logique métier :**
1. Appeler `Order::partiallyAccept()` (méthode déjà présente dans le domaine).
2. Récupérer la Kadhia liée à l'ordre.
3. Supprimer les `KadhiaLine` dont le `merchant_product_id` est dans `rejected_merchant_product_ids`.
4. Passer la Kadhia en `draft` : `$kadhia->setStatus(KadhiaStatus::Draft)`.
5. Stocker les `notes` de refus partiel dans `Order::rejectionReason` (champ existant).
6. Flush.

- Sécurité : `MerchantShopAccessChecker::denyUnlessMerchantOwnsShop()`.
- Si `rejected_merchant_product_ids` est vide → 422 `NO_LINES_REJECTED`.
- Si `rejected_merchant_product_ids` contient toutes les lignes → 422 `USE_REJECT_ENDPOINT`.
