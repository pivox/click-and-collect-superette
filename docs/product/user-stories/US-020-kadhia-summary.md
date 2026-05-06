# US-020 — Visualiser le récapitulatif de la Kadhia avec le total en TND

**Epic** : EPIC-003 — Gestion Kadhia
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **voir le récapitulatif de ma Kadhia avec le détail des produits et le montant total en TND**,
afin de **vérifier ma commande avant de la soumettre**.

---

## Préconditions

- Le client a une Kadhia `draft` avec au moins un produit.

---

## Scénario nominal

1. Le client ouvre l'écran Kadhia (accessible depuis l'icône panier ou le bas de l'écran).
2. Le récapitulatif affiche :
   - Nom de la supérette.
   - Liste des lignes : photo miniature, nom du produit, marque, format, quantité, prix unitaire TND, sous-total.
   - Total général en TND (en gras, taille large).
3. Un bouton « Choisir un rendez-vous » permet de passer à l'étape suivante.
4. Un bouton « Modifier » permet de revenir au catalogue.

---

## Scénarios alternatifs

**Kadhia vide** :
- L'écran affiche un état vide : « Votre Kadhia est vide. »
- Un bouton « Voir le catalogue » est affiché.

---

## Règles métier

- Le total est la somme des sous-totaux de chaque ligne (quantité × prix unitaire snapshot).
- Les prix sont en TND avec 3 décimales (ex : 12,500 TND).
- Le récapitulatif reflète toujours l'état serveur de la Kadhia.
- Le bouton de validation est désactivé si la Kadhia est vide.

---

## Critères d'acceptation

- [ ] Toutes les lignes de la Kadhia sont listées avec photo, nom, marque, format, quantité, prix unitaire et sous-total.
- [ ] Le total général est affiché en TND avec 3 décimales.
- [ ] Le total se met à jour après chaque modification de quantité.
- [ ] La Kadhia vide affiche un état dédié.
- [ ] Le bouton « Choisir un rendez-vous » est actif uniquement si la Kadhia est non vide.
- [ ] L'écran est lisible sur mobile (portrait, 360px minimum).

---

## Notes techniques

- Endpoint : `GET /api/kadhia` retourne les lignes et le total calculé côté serveur.
- Les prix sont stockés en millimes, affichés avec division par 1000 et 3 décimales.
- Attention RTL : l'affichage des prix et des totaux doit être adapté en mode arabe.
