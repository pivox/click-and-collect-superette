# US-056 — Déclarer une fermeture exceptionnelle de la supérette

**Epic** : EPIC-002 — Catalogue et disponibilité
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Should Have

---

## Récit

En tant que **marchand**,
je veux **bloquer ma supérette pour une journée ou une plage horaire précise**,
afin de **ne plus recevoir de commandes sur un créneau exceptionnel sans avoir à supprimer mes créneaux récurrents**.

---

## Préconditions

- Le marchand est connecté et propriétaire de la supérette.
- Des créneaux de retrait sont configurés pour la période visée.

---

## Scénario nominal

1. Le marchand accède aux paramètres de ses créneaux de retrait.
2. Il sélectionne « Ajouter une fermeture exceptionnelle ».
3. Il choisit une date (ou une plage de dates) et éventuellement un motif libre.
4. Il confirme.
5. Les créneaux de retrait de cette période passent en `isActive = false` temporairement.
6. Les créneaux redeviennent actifs automatiquement après la fin de la fermeture.

---

## Scénario alternatif — Commandes déjà soumises sur le créneau bloqué

1. Lors de la déclaration de fermeture, le système détecte des commandes au statut `submitted` ou `accepted` sur les créneaux concernés.
2. Le marchand voit un avertissement : « 3 commandes sont en attente sur ces créneaux ».
3. Il choisit : annuler ces commandes automatiquement (avec notification client) ou les laisser ouvertes pour les traiter manuellement.

---

## Règles métier

- Une fermeture exceptionnelle ne supprime pas les créneaux récurrents — elle les désactive sur la plage visée uniquement.
- Si la fermeture est déclarée après qu'un client a déjà soumis une commande sur le créneau, les commandes concernées ne sont pas automatiquement annulées sans accord explicite du marchand.
- La fermeture s'applique à tous les créneaux qui chevauchent la plage déclarée.
- Le catalogue produit reste visible pendant une fermeture — seule la soumission de commande est bloquée si aucun créneau n'est disponible.

---

## Critères d'acceptation

- [ ] Le marchand peut créer une fermeture exceptionnelle avec date de début et de fin.
- [ ] Les créneaux concernés n'apparaissent plus dans le sélecteur client pendant la fermeture.
- [ ] Un avertissement est affiché si des commandes actives chevauchent la fermeture.
- [ ] La fermeture est visible dans la liste des créneaux avec un badge « Fermé ».
- [ ] Le marchand peut annuler une fermeture exceptionnelle avant qu'elle débute.

---

## Notes techniques

**Nouvelle entité `ExceptionalClosure` :**
```php
#[ORM\Entity]
class ExceptionalClosure
{
    private Uuid $id;
    private Shop $shop;
    private \DateTimeImmutable $startsAt;
    private \DateTimeImmutable $endsAt;
    private ?string $reason = null;
    private \DateTimeImmutable $createdAt;
}
```

**Endpoints :**
```http
GET    /api/merchant/stores/{storeId}/closures
POST   /api/merchant/stores/{storeId}/closures
DELETE /api/merchant/stores/{storeId}/closures/{closureId}
```

**Payload POST :**
```json
{
  "starts_at": "2026-05-20T00:00:00+01:00",
  "ends_at": "2026-05-20T23:59:59+01:00",
  "reason": "Inventaire annuel"
}
```

**Impact sur la disponibilité des créneaux :**
Le `PickupSlotCollectionProvider` doit exclure les créneaux dont la plage chevauche une `ExceptionalClosure` active de la supérette :

```sql
SELECT ps.*
FROM pickup_slots ps
WHERE ps.shop_id = :shopId
  AND ps.is_active = true
  AND NOT EXISTS (
    SELECT 1 FROM exceptional_closures ec
    WHERE ec.shop_id = :shopId
      AND ec.starts_at <= ps.ends_at
      AND ec.ends_at   >= ps.starts_at
  )
```

**Migration :** ajouter la table `exceptional_closures`.
