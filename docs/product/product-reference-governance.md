# Gouvernance du référentiel produit

**Statut** : Décision Sprint 13 / #373  
**Date** : 2026-06-09  
**Objectif** : Formaliser les rôles, états, transitions et droits autour du référentiel produit pour soutenir le lancement officiel.

---

## 1. Vision

Le référentiel produit est un **actif stratégique partagé** par tous les marchands. Il doit rester :

- **Fiable** — chaque référence est validée par un admin avant d'entrer en `approved`
- **Contrôlable** — traçable à chaque état ; aucune modification silencieuse
- **Évolutif** — accepte les propositions (marchands, IA) sans bloquer les opérations

---

## 2. Rôles

### 2.1 Admin plateforme

- **Autorité unique** de validation, d'archivage et de fusion.
- Accès complet : lister, filtrer, détailer, modifier tous les états des références.
- Peut créer une référence directement en `draft` ou `approved`.

### 2.2 Marchand

- **Peut proposer** un nouveau produit via `ProductReferenceProposal`.
- **Ne peut pas** valider, refuser, fusionner ou archiver les références globales.
- Peut utiliser les références en `approved` dans son catalogue via `MerchantLocalProduct`.

### 2.3 Système IA

- **Assiste** la création et la déduplication sans décider seul.
- Génère un `score_quality` (0–100) et un `quality_level` (low/medium/good) pour chaque référence.
- Peut **suggérer** des fusions mais ne peut pas les exécuter.
- Propose des corrections (variante, format, volume) sans les appliquer.

---

## 3. Statuts de la ProductReference

### Diagramme d'état

```
draft
├─→ pending_review (admin relance)
├─→ approved (admin valide)
├─→ rejected (admin refuse)
└─→ archived (admin archibe, final)

pending_review
├─→ approved (admin valide)
├─→ rejected (admin refuse)
├─→ archived (admin archibe)
└─→ draft (admin revient en brouillon pour corrections)

approved
├─→ pending_review (admin demande révision)
├─→ archived (admin archibe)
└─→ draft (admin revient en brouillon pour corrections)

rejected
└─→ draft (admin réouvre pour correction)

archived
└─ (terminal)
```

### Signification de chaque état

