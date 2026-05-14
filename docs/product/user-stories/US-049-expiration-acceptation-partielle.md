# US-049 — Gérer l'expiration d'une acceptation partielle sans re-soumission

**Epic** : EPIC-005 — Validation marchand
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **système**,
je veux **annuler automatiquement une commande en `partially_accepted` si le client ne resoumets pas dans un délai défini**,
afin de **libérer le créneau et ne pas bloquer indéfiniment le planning du marchand**.

---

## Préconditions

- Une commande est en statut `partially_accepted`.
- Le client n'a pas resoumis sa Kadhia modifiée.
- Le créneau initialement réservé approche de son terme.

---

## Scénario nominal

1. Le marchand accepte partiellement une commande → statut `partially_accepted`.
2. Le client reçoit une notification : « Modifiez et resoumettez votre Kadhia avant [deadline]. »
3. La deadline est : **2 heures avant le début du créneau réservé**.
4. Si le client ne resoumets pas avant la deadline, le système annule automatiquement la commande.
5. Le créneau est libéré.
6. Le client reçoit une notification : « Votre commande #0042 a été annulée — délai de resoumission dépassé. »
7. Le client peut créer une nouvelle Kadhia si besoin.

---

## Règles métier

- La deadline de re-soumission est identique à la règle d'expiration des commandes `submitted` (US-043) : 2 heures avant le créneau.
- Le créneau reste réservé jusqu'à l'expiration ou la re-soumission.
- Si le client resoumets avant la deadline, le flux normal reprend (commande → `submitted`).
- La notification de deadline est envoyée **4 heures avant le créneau** (alerte préventive).
- L'annulation automatique crée un `OrderStatusLog` avec note `AUTO_CANCELLED_PARTIAL_TIMEOUT`.

---

## Critères d'acceptation

- [ ] Le client reçoit une alerte 4 heures avant le créneau s'il n'a pas resoumis.
- [ ] La commande est annulée automatiquement 2 heures avant le créneau si non resoumise.
- [ ] Le créneau est libéré lors de l'annulation automatique.
- [ ] La notification d'annulation est envoyée au client.
- [ ] L'annulation automatique est traçable dans `OrderStatusLog`.

---

## Notes techniques

**Planification (Symfony Messenger) :**
À la création du statut `partially_accepted`, planifier deux messages différés :

```php
// Alerte 4h avant le créneau
$alertAt = $slot->getStartsAt()->modify('-4 hours');
$bus->dispatch(new PartialOrderAlertMessage($order->getId()), [
    new DelayStamp(max(0, $alertAt->getTimestamp() - time()) * 1000),
]);

// Annulation 2h avant le créneau
$cancelAt = $slot->getStartsAt()->modify('-2 hours');
$bus->dispatch(new ExpirePartialOrderMessage($order->getId()), [
    new DelayStamp(max(0, $cancelAt->getTimestamp() - time()) * 1000),
]);
```

**`ExpirePartialOrderHandler` :**
1. Charger la commande.
2. Si statut != `partially_accepted`, ignorer (déjà resoumise ou annulée).
3. `Order::cancel()`.
4. Libérer le créneau.
5. Créer notification client.
6. Insérer `OrderStatusLog` avec note `AUTO_CANCELLED_PARTIAL_TIMEOUT`.
7. Flush.
