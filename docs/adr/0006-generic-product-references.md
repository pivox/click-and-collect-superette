# ADR-0006 — Produits génériques partagés : type discriminant sur `ProductReference`

Date : 2026-09-30
Statut : accepté
Issue : PRODUCT-IMAGE-001 (#581), épic #580

## Contexte

Le référentiel modélise des références industrielles exactes (marque + format
+ GTIN optionnel). Une supérette vend aussi des produits génériques — tomate,
pomme de terre, baguette, œuf, persil, olives, lentilles — qui n'ont ni
marque ni GTIN, doivent être mutualisés entre marchands (prix/disponibilité
propres via `MerchantProduct`) et porter une image générique commune.

Audit du modèle existant :

- `ProductReference.barcode` : déjà nullable — aucun GTIN imposé ✅ ;
- `ProductReference.brand` : `NOT NULL` — bloquant ❌ ;
- `ProductUnit` : couvre pièce/kg/g/l/ml/paquet ; « botte » manquait ❌ ;
- `ProductImage` (#391) : déjà rattachée à `ProductReference`, donc déjà
  mutualisée entre tous les marchands qui activent la référence ✅ ;
- `MerchantProduct` : prix, disponibilité et visibilité par marchand ✅ ;
- déduplication, gouvernance (statuts draft/pending/approved/archived),
  recherche : opèrent sur `ProductReference` ✅.

## Options

**A. Nouvelle entité `GenericProductReference`.** Dupliquerait toute la
chaîne aval : FK de `MerchantProduct`, providers catalogue public/marchand,
recherche, CRUD admin, propositions, déduplication, groupes de préchargement.
Coût massif, risque de régression sur « les recherches catalogue continuent
de fonctionner », deux pipelines à maintenir.

**B. Type discriminant sur `ProductReference`.** Champ
`kind ∈ {industrial, generic}` (défaut `industrial` → zéro régression pour
l'existant) + `brand` nullable **réservé au kind generic** par les processors
d'écriture. Tout l'aval (offre marchand, images, recherche, gouvernance,
déduplication) fonctionne sans changement structurel.

## Décision

**Option B.** Concrètement :

- `ProductReferenceKind` (`industrial` | `generic`), colonne `kind` défaut
  `industrial` ;
- `brand_id` nullable en base ; règles d'écriture admin :
  `industrial` sans marque → `422 ADMIN_PRODUCT_REFERENCE_BRAND_REQUIRED` ;
  `generic` avec marque → `422 ADMIN_PRODUCT_REFERENCE_GENERIC_BRAND_FORBIDDEN` ;
- `ProductUnit::Botte` ajouté (persil…) ; pièce/kg/g/l/paquet déjà couverts ;
- lectures : champs `brand_id`/`brand_name` deviennent nullable dans les
  sorties admin, recherche référentiel et groupes ; `kind` exposé de façon
  additive dans la sortie admin ;
- image générique commune : mécanisme #391 inchangé (une `ProductImage`
  approuvée sur la référence sert tous les marchands) — aucun faux SKU ;
- déduplication : la clé de similarité traite la marque absente comme vide
  (les génériques se dédupliquent par nom/catégorie/unité).

## Conséquences

- Les produits industriels existants ne changent pas (`kind` par défaut,
  contrat de marque intact pour eux).
- Un `generic` traverse la même gouvernance (proposition, approbation,
  archivage) que le reste du référentiel.
- Le frontend doit tolérer `brand`/`brand_name` null dans les listes du
  référentiel (le catalogue public marchand passait déjà par un accès
  null-safe).
- Rollback : la migration `down()` exige de re-brander ou supprimer les
  lignes génériques avant de restaurer `NOT NULL` (documenté dans la
  migration).
- Hors périmètre ici (issues sœurs de l'épic #580) : bibliothèque d'images
  génériques (#582), photos marchand (#583), registre de provenance (#584).
