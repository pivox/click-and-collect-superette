# US-016 — Proposer un nouveau produit au référentiel

**Epic** : EPIC-011 — Référentiel produit et catalogue marchand
**Sprint** : Sprint 1 — Référentiel produit et catalogue marchand
**Priorité** : Should Have

---

## Récit

En tant que **marchand**,
je veux **proposer un produit qui n'existe pas encore dans le référentiel global**,
afin de **l'ajouter à mon catalogue dès sa validation par l'administrateur**.

---

## Préconditions

- Le marchand a effectué une recherche sans résultat (US-013).
- Le marchand est connecté à son backoffice.

---

## Scénario nominal

1. Le marchand clique sur « Proposer un nouveau produit » depuis la page de recherche sans résultat.
2. Un formulaire s'affiche avec les champs : nom du produit, marque, catégorie, format/volume, unité.
3. Le marchand peut uploader une photo du produit (optionnel).
4. Le marchand peut saisir un code-barres (optionnel).
5. Il soumet la proposition.
6. Le système crée une entrée `ProductReferenceProposal` en statut `pending`.
7. L'administrateur reçoit une notification pour validation (US-030).
8. Le marchand voit la proposition dans un état « En attente de validation ».

---

## Scénarios alternatifs

**Proposition doublon** :
- Si un produit similaire existe déjà dans le référentiel ou en attente de validation, le système le signale :
  « Un produit similaire a déjà été proposé ou existe dans le référentiel. »

**Proposition validée** :
- Le produit est ajouté au référentiel.
- Le marchand est notifié et peut l'ajouter à son catalogue (US-014).

**Proposition refusée** :
- Le marchand est notifié avec la raison du refus.
- Il peut soumettre une nouvelle proposition corrigée.

---

## Règles métier

- Un nouveau produit ne peut pas être publié directement par le marchand.
- La validation par l'administrateur est obligatoire avant publication dans le référentiel.
- Le marchand ne peut pas modifier le nom ou la catégorie d'un produit existant dans le référentiel.
- Un produit peut être proposé par plusieurs marchands ; les doublons sont fusionnés par l'admin.

---

## Critères d'acceptation

- [ ] Le formulaire de proposition est accessible depuis la recherche sans résultat.
- [ ] Les champs obligatoires (nom, catégorie, format) sont validés.
- [ ] La proposition est créée en statut `pending` et visible par l'admin.
- [ ] Le marchand voit le statut de sa proposition (en attente, validé, refusé).
- [ ] Le marchand est notifié lorsque sa proposition est traitée.
- [ ] Une proposition validée est immédiatement recherchable dans le référentiel.

---

## Notes techniques

- Endpoint : `POST /api/merchant/product-proposals` avec `{ name, brandId?, categoryId, format?, volume?, unit?, barcode?, photoUrl? }`
- Entité : `ProductReferenceProposal` avec statut `pending | approved | rejected`.
- L'approbation admin crée automatiquement une `ProductReference` depuis la `ProductReferenceProposal`.
