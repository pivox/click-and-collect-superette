# US-007 — Valider le retrait par double validation

**Epic** : EPIC-007 — Retrait sécurisé
**Sprint** : Sprint 4 — Retrait sécurisé
**Priorité** : Must Have

---

## Récit

En tant que **client et marchand**,
nous voulons **valider la remise de la commande par un QR code de retrait et une confirmation double**,
afin de **finaliser le retrait de façon sécurisée et traçable**.

---

## Préconditions

- La commande est en statut `ready`.
- Le client est en magasin avec son QR code de retrait.
- Le marchand est connecté à son backoffice ou dispose d'un scanner.

---

## Scénario nominal

1. Le client ouvre l'application et affiche le QR code de retrait de sa commande.
2. Le marchand scanne le QR code depuis son backoffice.
3. Le système vérifie que le QR code correspond bien à une commande `ready` de cette supérette.
4. La commande passe en statut `pickup_pending`.
5. Le marchand confirme la remise sur son interface : « Remettre la Kadhia ».
6. Le client confirme la réception sur son interface : « J'ai bien récupéré ma Kadhia ».
7. La commande passe en statut `completed`.
8. Un récapitulatif est affiché aux deux parties.

---

## Scénarios alternatifs

**QR code expiré ou invalide** :
- Le système affiche une erreur : « Ce QR code n'est pas valide ou a expiré. »
- La commande reste en `ready`.

**Client confirme mais marchand n'a pas encore scanné** :
- Interdit dans le flux nominal. Le scan marchand doit précéder la confirmation client.

**Marchand seul disponible (client ne confirme pas)** :
- En cas de non-confirmation client dans un délai de 5 minutes, le marchand peut forcer la complétion avec une note.

---

## Règles métier

- Le QR code de retrait est unique par commande et à usage unique.
- Seul le marchand de la supérette concernée peut scanner et déclencher le retrait.
- La double validation est obligatoire dans le flux normal.
- La date et l'heure de complétion sont enregistrées.

---

## Critères d'acceptation

- [ ] Le QR code de retrait est bien visible sur l'écran du client (grande taille, lisible en intérieur).
- [ ] Le scan d'un QR code valide identifie correctement la commande et le client.
- [ ] La commande passe en `pickup_pending` après scan marchand.
- [ ] La commande passe en `completed` après confirmation des deux parties.
- [ ] Un QR code déjà utilisé est refusé avec un message d'erreur clair.
- [ ] Le récapitulatif de commande finalisée est accessible dans l'historique.

---

## Notes techniques

- Le QR code encode un token unique `PickupSession.token` (UUID signé, expiration 24h).
- Endpoint scan : `POST /api/merchant/pickup-sessions/scan` avec `{ token: string }`
- Endpoint confirmation marchand : `PATCH /api/merchant/pickup-sessions/{id}/confirm`
- Endpoint confirmation client : `PATCH /api/customer/pickup-sessions/{id}/confirm`
- Transition finale via `OrderStatusTransitionService::complete()`.
