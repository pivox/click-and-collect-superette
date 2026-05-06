# US-030 — Valider les propositions de nouveaux produits

**Epic** : EPIC-009 — Administration plateforme
**Sprint** : Sprint 5 — Administration minimale
**Priorité** : Must Have

---

## Récit

En tant qu'**administrateur plateforme**,
je veux **examiner et valider ou refuser les propositions de nouveaux produits soumises par les marchands**,
afin de **maintenir la qualité et la cohérence du référentiel global**.

---

## Préconditions

- Au moins une `ProductReferenceProposal` est en statut `pending`.
- L'administrateur est connecté à l'interface d'administration.

---

## Scénario nominal — Validation

1. L'administrateur accède à la file des propositions en attente.
2. Il consulte le détail d'une proposition : nom, marque, catégorie, format, photo, code-barres, marchand proposant.
3. Il vérifie qu'aucun doublon n'existe dans le référentiel.
4. Il valide la proposition.
5. Le système crée automatiquement une `ProductReference` depuis les données de la proposition.
6. Le marchand proposant est notifié : « Votre produit [nom] a été ajouté au référentiel. »
7. Le produit est immédiatement disponible à la recherche pour tous les marchands.

---

## Scénario nominal — Refus

1. L'administrateur sélectionne une proposition.
2. Il clique sur « Refuser ».
3. Il saisit la raison : doublon existant, données insuffisantes, produit hors périmètre, autre.
4. La proposition passe en statut `rejected`.
5. Le marchand est notifié avec la raison du refus.

---

## Scénarios alternatifs

**Doublon détecté** :
- Lors de la validation, le système affiche un avertissement avec le produit similaire.
- L'administrateur peut valider quand même, refuser ou fusionner.

**Fusion de propositions** :
- Si plusieurs marchands ont proposé le même produit, l'administrateur peut fusionner les propositions en une seule `ProductReference`.

---

## Règles métier

- La validation est irréversible : un produit validé ne peut qu'être archivé, pas supprimé.
- Le refus n'empêche pas le marchand de soumettre une nouvelle proposition corrigée.
- L'administrateur peut modifier les données d'une proposition avant validation (correction orthographique, catégorie…).

---

## Critères d'acceptation

- [ ] La liste des propositions en attente est visible avec date, marchand proposant et aperçu du produit.
- [ ] La validation crée automatiquement la `ProductReference` et notifie le marchand.
- [ ] Le refus avec raison notifie le marchand.
- [ ] L'administrateur peut modifier les données de la proposition avant validation.
- [ ] Un avertissement de doublon est affiché si un produit similaire existe déjà.
- [ ] Les propositions traitées (validées/refusées) sont archivées et consultables.

---

## Notes techniques

- Endpoint liste : `GET /api/admin/product-proposals?status=pending`
- Endpoint validation : `POST /api/admin/product-proposals/{id}/approve` avec `{ overrides?: { name?, categoryId?, ... } }`
- Endpoint refus : `POST /api/admin/product-proposals/{id}/reject` avec `{ reason: string }`
- La création de `ProductReference` depuis la proposition est atomique (Doctrine transaction).
- Notification marchand via Symfony Messenger.
