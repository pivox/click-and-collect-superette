# Admin backoffice — Documentation technique

Date de livraison : 2026-05-23
PRs : #130, #131, #132
Branch : `feat/admin-marchands-superettes-audit-dashboard`

---

## Périmètre

L'espace `/admin` du frontend Next.js 14 permet aux opérateurs plateforme (`ROLE_ADMIN`) de :

- se connecter via JWT (vérification du rôle côté backend) ;
- gérer les marchands (créer, modifier, suspendre, réactiver) ;
- gérer les supérettes (créer, modifier, archiver) ;
- gérer le référentiel produits (catégories, marques, produits, propositions) ;
- consulter l'audit trail des actions admin critiques ;
- voir les KPI de la plateforme sur un dashboard.

---

## PRs livrées

### PR #130 — Auth admin + layout foundations

**Fichiers principaux :**

| Fichier | Rôle |
|---|---|
| `src/middleware.ts` | Protection `/admin/**` → redirect `/admin/login` si `admin_token` absent |
| `src/app/admin/layout.tsx` | Route group `(admin)`, montage du shell |
| `src/lib/auth/AdminAuthContext.tsx` | Login JWT + vérification `ROLE_ADMIN`, logout, restauration session localStorage |
| `src/lib/api.ts` | `apiClient` axios : injection `admin_token \|\| jwt_token`, redirect 401 contextualisé |
| `src/components/admin/AdminShell.tsx` | Sidebar 240 px + topbar + zone contenu scrollable |
| `src/components/admin/AdminSidebar.tsx` | 5 items de navigation, active state |
| `src/app/admin/login/page.tsx` | Formulaire login avec gestion d'erreur (mauvais mdp / accès refusé) |

**Décisions :**

- Le token admin est stocké à la fois dans `localStorage` (restauration après refresh) et dans un cookie httpOnly côté middleware (protection SSR).
- La vérification du rôle se fait au login : si le JWT décodé ne contient pas `ROLE_ADMIN`, une erreur "Accès réservé à l'administration" est affichée sans stocker le token.
- L'`apiClient` distingue les redirections 401 : `/admin/login` si on est sous `/admin/`, `/login` sinon.

**Tests unitaires ajoutés :** 7 (Vitest) — `decodeJwtPayload`, `adminLogin`.

---

### PR #131 — Référentiel produits

**Pages ajoutées :**

| Route | Fonctionnalité |
|---|---|
| `/admin/referentiel/categories` | CRUD catégories (nom, slug auto) |
| `/admin/referentiel/marques` | CRUD marques (nom, slug, aliases `TagInput`) |
| `/admin/referentiel/produits` | CRUD produits + filtres server-side (q, brand, category, status) + archive |
| `/admin/referentiel/propositions` | Lecture + approve inline (lier existant / créer nouveau) + reject avec raison |

**Composants partagés introduits :**

| Composant | Description |
|---|---|
| `AdminTable<T>` | Tableau avec skeleton de chargement, tri client-side, pagination, état vide + action |
| `AdminDrawer` | Slide-over latérale, titre configurable, boutons Annuler / Sauvegarder |
| `AdminConfirmDialog` | Dialog modale avec variantes `danger` / `warning`, champ optionnel `extraField` |
| `useSort<T>` | Hook de tri client-side générique sur n'importe quelle clé de `T` |

**Services ajoutés :**

- `categories.service.ts`
- `brands.service.ts`
- `product-references.service.ts`
- `proposals.service.ts`

**Tests unitaires ajoutés :** 5 (Vitest) — `useSort`.

---

### PR #132 — Marchands, supérettes, audit et dashboard

**Pages ajoutées :**

| Route | Fonctionnalité |
|---|---|
| `/admin/marchands` | Liste + create/update drawer + suspend/réactiver confirm dialog |
| `/admin/superettes` | Liste + create/update drawer + archive confirm dialog |
| `/admin/audit` | Lecture audit logs, filtre UUID admin, pagination |
| `/admin/dashboard` | 4 KPI réels : marchands total, supérettes actives, produits approuvés, propositions en attente |

**Services ajoutés :**

- `merchants.service.ts`
- `stores.service.ts`
- `audit-logs.service.ts`

**Types backend** (snake_case, alignés sur `@SerializedName` PHP) :

- `merchants.types.ts` — `is_active`, `stores_count`, `first_name`/`last_name` nullable
- `stores.types.ts` — `owner: { id, email }`, `is_active`, filtre `isActive` boolean
- `audit-logs.types.ts` — `summary: string | null`, `admin_id`, `user_agent`, `metadata`

---

## Architecture frontend admin

```
apps/frontend/src/
├── app/
│   └── admin/
│       ├── layout.tsx                  # AdminShell wrapper
│       ├── login/page.tsx
│       ├── dashboard/page.tsx
│       ├── marchands/page.tsx
│       ├── superettes/page.tsx
│       ├── audit/page.tsx
│       └── referentiel/
│           ├── categories/page.tsx
│           ├── marques/page.tsx
│           ├── produits/page.tsx
│           └── propositions/page.tsx
├── components/
│   └── admin/
│       ├── AdminShell.tsx
│       ├── AdminSidebar.tsx
│       ├── ui/
│       │   ├── AdminTable.tsx
│       │   ├── AdminDrawer.tsx
│       │   └── AdminConfirmDialog.tsx
│       ├── marchands/MerchantDrawer.tsx
│       ├── superettes/StoreDrawer.tsx
│       └── referentiel/...
├── lib/
│   ├── auth/AdminAuthContext.tsx
│   ├── api.ts
│   ├── hooks/useSort.ts
│   ├── services/admin/
│   │   ├── merchants.service.ts
│   │   ├── stores.service.ts
│   │   ├── audit-logs.service.ts
│   │   ├── categories.service.ts
│   │   ├── brands.service.ts
│   │   ├── product-references.service.ts
│   │   └── proposals.service.ts
│   └── types/admin/
│       ├── merchants.types.ts
│       ├── stores.types.ts
│       ├── audit-logs.types.ts
│       └── referentiel.types.ts
└── middleware.ts
```

