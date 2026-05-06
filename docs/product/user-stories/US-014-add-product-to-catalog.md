# US-014 — Ajouter un produit du référentiel à son catalogue

**Epic** : EPIC-011 — Référentiel produit et catalogue marchand
**Sprint** : Sprint 1 — Référentiel produit et catalogue marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **ajouter un produit trouvé dans le référentiel global à mon catalogue**,
afin de **le rendre disponible à mes clients**.

---

## Préconditions

- Le marchand a trouvé un produit dans le référentiel (US-013).
- Le produit n'est pas encore dans le catalogue du marchand.

---

## Scénario nominal

1. Le marchand clique sur « Sélectionner » depuis les résultats de recherche.
2. Un formulaire d'ajout s'affiche avec les informations du référentiel pré-remplies (nom, marque, photo).
3. Le marchand saisit le prix en TND (champ obligatoire).
4. Le marchand choisit la disponibilité initiale : disponible ou indisponible.
5. Le marchand valide en cliquant sur « Ajouter à mon catalogue ».
6. Le produit apparaît dans son catalogue avec le statut choisi.

---

## Scénarios alternatifs

**Prix non renseigné** :
- La validation est bloquée avec un message : « Le prix est obligatoire. »

**Produit déjà dans le catalogue** :
- Si le marchand tente d'ajouter un produit déjà présent, le système affiche :
  « Ce produit est déjà dans votre catalogue. Voulez-vous modifier ses paramètres ? »

---

## Règles métier

- Le prix est obligatoire et doit être un montant positif en TND.
- Le prix est stocké en millimes (entier) : 2800 = 2,800 TND.
- La disponibilité par défaut est `available = true`.
- La visibilité par défaut est `visible = true`.
- Le marchand ne peut pas modifier le nom, la marque ou la photo du référentiel (il peut signaler une correction via US-016).
- Un même produit du référentiel peut être dans le catalogue de plusieurs supérettes avec des prix différents.

---

## Critères d'acceptation

- [ ] Le formulaire d'ajout est pré-rempli avec les données du référentiel.
- [ ] Le champ prix est obligatoire et valide uniquement les montants positifs.
- [ ] Le produit ajouté apparaît immédiatement dans le catalogue du marchand.
- [ ] Le statut de disponibilité est correctement appliqué.
- [ ] L'ajout d'un doublon est détecté et redirige vers la modification.
- [ ] Le prix est affiché avec 3 décimales en TND.

---

## Notes techniques

- Endpoint : `POST /api/merchant/catalog` avec `{ productReferenceId, price: int (millimes), available: bool, visible: bool }`
- Entité créée : `MerchantProductOffer` liée à `ProductReference` et `Shop`.
- Contrainte d'unicité : `(shop_id, product_reference_id)` unique en base.
