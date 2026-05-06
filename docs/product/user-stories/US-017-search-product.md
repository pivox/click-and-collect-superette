# US-017 — Rechercher un produit dans le catalogue de la supérette

**Epic** : EPIC-002 — Catalogue produits
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **rechercher un produit par nom ou marque dans le catalogue de la supérette**,
afin de **trouver rapidement ce que je cherche sans parcourir toutes les catégories**.

---

## Préconditions

- Le client est sur la page catalogue d'une supérette active.

---

## Scénario nominal

1. Le client appuie sur le champ de recherche en haut du catalogue.
2. Il saisit un terme de recherche (ex : « lait », « Vitalait », « 1L »).
3. Le système retourne les produits disponibles dont le nom, la marque ou le format correspond.
4. Les résultats s'affichent avec : nom, marque, format, prix TND, photo.
5. Le client peut ajouter un produit directement depuis les résultats (US-003).

---

## Scénarios alternatifs

**Aucun résultat** :
- Le système affiche : « Aucun produit trouvé pour "[terme]". »
- Le client peut effacer sa recherche et revenir au catalogue complet.

**Résultats avec produits indisponibles** :
- Les produits indisponibles apparaissent grisés et non commandables.

---

## Règles métier

- La recherche porte uniquement sur le catalogue actif de la supérette (produits `visible = true`).
- La recherche est insensible à la casse et aux accents.
- La recherche doit fonctionner en français et en arabe.
- Les résultats sont triés par pertinence (correspondance exacte en premier).

---

## Critères d'acceptation

- [ ] La recherche par nom partiel retourne les produits correspondants.
- [ ] La recherche par marque fonctionne.
- [ ] La recherche est déclenchée après 300 ms d'inactivité (debounce).
- [ ] Les résultats affichent photo, nom, marque, format et prix TND.
- [ ] L'absence de résultat affiche un message clair.
- [ ] Le champ de recherche peut être vidé facilement (bouton effacer).

---

## Notes techniques

- Endpoint : `GET /api/shops/{shopId}/products?q={query}`
- Recherche `ILIKE` PostgreSQL sur les champs `name`, `brand`, `format` de `ProductReference`.
- Debounce côté front : 300 ms avant envoi de la requête.
