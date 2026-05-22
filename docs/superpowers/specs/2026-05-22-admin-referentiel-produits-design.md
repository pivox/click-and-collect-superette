# Design — Admin Référentiel Produits

**Date :** 2026-05-22
**Périmètre :** Front admin — section Référentiel produits (4 sous-pages)
**Branche cible :** feat/admin-backoffice-auth-layout (ou branche dédiée depuis main)

---

## Contexte

Le backoffice admin dispose déjà du shell (layout, sidebar, auth, middleware). La sidebar liste "Référentiel produits" comme item de navigation, mais aucune page n'existe encore. Le backend Sprint 5 expose tous les endpoints nécessaires.

---

## Décisions de design

| Décision | Choix |
|----------|-------|
| Navigation sous-sections | Sidebar expandable (sous-items visibles quand pathname commence par `/admin/referentiel`) |
| Formulaire create/edit | Drawer slide-over depuis la droite |
| Approve proposition | Expansion inline dans la ligne du tableau |
| Tri | Client-side (trie la page courante) |
| Filtres Produits | Server-side : `q`, `brand`, `category`, `status` |
| Filtres Catégories/Marques/Propositions | Client-side search + pills statut (Propositions) |

---

## Architecture

### Routes Next.js

```
app/admin/referentiel/
  layout.tsx                          — wrapper vide, children uniquement
  categories/page.tsx
  marques/page.tsx
  produits/page.tsx
  propositions/page.tsx
```

### Composants partagés admin (nouveaux)

```
components/admin/ui/
  AdminTable.tsx          — table générique : colonnes cliquables (sort), skeleton 5 lignes, empty state, pagination prev/next
  AdminDrawer.tsx         — panneau slide-over : backdrop, header titre, body scrollable, footer sticky Save/Cancel
  AdminConfirmDialog.tsx  — modale de confirmation : message + boutons Confirmer/Annuler
```

### Composants par entité (nouveaux)

```
components/admin/referentiel/
  categories/
    CategoryDrawer.tsx      — form create/edit catégorie
  marques/
    BrandDrawer.tsx         — form create/edit marque
  produits/
    ProductReferenceDrawer.tsx  — form create/edit produit (plus grand)
  propositions/
    ProposalRow.tsx         — ligne avec expansion inline approve/reject
```

### Hook partagé

```
lib/hooks/useSort.ts
  useSort<T>(items: T[], key: keyof T, dir: 'asc' | 'desc')
  → { sorted: T[], sortKey, sortDir, toggleSort(key) }
```

### Services admin (nouveaux)

```
lib/services/admin/
  categories.service.ts         — listCategories(page, limit), createCategory, updateCategory, deleteCategory
  brands.service.ts             — listBrands(page, limit), createBrand, updateBrand, deleteBrand
  product-references.service.ts — listProductReferences({q, brand, category, status, page, limit}), create, update, archive
  proposals.service.ts          — listProposals({status, page}), approveProposal(id, data), rejectProposal(id, reason)
```

### Mise à jour AdminSidebar

`Référentiel produits` devient un item expandable. Quand `pathname.startsWith('/admin/referentiel')`, afficher 4 sous-liens indentés :

```
▾ Référentiel produits   ← item parent, lien vers /admin/referentiel/produits
    Catégories
    Marques
    Produits
    Propositions  [badge count si pending > 0]
```

---

## Pages

### Catégories — `/admin/referentiel/categories`

**Endpoints :** `GET /api/admin/categories?page&limit`, `POST`, `PATCH /{id}`, `DELETE /{id}`

**Tableau :**

| Colonne | Triable | Notes |
|---------|---------|-------|
| Nom FR | ✓ | |
| Nom AR | — | nullable |
| Slug | — | |
| Ordre | ✓ | sort_order |
| Actif | ✓ | badge vert/gris |
| Actions | — | Edit, Delete |

