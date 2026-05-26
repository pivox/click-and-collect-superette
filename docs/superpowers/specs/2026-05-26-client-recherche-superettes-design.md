# Design — Recherche supérettes (autocomplete dropdown)

Date : 2026-05-26  
Périmètre : frontend client — page `/stores`  
Statut : approuvé

---

## Contexte

La page `/stores` affiche une grille statique de toutes les supérettes. Le `SearchInput` existant est rendu mais ne filtre rien. Le backend expose déjà `GET /api/stores/search?query=…` (public, pas d'auth requise) qui retourne une liste de supérettes correspondantes.

L'objectif est de brancher ce champ de recherche sur un dropdown autocomplete : l'utilisateur tape, des suggestions apparaissent, un clic ouvre directement la fiche supérette.

---

## Architecture des composants

La page `/stores` reste un **Server Component** (SSR de la grille initiale). Le bloc recherche est extrait dans un **Client Component** `StoreSearchCombobox` qui gère son propre état.

```
/stores/page.tsx  (Server Component)
  └── <StoreSearchCombobox />  (Client Component)
        ├── <SearchInput />  (input contrôlé — déjà existant)
        └── dropdown flottant  (conditionnel, dans le DOM du combobox)
              └── liste de StoreSearchItem (nom + ville + badge actif)
```

### Fichiers à créer / modifier

| Fichier | Action |
|---|---|
| `apps/frontend/src/lib/services/store-search.service.ts` | Nouveau — fonction `searchStores(query)` |
| `apps/frontend/src/components/store/StoreSearchCombobox.tsx` | Nouveau — Client Component principal |
| `apps/frontend/src/app/(client)/stores/page.tsx` | Modifier — remplacer `<SearchInput>` nu par `<StoreSearchCombobox>` |

---

## Comportement

### Déclenchement

- Le dropdown s'ouvre dès que `query.trim().length >= 2`.
- Debounce **400 ms** sur la valeur du champ (géré par `useState` + `useEffect`).
- Le dropdown se ferme :
  - quand le champ est vidé (longueur < 2) ;
  - au `onBlur` avec un délai de **200 ms** (pour laisser un clic sur une suggestion s'exécuter avant la fermeture).

### États du dropdown

| État | Affichage |
|---|---|
| `loading` | Spinner léger ou skeleton (3 lignes grises) |
| `success` avec résultats | Liste de suggestions (max 8) |
| `success` sans résultats | Message « Aucune supérette trouvée pour "…" » |
| `error` | Silencieux — le dropdown reste fermé, la grille de base reste visible |

### Contenu d'une suggestion

Chaque ligne affiche :
- Icône supérette
- Nom de la supérette (en gras)
- Ville (grisée)
- Badge « Ouverte » vert si `is_active: true`

### Navigation

- Clic sur une suggestion → `router.push('/stores/{store_id}')`.
- Navigation clavier (flèches, Entrée) : hors scope MVP.

### Grille de base

La grille de toutes les supérettes reste visible sous le dropdown. Elle n'est pas affectée par la recherche. Quand le dropdown est fermé, la grille affiche l'ensemble des supérettes normalement (comportement actuel préservé).

---

## Contrat de données

### Appel API

```
GET /api/stores/search?query={query}
Authorization: non requis (PUBLIC_ACCESS)
```

### Réponse

```json
{
  "items": [
    {
      "store_id": "uuid",
      "name": "Marjé El Amel",
      "slug": "marje-el-amel",
      "city": "Tunis",
      "country": "TN",
      "is_active": true
    }
  ],
  "total": 3
}
```

### Type TypeScript

```ts
export interface StoreSearchItem {
  storeId: string;
  name: string;
  slug: string;
  city: string | null;
  country: string;
  isActive: boolean;
}
```

---

## Implémentation React Query

```ts
const { data, isLoading } = useQuery({
  queryKey: ['store-search', debouncedQuery],
  queryFn: () => searchStores(debouncedQuery),
  enabled: debouncedQuery.trim().length >= 2,
});
```

`debouncedQuery` est une valeur d'état mise à jour 400 ms après la dernière frappe.

---

## Hors scope (cette itération)

- Navigation clavier dans le dropdown (flèches, Entrée, Echap)
- Filtre par ville (`?city=…` déjà disponible en backend)
- Mise en surbrillance du terme recherché dans les suggestions
- Historique des recherches récentes
- Scan QR caméra (chantier séparé)
