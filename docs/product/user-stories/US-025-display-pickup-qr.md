# US-025 — Afficher le QR code de retrait

**Epic** : EPIC-007 — Retrait sécurisé
**Sprint** : Sprint 4 — Retrait sécurisé
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **afficher le QR code de retrait de ma commande sur mon téléphone**,
afin de **le présenter au marchand pour finaliser le retrait de ma Kadhia**.

---

## Préconditions

- La commande est en statut `ready`.
- Le client est connecté et accède à sa commande.

---

## Scénario nominal

1. Le client reçoit une notification : « Votre Kadhia est prête ! »
2. Il ouvre l'application et voit sa commande en statut « Prête ».
3. Il appuie sur « Présenter mon QR code ».
4. L'application affiche le QR code en grand format, sur fond blanc, avec luminosité maximale.
5. En dessous du QR code : numéro de commande, nom de la supérette, créneau de retrait.
6. Le client présente son téléphone au marchand pour scan.

---

## Scénarios alternatifs

**Commande non encore prête** :
- Le bouton « QR code de retrait » n'est pas accessible.
- Le statut en cours est affiché (ex : « En préparation »).

**QR code expiré** :
- Si le client n'est pas venu dans les 24h après passage en `ready`, le QR code expire.
- L'application affiche : « Ce QR code a expiré. Contactez la supérette. »

---

## Règles métier

- Le QR code est généré lors du passage en statut `ready`.
- Il est unique par commande et à usage unique.
- Sa durée de validité est de 24 heures après génération.
- Le QR code ne contient pas d'information sensible : il encode uniquement un token opaque.

---

## Critères d'acceptation

- [ ] Le QR code n'est accessible que pour les commandes en statut `ready`.
- [ ] Le QR code est affiché en grand format lisible (minimum 250×250px).
- [ ] La luminosité de l'écran est portée au maximum lors de l'affichage.
- [ ] Le numéro de commande et le créneau sont affichés sous le QR code.
- [ ] Un QR code expiré affiche un message clair.
- [ ] Le QR code est lisible dans des conditions d'éclairage variables (magasin).

---

## Notes techniques

- Le token est un UUID v4 stocké dans `PickupSession.token`, généré lors du passage en `ready`.
- Rendu QR code côté client via une librairie JS (ex : `qrcode.js`).
- Endpoint récupération du token : `GET /api/orders/{id}/pickup-session`
- L'expiration est vérifiée côté serveur au moment du scan, pas côté client.