**Filtres :** Barre de recherche texte → filtre client-side sur `name_fr` + `name_ar` de la page courante.

**Formulaire création (Drawer) :**
- `name_fr` — texte, obligatoire, max 160
- `name_ar` — texte, optionnel, max 160
- `slug` — texte, optionnel (backend auto-génère si absent), max 180

**Formulaire édition (Drawer) :**
- `name_fr` — texte, obligatoire, max 160
- `name_ar` — texte, optionnel, max 160
- `is_active` — toggle, défaut true

Note : `parent_id` est affiché en lecture seule dans le tableau mais n'est pas modifiable via ce formulaire (non exposé par l'API admin).

**Delete :** AdminConfirmDialog → `DELETE /api/admin/categories/{id}`

---

### Marques — `/admin/referentiel/marques`

**Endpoints :** `GET /api/admin/brands?page&limit`, `POST`, `PATCH /{id}`, `DELETE /{id}`

**Tableau :**

| Colonne | Triable | Notes |
|---------|---------|-------|
| Nom canonique | ✓ | |
| Aliases | — | count badges |
| Pays | ✓ | code ISO 2 lettres |
| Actif | ✓ | badge |
| Actions | — | Edit, Delete |

**Filtres :** Barre de recherche client-side sur `canonical_name`.

**Formulaire (Drawer) :**
- `canonical_name` — texte, obligatoire, max 160
- `slug` — texte, optionnel, max 180
- `aliases` — tag input (entrée + Enter pour ajouter, ✕ pour retirer)
- `country` — texte 2 chars (ex. TN, FR)

**Delete :** AdminConfirmDialog → `DELETE /api/admin/brands/{id}`

---

### Produits — `/admin/referentiel/produits`

**Endpoints :** `GET /api/admin/product-references?q&brand&category&status&page&limit`, `POST`, `PATCH /{id}`, `PATCH /{id}/archive`

**Tableau :**

| Colonne | Triable | Notes |
|---------|---------|-------|
| Produit | ✓ | name_fr + variant_fr en sous-titre gris |
| Marque | ✓ | brand_name |
| Catégorie | ✓ | category_name_fr |
| Unité | — | |
| Statut | ✓ | chip coloré (voir ci-dessous) |
| Actions | — | Edit, Archive |

**Couleurs statut :**

| Statut | Couleur |
|--------|---------|
| approved | vert |
| pending_review | jaune |
| draft | gris |
| rejected | rouge |
| archived | gris clair, ligne grisée |

**Filtres server-side :**
- `q` — barre de recherche texte (nameFr, nameAr, barcode)
- `brand` — select avec options chargées depuis `GET /api/admin/brands`
- `category` — select avec options chargées depuis `GET /api/admin/categories`
- `status` — select : Tous / draft / pending_review / approved / rejected / archived

**Archive :** AdminConfirmDialog → `PATCH /api/admin/product-references/{id}/archive`. Pas de suppression.

**Formulaire (Drawer large) :**
- `name_fr` — texte, obligatoire, max 255
- `name_ar` — texte, optionnel
- `variant_fr` / `variant_ar` — texte, optionnel, max 160
- `brand_id` — select (options depuis brands), obligatoire
- `category_id` — select (options depuis categories), obligatoire
- `unit` — select : litre / millilitre / kilogramme / gramme / piece / paquet, obligatoire
- `volume` — texte, optionnel
- `barcode` — texte, optionnel, max 64
- `country` — texte 2 chars, optionnel (défaut TN suggéré)
- `status` — select : draft / pending_review / approved / rejected (archived exclu — action dédiée)
- `aliases` — tag input, optionnel

---

### Propositions — `/admin/referentiel/propositions`

**Endpoints :** `GET /api/admin/product-proposals?status&page`, `PATCH /{id}/approve`, `PATCH /{id}/reject`

