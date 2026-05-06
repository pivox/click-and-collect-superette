# US-029 — Superviser le référentiel produit global

**Epic** : EPIC-009 — Administration plateforme
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Must Have

---

## Récit

En tant qu'**administrateur plateforme**,
je veux **consulter, corriger et enrichir le référentiel produit global**,
afin de **maintenir des données produit fiables et cohérentes pour tous les marchands**.

---

## Préconditions

- L'administrateur est connecté à l'interface d'administration.

---

## Scénario nominal — Correction d'un produit

1. L'administrateur accède à la section « Référentiel produit ».
2. Il recherche un produit par nom, marque ou code-barres.
3. Il sélectionne le produit à corriger.
4. Il modifie les champs : nom, marque, catégorie, format, volume, unité, photo.
5. Il sauvegarde.
6. Les modifications sont visibles dans tous les catalogues marchands qui utilisent ce produit.

---

## Scénario nominal — Ajout manuel d'un produit

1. L'administrateur clique sur « Ajouter un produit ».
2. Il renseigne : nom, marque, catégorie, format, volume, unité, photo, code-barres (optionnel).
3. Il publie le produit.
4. Le produit est disponible à la recherche pour les marchands.

---

## Scénarios alternatifs

**Produit en doublon détecté** :
- Le système signale un produit similaire lors de la saisie.
- L'administrateur peut confirmer le doublon ou fusionner.

---

## Règles métier

- Les modifications du référentiel s'appliquent à tous les catalogues marchands utilisant ce produit.
- L'administrateur ne peut pas modifier le prix d'un produit dans le référentiel (le prix appartient au marchand).
- Un produit supprimé du référentiel passe en statut `archived` : il reste dans les commandes existantes mais n'est plus proposé aux marchands.
- Les catégories et marques sont gérées séparément (listes de référence).

---

## Critères d'acceptation

- [ ] L'administrateur peut rechercher un produit dans le référentiel.
- [ ] L'administrateur peut modifier le nom, la catégorie, le format ou la photo d'un produit.
- [ ] Les modifications sont répercutées dans tous les catalogues marchands.
- [ ] L'administrateur peut ajouter manuellement un nouveau produit.
- [ ] Un produit archivé ne peut plus être ajouté à de nouveaux catalogues.
- [ ] La liste des produits est paginée et filtrable par catégorie et marque.

---

## Notes techniques

- Endpoint : `GET /api/admin/product-references?q=&category=&brand=`
- Endpoint : `PATCH /api/admin/product-references/{id}`
- Endpoint : `POST /api/admin/product-references`
- Endpoint : `PATCH /api/admin/product-references/{id}/archive`
- Les modifications déclenchent l'invalidation du cache catalogue pour les supérettes concernées.
