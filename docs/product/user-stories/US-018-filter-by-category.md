# US-018 — Filtrer le catalogue par catégorie

**Epic** : EPIC-002 — Catalogue produits
**Sprint** : Sprint 2 — Parcours client
**Priorité** : Must Have

---

## Récit

En tant que **client**,
je veux **filtrer le catalogue par catégorie de produits**,
afin de **naviguer rapidement dans les rayons sans défiler tout le catalogue**.

---

## Préconditions

- Le client est sur la page catalogue d'une supérette active.
- Le catalogue contient des produits dans au moins deux catégories différentes.

---

## Scénario nominal

1. Le client voit les catégories disponibles affichées sous forme d'onglets ou de chips horizontaux.
2. Il appuie sur une catégorie (ex : « Produits laitiers », « Boissons »).
3. Le catalogue filtre les produits pour n'afficher que ceux de la catégorie sélectionnée.
4. Le filtre actif est visuellement mis en évidence.
5. Le client peut appuyer sur « Tout » pour revenir à la vue complète.

---

## Scénarios alternatifs

**Catégorie sans produit disponible** :
- La catégorie n'est pas affichée dans les filtres.

**Combinaison recherche + catégorie** :
- Le client peut filtrer par catégorie et rechercher en même temps.
- Les deux filtres s'appliquent de façon cumulée.

---

## Règles métier

- Seules les catégories ayant au moins un produit `visible = true` dans la supérette sont affichées.
- Le filtre par catégorie se cumule avec la recherche textuelle.
- La catégorie « Tout » réinitialise le filtre catégorie mais conserve la recherche.

---

## Critères d'acceptation

- [ ] Les catégories disponibles sont affichées en chips ou onglets horizontaux.
- [ ] La sélection d'une catégorie filtre les produits immédiatement.
- [ ] La catégorie active est visuellement différenciée.
- [ ] Le filtre « Tout » revient à la vue complète.
- [ ] Les catégories sans produit disponible ne sont pas affichées.
- [ ] La combinaison catégorie + recherche textuelle fonctionne correctement.

---

## Notes techniques

- Endpoint : `GET /api/shops/{shopId}/products?category={categoryId}`
- Les catégories disponibles : `GET /api/shops/{shopId}/categories` (retourne seulement les catégories ayant des produits visibles).
- L'ordre des catégories est alphabétique dans le MVP.