**Filtres statut :** Pills cliquables en haut : En attente (défaut) / Approuvé / Rejeté. Statut envoyé en query param server-side.

**Barre de recherche :** client-side, filtre `name_fr` de la page courante.

**Tableau :**

| Colonne | Triable | Notes |
|---------|---------|-------|
| Nom proposé | ✓ | name_fr |
| Marque | — | brand_name |
| Catégorie | — | category |
| Proposé par | — | proposed_by |
| Date | ✓ | created_at, défaut DESC |
| Actions | — | ✓ Approuver, ✗ Rejeter |

**Expansion inline — Approuver :**
Clic sur ✓ déplie un panneau sous la ligne (fond vert clair, bordure gauche verte).

```
↳ Approuver — lier à un produit existant ou créer nouveau

[ Rechercher produit canonique… ]  ou  [ Saisir nom canonique FR ]   [Confirmer]
```

- Champ "Rechercher" : autocomplete debounce 300ms → `GET /api/admin/product-references?q=...` → sélection envoie `{ productReferenceId: uuid }`
- Champ "Saisir nom" : 3 champs requis (brandId et categoryId sont `NotBlank` dans `AdminApproveCanonicalData`) :
  - `nameFr` — texte obligatoire
  - `brandId` — select parmi les marques existantes, obligatoire
  - `categoryId` — select parmi les catégories existantes, obligatoire
  - Envoie `{ canonicalData: { nameFr, brandId, categoryId } }`
- Exactement un seul panneau ouvert à la fois (ouvrir un autre ferme le précédent)

**Expansion inline — Rejeter :**
Clic sur ✗ déplie un panneau sous la ligne (fond rouge clair, bordure gauche rouge).

```
↳ Raison du rejet *

[ Textarea obligatoire ]   [Confirmer le rejet]
```

Envoie `{ reason: string }` → `PATCH /api/admin/product-proposals/{id}/reject`.

---

## Composants partagés — Spécifications

### AdminTable

Props :
```ts
columns: Array<{ key: string; label: string; sortable?: boolean; render?: (row) => ReactNode }>
data: T[]
isLoading: boolean
emptyMessage?: string
emptyAction?: { label: string; onClick: () => void }
pagination: { page: number; total: number; limit: number; onPageChange: (p: number) => void }
sortKey?: string
sortDir?: 'asc' | 'desc'
onSort?: (key: string) => void
```

États :
- **Loading** : 5 lignes skeleton (barres grises animées)
- **Vide** : message centré + bouton CTA optionnel
- **Données** : lignes normales

### AdminDrawer

Props :
```ts
open: boolean
onClose: () => void
title: string
onSubmit: () => void
isSubmitting: boolean
children: ReactNode
size?: 'md' | 'lg'   // md = 400px, lg = 560px (ProductReference)
```

Comportement :
- Backdrop semi-transparent, clic ferme
- Escape key ferme
- Footer sticky : bouton "Enregistrer" (primary, spinner si isSubmitting) + "Annuler" (ghost)

### AdminConfirmDialog

Props :
```ts
open: boolean
onClose: () => void
onConfirm: () => void
title: string
message: string
confirmLabel?: string   // défaut "Confirmer"
variant?: 'danger' | 'warning'
```

---

## Gestion des erreurs

- **Erreur API (4xx/5xx)** : toast inline affiché sous la barre de filtres, auto-dismiss 5s
- **Validation form** : erreurs inline sous chaque champ (state local, avant envoi)
- **409 Conflict (proposition déjà traitée)** : message spécifique "Cette proposition a déjà été traitée."
- **Optimistic update** : non — attendre la réponse API avant de mettre à jour la liste

---

## Hors scope

- Tri server-side
- Opérations bulk (approve/reject multiple propositions)
- Import/export CSV du référentiel
- Drag-and-drop reorder des catégories
- Pagination des selects brand/category dans le formulaire produit (chargement complet, suffisant pour le MVP)
