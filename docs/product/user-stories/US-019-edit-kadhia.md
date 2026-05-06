# US-019 — Modifier la quantité ou retirer un produit de la Kadhia

**Epic** : EPIC-003 — Gestion Kadhia
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **modifier la quantité d'un produit ou le retirer de ma Kadhia**,
afin de **corriger ma liste de courses avant de soumettre la commande**.

---

## Préconditions

- Le client a une Kadhia `draft` non vide.
- La Kadhia n'a pas encore été soumise.

---

## Scénario nominal — Modification de quantité

1. Le client ouvre sa Kadhia.
2. Il voit la liste des produits avec leur quantité, prix unitaire et sous-total.
3. Il appuie sur le bouton « + » ou « − » à côté d'un produit pour modifier la quantité.
4. Le total général de la Kadhia est mis à jour en temps réel.

---

## Scénario nominal — Suppression d'un produit

1. Le client appuie sur le bouton de suppression (icône poubelle ou glisser à gauche).
2. Une confirmation rapide s'affiche (ex : « Retirer ce produit ? »).
3. Le client confirme.
4. Le produit est retiré de la Kadhia.
5. Si la Kadhia est vide, un état vide est affiché avec une invitation à revenir au catalogue.

---

## Scénarios alternatifs

**Quantité réduite à 0** :
- Réduire la quantité à 0 équivaut à retirer le produit (comportement identique à la suppression).

**Kadhia déjà soumise** :
- Les modifications ne sont plus possibles.
- L'interface est en lecture seule.

---

## Règles métier

- La quantité minimum pour une ligne active est 1.
- Mettre la quantité à 0 supprime la ligne de la Kadhia.
- Le total est recalculé à chaque modification : somme des (quantité × prix unitaire snapshot) de chaque ligne.
- Une Kadhia soumise, acceptée ou au-delà n'est pas modifiable.

---

## Critères d'acceptation

- [ ] Le « + » incrémente la quantité, le « − » décrémente (avec suppression à 0).
- [ ] Le total de la Kadhia est mis à jour en temps réel après chaque modification.
- [ ] La suppression d'un produit affiche une confirmation.
- [ ] La Kadhia vide affiche un état dédié avec retour au catalogue.
- [ ] Les modifications ne sont pas possibles sur une Kadhia soumise.

---

## Notes techniques

- Endpoint mise à jour : `PATCH /api/kadhia/lines/{lineId}` avec `{ quantity: int }`
- Endpoint suppression : `DELETE /api/kadhia/lines/{lineId}`
- Si `quantity = 0` envoyé au PATCH, équivaut à DELETE.
- Le total est calculé côté serveur et retourné dans la réponse.
