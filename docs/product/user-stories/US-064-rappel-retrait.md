# US-064 — Rappel de retrait avant expiration du créneau

**Epic** : EPIC-013 — Compte client
**Sprint** : Sprint 4 — Retrait sécurisé
**Priorité** : Could Have

---

## Récit

En tant que **client**,
je veux **recevoir un rappel avant l'heure de mon créneau de retrait**,
afin de **ne pas oublier de passer chercher ma commande et éviter qu'elle soit annulée**.

---

## Préconditions

- Le client a une commande au statut `ready` avec un créneau de retrait à venir.
- Le système de notification est opérationnel (voir US-038).

---

## Scénario nominal

1. La commande passe au statut `ready`.
2. Le système programme un rappel pour 1 heure avant le début du créneau de retrait.
3. À l'heure prévue, le client reçoit une notification in-app (et éventuellement email).
4. La notification contient : nom de la supérette, heure du créneau, numéro de commande.
5. Le client clique sur la notification et est redirigé directement vers son QR code de retrait.

---

## Scénario alternatif — Commande déjà retirée

1. Le rappel est programmé à l'heure de passage en `ready`.
2. Entre-temps, la commande passe à `completed` (retrait effectué).
3. À l'heure du rappel, le système détecte que la commande est déjà complétée.
4. Le rappel n'est pas envoyé (annulation silencieuse).

---

## Scénario alternatif — Commande annulée

1. Le rappel est programmé mais la commande est annulée entre-temps.
2. Même logique : la vérification du statut au moment de l'envoi annule le rappel.

---

## Règles métier

- Le rappel est envoyé **1 heure avant** le début du créneau de retrait.
- Si la commande passe `ready` moins d'1 heure avant le créneau, le rappel est envoyé immédiatement.
- Le rappel n'est envoyé que si la commande est encore au statut `ready` ou `pickup_pending` au moment de l'envoi.
- Un seul rappel par commande (pas de second rappel 30 min avant).
- Le rappel est une notification in-app en priorité. L'email est un canal secondaire si la notification n'a pas été lue dans les 15 minutes.

---

## Critères d'acceptation

- [ ] Un rappel est programmé automatiquement quand une commande passe au statut `ready`.
- [ ] Le rappel est envoyé 1 heure avant le créneau (ou immédiatement si < 1h).
- [ ] Le rappel n'est pas envoyé si la commande est déjà `completed` ou `cancelled` à l'heure prévue.
- [ ] La notification contient : nom de la supérette, heure du créneau, numéro de commande.
- [ ] Un clic sur la notification redirige vers le QR code de retrait.
- [ ] Un seul rappel par commande.

---

## Notes techniques

**Implémentation via Symfony Messenger + DelayStamp :**

```php
// Dans MarkOrderReadyProcessor
$pickupSlot = $order->getPickupSlot();
$reminderAt = $pickupSlot->getStartsAt()->modify('-1 hour');
$delay       = max(0, $reminderAt->getTimestamp() - time());

$this->messageBus->dispatch(
    new SendPickupReminderMessage($order->getId()),
    [new DelayStamp($delay * 1000)]
);
```

**Message handler `SendPickupReminderHandler` :**
```php
public function __invoke(SendPickupReminderMessage $message): void
{
    $order = $this->orderRepository->find($message->orderId);

    // Vérification du statut au moment de l'envoi
    if (!in_array($order?->getStatus(), [OrderStatus::Ready, OrderStatus::PickupPending])) {
        return; // Annulation silencieuse
    }

    $this->notificationService->sendPickupReminder($order);
}
```

**Contenu de la notification :**
```json
{
  "type": "pickup_reminder",
  "title": "Votre Kadhia est prête !",
  "body": "Commande #0042 — à retirer avant 14h30 chez Ezzahra Market.",
  "action_url": "/me/orders/{orderId}/pickup-qr",
  "order_id": "<uuid>"
}
```

**Canal email (fallback) :**
Déclenché via un second `DelayStamp` de +15 minutes si la notification in-app n'est pas marquée `read` dans l'entité `Notification`.

**Pas de nouvelle entité** — utilise `Notification` (US-038) avec `type = 'pickup_reminder'`.

**Dépendance :** nécessite que US-038 (notifications client) et US-025 (QR code de retrait) soient implémentés.
