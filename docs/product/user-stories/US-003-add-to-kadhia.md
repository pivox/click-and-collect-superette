# US-003 — Ajouter un produit à la Kadhia

**Epic** : EPIC-003 — Gestion Kadhia
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **ajouter un produit disponible à ma Kadhia**,
afin de **constituer ma liste de courses pour cette supérette**.

---

## Préconditions

- Le client est sur la page catalogue ou la fiche produit d'une supérette.
- Le produit est disponible et visible.
- Le client est connecté ou en session visiteur avec une Kadhia temporaire.

---

## Scénario nominal

1. Le client voit un produit qui l'intéresse dans le catalogue.
2. Il appuie sur le bouton « + Ajouter » du produit.
3. Le produit est ajouté à la Kadhia avec une quantité de 1.
4. Un retour visuel immédiat confirme l'ajout (badge sur l'icône Kadhia, mini-toast).
5. Si le produit est déjà dans la Kadhia, la quantité est incrémentée de 1.

---

## Scénarios alternatifs

**Produit devenu indisponible entre le chargement et l'ajout** :
- Le système retourne une erreur et affiche : « Ce produit n'est plus disponible. »
- La Kadhia n'est pas modifiée.

**Kadhia appartenant à une autre supérette** :
- Si le client a déjà une Kadhia active pour une autre supérette, afficher un avertissement :
  « Vous avez déjà une Kadhia en cours chez [nom supérette]. Voulez-vous l'annuler et commencer une nouvelle Kadhia ici ? »
- Le client confirme ou annule.

---

## Règles métier

- Une Kadhia est liée à une seule supérette à la fois.
- Un client ne peut avoir qu'une seule Kadhia active en statut `draft`.
- La quantité minimum par ligne est 1. Il n'y a pas de maximum fixé dans le MVP.
- Le prix enregistré dans la ligne Kadhia est le prix au moment de l'ajout.

---

## Critères d'acceptation

- [ ] Le bouton « + Ajouter » est présent sur chaque produit disponible.
- [ ] L'ajout est confirmé visuellement dans les 300 ms (badge + toast).
- [ ] Si le produit est déjà dans la Kadhia, la quantité est bien incrémentée.
- [ ] Un produit indisponible ne peut pas être ajouté.
- [ ] La Kadhia multi-supérettes déclenche bien l'avertissement.
- [ ] Le total de la Kadhia est mis à jour après chaque ajout.

---

## Notes techniques

- Endpoint : `POST /api/kadhia/lines` avec `{ productOfferId, quantity: 1 }`
- La Kadhia `draft` est créée automatiquement si elle n'existe pas.
- Le prix snapshot (`unitPrice`) est copié depuis `MerchantProductOffer.price` à l'ajout.
- La Kadhia est identifiée par session (visiteur) ou par compte client (connecté).
