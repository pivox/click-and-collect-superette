# US-013 — Rechercher un produit dans le référentiel global

**Epic** : EPIC-011 — Référentiel produit et catalogue marchand
**Sprint** : Sprint 1 — Référentiel produit et catalogue marchand
**Priorité** : Must Have

---

## Récit

En tant que **marchand**,
je veux **rechercher un produit dans le référentiel global tunisien**,
afin de **l'ajouter à mon catalogue sans tout ressaisir manuellement**.

---

## Préconditions

- Le marchand est connecté à son backoffice.
- Le référentiel global contient des produits indexés.

---

## Scénario nominal

1. Le marchand accède à la section « Gérer mon catalogue » dans le backoffice.
2. Il clique sur « Ajouter un produit depuis le référentiel ».
3. Un champ de recherche s'affiche.
4. Le marchand saisit un nom, une marque ou un code-barres (ex : « Vitalait », « Délice », « 619xxx »).
5. Le système retourne une liste de correspondances avec : nom, marque, format/volume, photo.
6. Le marchand identifie le produit souhaité dans la liste.
7. Il clique sur « Sélectionner » pour passer à l'ajout à son catalogue (voir US-014).

---

## Scénarios alternatifs

**Aucun résultat trouvé** :
- Le système affiche : « Aucun produit trouvé. Vous pouvez proposer un nouveau produit. »
- Un lien vers le formulaire de proposition de nouveau produit est affiché (US-016).

**Produit déjà dans le catalogue du marchand** :
- Le produit est marqué « Déjà dans votre catalogue ».
- Le marchand peut l'ouvrir pour modifier ses paramètres sans en créer un doublon.

---

## Règles métier

- La recherche porte sur le nom, la marque, le code-barres et la référence interne.
- La recherche est insensible à la casse et aux accents.
- Un produit du référentiel est une `ProductReference` partagée ; l'offre marchand reste propre à chaque supérette.
- Le référentiel ne contient pas les prix ni la disponibilité : ces informations appartiennent au marchand.

---

## Critères d'acceptation

- [ ] La recherche par nom retourne des résultats pertinents en moins de 1 seconde.
- [ ] La recherche par marque fonctionne (ex : « Délice » retourne tous les produits Délice).
- [ ] La recherche par code-barres retourne exactement le produit correspondant ou rien.
- [ ] Chaque résultat affiche : nom, marque, format, volume/unité et photo si disponible.
- [ ] Un produit déjà dans le catalogue du marchand est clairement identifié.
- [ ] L'absence de résultat propose le chemin vers la proposition de nouveau produit.

---

## Notes techniques

- Endpoint : `GET /api/product-references?q={query}` (recherche full-text PostgreSQL ou Elasticsearch selon choix)
- Pour le MVP : recherche `ILIKE` PostgreSQL sur `name`, `brand.name`, `barcode`.
- L'index full-text peut être ajouté post-MVP si les performances l'exigent.
- Résultats paginés : 20 par page.