---

## Contrats API consommés

### Marchands

| Méthode | Route | Payload/Filtres | Notes |
|---|---|---|---|
| GET | `/api/admin/merchants` | `page`, `limit`, `search` | Réponse : `{id, items, page, limit, total}` |
| POST | `/api/admin/merchants` | `{email, first_name, last_name, phone?, is_active?}` | `@SerializedName` → snake_case obligatoire |
| PATCH | `/api/admin/merchants/{id}` | `{first_name?, last_name?, phone?, is_active?}` | email non modifiable |
| PATCH | `/api/admin/merchants/{id}/suspend` | `{}` (body ignoré — `input: false`) | |
| PATCH | `/api/admin/merchants/{id}/activate` | `{}` (body ignoré — `input: false`) | |

### Supérettes

| Méthode | Route | Payload/Filtres | Notes |
|---|---|---|---|
| GET | `/api/admin/stores` | `page`, `limit`, `is_active` (boolean) | Seul filtre supporté côté backend |
| POST | `/api/admin/stores` | `{name, ownerId, address?, city?, phone?}` | Pas de logoUrl/coverUrl en création |
| PATCH | `/api/admin/stores/{id}` | `{name?, address?, city?, phone?, isActive?, ownerId?, logoUrl?, coverUrl?}` | `null` pour effacer logoUrl/coverUrl |
| PATCH | `/api/admin/stores/{id}/archive` | `{}` | Annule les commandes actives |

### Audit logs

| Méthode | Route | Filtres | Notes |
|---|---|---|---|
| GET | `/api/admin/audit-logs` | `page`, `limit`, `admin` (UUID), `action`, `resource_type`, `resource_id` | `admin` attend un UUID — pas un email |

### Référentiel

| Ressource | Routes | Notes |
|---|---|---|
| Catégories | GET/POST/PATCH/DELETE `/api/admin/categories` | |
| Marques | GET/POST/PATCH/DELETE `/api/admin/brands` | champ `aliases` (tableau) |
| Produits | GET/POST/PATCH `/api/admin/product-references` + PATCH `/{id}/archive` | filtres `q`, `brand`, `category`, `status` |
| Propositions | GET `/api/admin/product-proposals` + PATCH `/{id}/approve` + PATCH `/{id}/reject` | approve : lier existant ou créer nouveau |

---

## Conventions de code

### snake_case vs camelCase

Le backend utilise des annotations `@SerializedName` sur certains DTOs d'entrée, forçant des clés snake_case dans le corps des requêtes :

- `AdminCreateMerchantInput` / `AdminUpdateMerchantInput` : `first_name`, `last_name`, `is_active`
- `AdminStoreCreateInput` / `AdminStoreUpdateInput` : camelCase (`ownerId`, `isActive`, `logoUrl`)

Les réponses de l'API sont toujours en snake_case (`first_name`, `is_active`, `created_at`, etc.).

### Patterns de page

Chaque page admin suit le même squelette :

```tsx
const load = useCallback(async () => {
  setIsLoading(true);
  setError(null);
  try {
    const data = await listResource(filters);
    setItems(data.items);
    setTotal(data.total);
  } catch {
    setError('Message d\'erreur.');
  } finally {
    setIsLoading(false);
  }
}, [filters]);

useEffect(() => { void load(); }, [load]);
useEffect(() => { setPage(1); }, [filtersThatResetPage]);
```

Le debounce des champs texte est à 400 ms.

---

## Limitations connues

| Sujet | Détail |
|---|---|
| Propositions en attente (dashboard) | `listProposals` est paginé (limit 20) et ne retourne pas de total — le KPI affiche ≤ 20 |
| Filtre audit par admin | Le backend attend un UUID (`Uuid::isValid`). Le filtre email n'est pas supporté côté API. |
| Recherche marchands | La recherche `?search=` est définie côté frontend mais le backend devra l'implémenter si elle n'est pas encore active |
| Supérette — owner | `owner` peut être `null` si le marchand a été supprimé (soft delete) |
| logoUrl / coverUrl | Le backend valide `requireTld: true, protocols: ['https', 'http']` — les URL `http://localhost` seront refusées en production |

---

## Vérifications effectuées

- TypeScript : `npx tsc --noEmit` → 0 erreur
- ESLint : `npm run lint` → 0 warning
- Build : non exécuté (environnement local sans backend actif)
- Tests Vitest : 12 tests passants (7 auth + 5 useSort)
- Tests manuels : non effectués (backend non démarré localement)

---

## Prochaines étapes recommandées

1. Test d'intégration manuel avec le backend Symfony en local.
2. Implémenter la recherche marchands côté backend si `?search=` n'est pas encore active.
3. Ajouter un total au endpoint `/api/admin/product-proposals` pour avoir un KPI précis.
4. Envisager un lookup email → UUID pour le filtre admin de l'audit, ou documenter le UUID dans le profil admin.
5. Parcours client frontend (scan QR, catalogue, Kadhia, commande).
