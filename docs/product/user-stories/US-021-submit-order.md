# US-021 — Soumettre la commande

**Epic** : EPIC-004 — Rendez-vous et soumission de commande
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **soumettre ma Kadhia au marchand avec le créneau de retrait choisi**,
afin de **déclencher le processus de validation et de préparation de ma commande**.

---

## Préconditions

- Le client a une Kadhia `draft` non vide.
- Le client a sélectionné un créneau de retrait disponible.
- Le client est connecté (la soumission nécessite un compte).

---

## Scénario nominal

1. Le client est sur l'écran de confirmation avec la Kadhia et le créneau sélectionné.
2. Il vérifie le récapitulatif : produits, total TND, créneau de retrait.
3. Il appuie sur « Soumettre ma commande ».
4. Le système crée la commande en statut `submitted` depuis la Kadhia `draft`.
5. La Kadhia `draft` est transformée en commande (elle n'est plus modifiable).
6. Le client voit une page de confirmation avec le numéro de commande.
7. Le client reçoit une notification ou un email de confirmation.

---

## Scénarios alternatifs

**Créneau plus disponible au moment de la soumission** :
- Le système détecte le conflit.
- Il affiche : « Le créneau sélectionné n'est plus disponible. Veuillez en choisir un autre. »
- Le client est renvoyé à l'étape de sélection du créneau.

**Produit devenu indisponible entre la mise en Kadhia et la soumission** :
- Le système signale le(s) produit(s) concerné(s).
- Le client peut supprimer les lignes problématiques et resoumettre, ou annuler.

**Client non connecté** :
- Si le client a composé sa Kadhia en visiteur, le système lui demande de se connecter ou de créer un compte avant soumission.
- La Kadhia visiteur est transférée vers son compte.

---

## Règles métier

- La soumission est irréversible : une commande `submitted` ne peut pas redevenir `draft`.
- Le client peut annuler une commande `submitted` tant que le marchand n'a pas encore répondu.
- La capacité du créneau est décrémentée de 1 à la soumission.
- Les prix sont figés au moment de la soumission (snapshot déjà pris à l'ajout en Kadhia).

---

## Critères d'acceptation

- [ ] La soumission crée bien une commande `submitted` depuis la Kadhia `draft`.
- [ ] La Kadhia `draft` n'est plus modifiable après soumission.
- [ ] La capacité du créneau est décrémentée.
- [ ] La page de confirmation affiche le numéro de commande et le créneau retenu.
- [ ] Un créneau invalide au moment de la soumission bloque avec un message clair.
- [ ] Un produit devenu indisponible au moment de la soumission est signalé.
- [ ] Un client visiteur est invité à se connecter avant soumission.

---

## Notes techniques

- Endpoint : `POST /api/orders` avec `{ kadhiaId, pickupSlotId }`
- La soumission vérifie atomiquement : disponibilité du créneau, disponibilité des produits.
- Utilisation d'une transaction Doctrine pour garantir la cohérence créneau + commande.
- Notification via Symfony Messenger : email de confirmation client + notification marchand.
