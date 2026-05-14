# US-043 — Gérer le délai de réponse du marchand

**Epic** : EPIC-005 — Validation marchand
**Sprint** : Sprint 3 — Parcours marchand
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **savoir qu'une commande sans réponse du marchand sera automatiquement annulée après un délai défini**,
afin de **ne pas attendre indéfiniment et pouvoir commander ailleurs**.

---

## Préconditions

- La commande est en statut `submitted`.
- Le marchand n'a pas répondu dans le délai imparti.

---

## Scénario nominal

1. Le client soumet une commande.
2. Le délai de réponse attendu est affiché : « Le marchand répond généralement sous 30 minutes. »
3. Si le marchand ne répond pas dans le délai (ex : 2 heures avant le début du créneau), la commande est automatiquement annulée.
4. Le client reçoit une notification : « Votre commande #0042 a été annulée — le marchand n'a pas pu la traiter à temps. »
5. Le créneau est libéré.
6. Le client peut créer une nouvelle Kadhia.

---

## Scénarios alternatifs

**Créneau dans plus de 4 heures :**
- Le marchand a jusqu'à `starts_at - 2h` pour répondre.

**Créneau dans moins de 2 heures :**
- La commande est refusée à la soumission avec le message : « Ce créneau est trop proche. Choisissez un créneau dans au moins 2 heures. »

---

## Règles métier

- **Délai de réponse minimum :** le créneau doit être dans au moins **2 heures** au moment de la soumission.
- **Délai d'expiration :** une commande `submitted` non traitée **2 heures avant le début du créneau** est automatiquement annulée.
- L'annulation automatique libère le créneau et crée une notification client.
- Le marchand n'est pas pénalisé dans le MVP (pas de score de fiabilité).
- La règle du créneau minimum (2 heures) s'applique aussi à la re-soumission après acceptation partielle.

---

## Critères d'acceptation

- [ ] Une commande soumise moins de 2 heures avant le créneau est refusée avec un message explicite.
- [ ] Une commande non traitée 2 heures avant le créneau est automatiquement annulée.
- [ ] Le créneau est libéré lors de l'annulation automatique.
- [ ] Le client reçoit une notification d'annulation automatique.
- [ ] Le délai minimum est configurable par paramètre Symfony (pas en dur).

---

## Notes techniques

**Validation à la soumission (dans `SubmitOrderProcessor`) :**
```php
$minStartsAt = new \DateTimeImmutable('+2 hours');
if ($slot->getStartsAt() < $minStartsAt) {
    throw new UnprocessableEntityHttpException('PICKUP_SLOT_TOO_SOON');
}
```

**Annulation automatique — Symfony Messenger (Scheduler) :**
```php
// Message planifié lors de la soumission
$expiresAt = $slot->getStartsAt()->modify('-2 hours');
$bus->dispatch(new ExpireOrderMessage($order->getId()), [
    new DelayStamp($expiresAt->getTimestamp() - time()) * 1000),
]);
```

**Handler `ExpireOrderHandler` :**
1. Charger la commande.
2. Si statut != `submitted`, ignorer (déjà traitée).
3. Appeler `Order::cancel()`.
4. Libérer le créneau.
5. Créer la notification client.
6. Flush.

**Paramètre configurable :**
```yaml
# config/services.yaml
parameters:
    app.order.min_slot_lead_hours: 2
    app.order.auto_cancel_lead_hours: 2
```