| État | Sens | Visibilité | Utilisable |
|---|---|---|---|
| **draft** | Brouillon, pas encore validé | Admin seulement | Non (admin peut l'utiliser) |
| **pending_review** | En attente de validation admin | Admin seulement | Non (en review) |
| **approved** | Valide, prêt pour le catalogue marchand | Admin + marchands (readonly) | **OUI** — seul état utilisable par les marchands |
| **rejected** | Refusé, raison documentée | Admin seulement | Non (bloqué) |
| **archived** | Supprimé logiquement de la circulation | Admin seulement (historique) | Non (interdit) |

### Transition par rôle

#### Admin

- `draft` → `pending_review` — relance de review
- `draft` → `approved` — validation directe (référence simple)
- `draft` → `rejected` — refus avec raison
- `pending_review` → `approved` — validation après review
- `pending_review` → `rejected` — refus après review
- `pending_review` → `draft` — retour en brouillon pour corrections
- `approved` → `pending_review` — demande de révision (ex. suite à feedback)
- `approved` → `draft` — retour en brouillon pour correction
- `approved` → `archived` — retrait de la circulation
- `rejected` → `draft` — réouverture pour correction
- Tout état → `archived` — archivage final (remplacement, doublon détecté, produit obsolète)

#### Marchand

- **Aucune transition directe** — seul lecteur de références en `approved`
- Peut proposer un nouveau produit → crée une `ProductReferenceProposal`

#### IA

- Émet un score et une suggestion de correction (via champ metadata)
- **Ne peut pas** changer l'état ni forcer une fusion

---

## 4. Workflow de création

### 4.1 Création directe par admin

```
Admin crée dans l'UI admin → POST /api/admin/product-references
├─ Données complètes (nom FR/AR, marque, catégorie, code-barres, volume, unité)
├─ Statut initial : draft
└─ Admin peut passer directement en approved si complet
```

### 4.2 Création via proposition de marchand

```
Marchand propose → ProductReferenceProposal en pending
     ↓ 
Admin reçoit notification
     ↓
Admin valide ou refuse la proposition
├─ Approuve : crée ProductReference en approved, lie via createdProductReference
└─ Refuse : stocke rejectionReason, ProductReferenceProposal reste en rejected
```

### 4.3 Suggestion IA

```
IA analyse import massif → génère N ProductReferenceProposal suggérées
     ↓
Admin filtre par score_quality et sélectionne celles à traiter
     ↓
Admin approuve ou rejette
```

---

## 5. Workflow de fusion (merge)

**Responsable** : Admin seul  
**Trigger** : Doublon détecté, code-barres identique, ou décision admin de consolider

### Étapes

1. **Admin identifie les doublons**
   - Via la UI admin : 2 références visiblement identiques
   - Via IA : score qualité bas + suggestion de fusion
   - Via manual merge endpoint : `PATCH /api/admin/product-references/{id}/merge`

2. **Admin sélectionne la référence cible** (celle qui reste)
   - Critère de priorité : code-barres non-null > nom exact > score qualité plus élevé

3. **Admin fusionne**
   - Statut des sources → `archived` (marqué like duplicates)
   - Statut de la cible → reste son état courant (généralement `approved`)
   - Enregistrement : `ProductReferenceMergeHistory` (qui + quand + source + target)

4. **Conséquences**
   - Les `MerchantLocalProduct` liées aux sources restent valides (foreign keys not deleted)
   - Les propositions en pending des sources deviennent orphelines (cascade `SET NULL`)
   - Les commandes historiques restent intactes (frozen prices)

---

## 6. Workflow d'archivage

**Responsable** : Admin seul  
**Raisons** : Produit hors catalogue, obsolète, erreur, remplacé par une fusion

### Étapes

1. **Admin sélectionne une référence en approved, pending_review ou draft**
2. **Admin clique "Archiver"** → `PATCH /api/admin/product-references/{id}/archive`
3. **Changement d'état** : current status → `archived`
4. **Conséquences**
   - La référence **disparaît du filtre marchand** (readonly visible seulement)
   - Les `MerchantLocalProduct` liées deviennent **invisibles au client** (catalog filtre sur `status=approved`)
   - Les commandes existantes restent valides (prix figé)
   - Aucune nouvelle `MerchantLocalProduct` ne peut pointer sur une référence archivée

---

## 7. Score de qualité et éligibilité

**Calculé** : `ProductReferenceQualityScorer` (Sprint 13 #372)  
**Échelle** : 0–100 (déterministe, réproductible)  
**Niveaux** : low (0–40), medium (41–70), good (71–100)

### Critères de scoring

Champs complétés avec poids :
- `nameFr` + `nameAr` — 20 points
- `brand` (existant) — 15 points
- `category` (existant) — 15 points
- `barcode` — 25 points
- `volume` + `unit` — 15 points
- `productImages` (count ≥ 1) — 10 points

**Utilisation** :
- **Admin** : Filtre par qualité (min/max) pour prioriser le review
- **IA** : Repère les références incomplètes et suggère des corrections
- **Recherche** : Tri optionnel `sort=quality_score` pour affiner les résultats

---

## 8. Droits par endpoint

### Admin endpoints

| Opération | Endpoint | Rôle | Statuts accessibles |
|---|---|---|---|
| Lister | `GET /api/admin/product-references` | Admin | Tous (draft, approved, rejected, archived) |
| Détailer | `GET /api/admin/product-references/{id}` | Admin | Tous |
| Créer | `POST /api/admin/product-references` | Admin | Initial = `draft` ou `approved` |
| Modifier | `PATCH /api/admin/product-references/{id}` | Admin | draft, pending_review, approved (non-archived) |
| Archiver | `PATCH /api/admin/product-references/{id}/archive` | Admin | draft, pending_review, approved → archived |
| Valider proposition | `PATCH /api/admin/product-proposals/{id}/approve` | Admin | ProductReferenceProposal.status=pending |
| Refuser proposition | `PATCH /api/admin/product-proposals/{id}/reject` | Admin | ProductReferenceProposal.status=pending |
| Fusionner | `PATCH /api/admin/product-references/{id}/merge` | Admin | Tous (sources + target) |
| Comparer avant merge | `GET /api/admin/product-references/compare` | Admin | Tous |
| Lister doublons | `GET /api/admin/product-references/duplicates` | Admin | approved + draft |

### Marchand endpoints

| Opération | Endpoint | Rôle | Voir |
|---|---|---|---|
| Rechercher | `GET /api/merchant/product-references` | Marchand | Seules references en `approved` |
| Détailer | `GET /api/merchant/product-references/{id}` | Marchand | Seules references en `approved` |
| Proposer | `POST /api/merchant/product-proposals` | Marchand | Crée une ProductReferenceProposal |

### Client endpoints

| Opération | Endpoint | Rôle | Voir |
|---|---|---|---|
| Catalogue public | `GET /api/stores/{storeId}/products` | Public | MerchantLocalProduct pointant sur approved references |

---

## 9. Audit et traçabilité

Chaque transition d'état doit être enregistrée :

- **Champ `updatedAt`** sur ProductReference
- **Log admin** (si `AdminAuditLog` existe) : `action=ARCHIVE_PRODUCT_REFERENCE`, metadata with reason
- **ProductReferenceMergeHistory** : qui a fusionné quoi et quand
- **ProductReferenceProposal.status** : historique des décisions (pending → approved/rejected → created/archived)

---

## 10. Règles d'archivage et cascades

### Ce qui se passe quand une référence est archivée

1. **Références elles-mêmes** → `archived` (changement d'état)
2. **MerchantLocalProduct liées**
   - Statut ne change pas (`is_active` reste inchangé)
   - Deviennent **invisibles au client** (filtre catalog sur `reference.status=approved`)
   - Marchand peut les réactiver en les reliant à une nouvelle référence approuvée
3. **Commandes existantes** → intactes (prix figé, référence toujours queryable)
4. **Propositions en pending** → gardent leur statut (orphelines, pas de cascade delete)

### Protections

- Une référence archivée **ne peut pas être de-archivée** directement
  - Pour la réutiliser : créer une nouvelle référence et migrer les liens
- Une référence archivée **bloque la création de nouvelles MerchantLocalProduct** pointant sur elle
  - Validation lors de la création : `if (reference.status === archived) throw 422`

---

## 11. Considérations pour l'IA et l'automatisation

### Ce que l'IA peut faire

- Calculer et mettre à jour `score_quality` + `quality_level`
- Suggérer des doublons (via une table ou un champ metadata `suggested_merge_with`)
- Proposer des corrections de champs (variante, volume, unité)
- Générer des propositions en masse lors d'imports (bulk proposals)

### Ce que l'IA ne peut pas faire

- Changer d'état directement
- Exécuter une fusion sans approbation admin
- Archiver une référence
- Accéder à un endpoint de modification

### Champs metadata pour l'IA

Ajouter optionnellement sur ProductReference :

```php
#[ORM\Column(type: 'json', nullable: true)]
private ?array $aiMetadata = null; // suggestions, detected_duplicates, corrections
```

---

## 12. Transition vers les actions rapides et en masse (S13-006 & S13-008)

La gouvernance formalise les **fondations** pour :

- **S13-006 (Traitement rapide)** — L'admin peut corriger et valider plus vite car les rôles et états sont clairs
- **S13-008 (Actions en masse)** — Les actions en masse (archiver N, approuver N, fusionner N) doivent respecter ce workflow

---

## 13. Critères de conformité

Une implémentation est **conforme** à cette gouvernance si :

- [ ] Seul un admin peut approuver, refuser, fusionner ou archiver
- [ ] Les statuts draft/pending_review/approved/rejected/archived sont respectés
- [ ] Une référence en `approved` est seule accessible aux marchands et clients
- [ ] Une référence archivée disparaît des listes métier (mais reste en historique admin)
- [ ] Chaque fusion génère un enregistrement tracé
- [ ] Le score qualité est affiché et utilisable pour le filtrage
- [ ] Les propositions marchands sont isolées jusqu'à approbation
- [ ] Aucune transition non documentée ici n'existe

---

## 14. Évolutions futures

Hors MVP — à envisager une fois la gouvernance stabilisée :

- **Workflow multi-étape** : draft → suggested_by_ai → pending_review → approved (si volume trop élevé)
- **Suppressions logiques partagées** : différencier `archived` (utilisateur retire) et `merged` (IA fusionne)
- **Historique complet des modifications** : event sourcing sur ProductReference
- **Approbation en chaîne** : review par un lead product avant approbation admin

---
