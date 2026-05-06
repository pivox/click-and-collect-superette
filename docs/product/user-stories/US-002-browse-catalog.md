# US-002 — Consulter le catalogue marchand

**Epic** : EPIC-002 — Catalogue produits
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **consulter les produits disponibles dans la supérette**,
afin de **choisir ce que je veux mettre dans ma Kadhia**.

---

## Préconditions

- Le client a accédé à la supérette (via QR code ou lien direct).
- La supérette est active.
- Le catalogue marchand contient au moins un produit disponible.

---

## Scénario nominal

1. Le client arrive sur la page d'accueil de la supérette.
2. Le catalogue s'affiche avec les produits disponibles groupés par catégorie.
3. Chaque produit affiche : nom, marque, format/volume, prix en TND, photo si disponible.
4. Le client peut faire défiler le catalogue ou naviguer par catégorie.
5. Les produits indisponibles sont affichés en grisé avec la mention « Indisponible ».

---

## Scénarios alternatifs

**Catalogue vide** :
- Le système affiche : « Aucun produit disponible pour le moment. »

**Produit sans photo** :
- Un pictogramme générique est affiché à la place de la photo.

**Chargement lent** :
- Des squelettes de chargement (skeleton screens) sont affichés pendant le chargement.

---

## Règles métier

- Seuls les produits avec `available = true` et `visible = true` sont affichés.
- Le prix affiché est le prix défini par le marchand, en TND.
- Les catégories sans produit disponible ne sont pas affichées.
- L'ordre d'affichage par défaut : catégories alphabétiques, produits alphabétiques dans chaque catégorie.

---

## Critères d'acceptation

- [ ] Le catalogue affiche les produits disponibles groupés par catégorie.
- [ ] Chaque produit affiche son nom, sa marque, son format/volume, son prix en TND.
- [ ] Les produits indisponibles sont visuellement distincts et non commandables.
- [ ] La navigation par catégorie fonctionne.
- [ ] Le catalogue se charge en moins de 3 secondes sur une connexion 4G standard.
- [ ] L'affichage est lisible et utilisable sur mobile (écran 360px minimum).

---

## Notes techniques

- Endpoint : `GET /api/shops/{shopId}/products?available=true`
- Pagination recommandée : 20 produits par page ou scroll infini.
- Les images produits sont servies depuis le CDN, avec fallback sur un placeholder.
- Les prix sont stockés en millimes (entier) et affichés avec 3 décimales TND (ex : 2,800 TND).
