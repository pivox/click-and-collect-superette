# Store Context Persistant — Design Spec

**Issue :** #306  
**Date :** 2026-06-02  
**Statut :** Approuvé

---

## Problème

La home affiche une "Supérette en vedette" arbitraire (`shops[0]`). Il n'existe pas de notion de supérette sélectionnée par l'utilisateur. Le contexte "dans quelle supérette je suis" est absent de toute la navigation, ce qui rend le parcours implicite.

---

## Objectif

Permettre à l'utilisateur de sélectionner explicitement une supérette et de voir cette sélection affichée en permanence dans l'interface (mobile + desktop), avec la possibilité de switcher à tout moment.

---

## Décisions de design

| Question | Choix |
|---|---|
| Où afficher le contexte | Pill sticky en haut de `<main>` (mobile + desktop) + bloc dans la sidebar desktop |
| État vide | Pill ambre "Choisir une supérette" toujours visible |
| Interaction switch | Redirection vers `/stores`, clic sur une carte → sélectionne et navigue vers la fiche |
| Kadhia active + changement store | Dialog d'avertissement avant confirmation |

---

## Architecture

### Persistence

Clé `localStorage` : `"selected_store"`  
Format : `{ id: string, name: string, logoLetter?: string | null }`

Coexiste avec les clés existantes `kadhia:active:*` et `kadhia:context` sans les modifier.

### `SelectedStoreContext`

**Fichier :** `apps/frontend/src/lib/store/SelectedStoreContext.tsx`

Context React côté client (`"use client"`). Expose :

```ts
interface SelectedStoreContextValue {
  selectedStore: { id: string; name: string; logoLetter?: string | null } | null;
  selectStore: (shop: { id: string; name: string; logoLetter?: string | null }) => void;
  clearStore: () => void;
}
```

- Lecture initiale au mount via `useHydrated` (SSR-safe, pas de mismatch hydration).
- `selectStore()` écrit en `localStorage` + met à jour le state.
- `clearStore()` supprime la clé et remet `null`.

### Hook `useSelectedStore()`

**Fichier :** `apps/frontend/src/lib/store/SelectedStoreContext.tsx` (export nommé)

Raccourci : `const { selectedStore, selectStore } = useSelectedStore()`.  
Lance si utilisé hors du provider.

---

## Composants

### `StoreContextPill`

**Fichier :** `apps/frontend/src/components/store/StoreContextPill.tsx`

Pill sticky placée en haut de `<main>` dans le layout client, au-dessus de `{children}`.

**États :**

| État | Apparence | Action au clic |
|---|---|---|
| Store sélectionné | Pill violette `📍 [nom] ↕` | Lien vers `/stores` |
| Aucun store | Pill ambre `🏪 Choisir une supérette →` | Lien vers `/stores` |

Composant client (`"use client"`), lit `useSelectedStore()`.  
Ne rend rien côté serveur (SSR skip via `useHydrated`).

### `StoreSwitchWarning`

**Fichier :** `apps/frontend/src/components/store/StoreSwitchWarning.tsx`

Dialog de confirmation affiché quand l'utilisateur tente de sélectionner une supérette différente alors qu'il a une Kadhia active dans la supérette courante.

```
"Tu as une Kadhia en cours chez [store actuel].
Changer de supérette ne la supprime pas, mais elle sera mise en pause
jusqu'à ton retour. Continuer ?"

[Annuler]  [Changer quand même]
```

Détection Kadhia active : lire `localStorage.getItem("kadhia:active:" + selectedStore.id)`.  
Si la clé existe et est non-vide → afficher le dialog.

---

## Modifications de fichiers existants

### `apps/frontend/src/app/(client)/layout.tsx`

1. Enrober le contenu dans `<SelectedStoreProvider>`.
2. Dans `<main>`, ajouter `<StoreContextPill />` avant `{children}`.

```tsx
<main className="relative min-w-0 px-4 pt-4 pb-40 md:p-7">
  <StoreContextPill />
  {children}
</main>
```

### `apps/frontend/src/components/layout/DesktopNav.tsx`

Ajouter un bloc "Supérette active" entre la liste de navigation et le bloc user (en bas de sidebar).

**Store sélectionné :**
```
┌─────────────────────────────┐
│  [A]  Aziza Montplaisir     │
│       Changer →             │
└─────────────────────────────┘
```

**Aucun store :**
```
┌─────────────────────────────┐
│  Aucune supérette           │
│  Choisir →                  │
└─────────────────────────────┘
```

Les deux sont des liens vers `/stores`.

### `apps/frontend/src/app/(client)/stores/page.tsx`

La page reste un Server Component (fetch des shops). Les cartes sont extraites dans un composant client `StoreSelectList` qui reçoit `shops: Shop[]` en prop.

`StoreSelectList` gère :
- Un `onClick` par carte qui : vérifie Kadhia active → affiche `StoreSwitchWarning` si besoin → appelle `selectStore(shop)` → navigue vers `/stores/${shop.id}`.
- Un badge "active" sur la carte du store actuellement sélectionné (lu via `useSelectedStore()`).

### `apps/frontend/src/app/(client)/stores/[shopId]/page.tsx`

Le bouton "Commencer ma Kadhia" extrait dans un composant client `StartKadhiaCta` qui :
1. `useEffect` au mount : si aucun store sélectionné → `selectStore(shop)` silencieusement (sans dialog).
2. Au clic : si un store différent est déjà sélectionné et Kadhia active → affiche `StoreSwitchWarning`.
3. Sinon au clic : `selectStore(shop)` + navigue vers `/stores/${shopId}/catalog`.

### `apps/frontend/src/app/(client)/stores/by-qr/[qrToken]/page.tsx`

Après résolution du token → auto-sélection silencieuse du store via un composant client `AutoSelectStore` monté sur la page. Pas de dialog (le QR scan est une intention explicite).

### `apps/frontend/src/app/(client)/page.tsx`

- Supprimer le bloc "Supérette en vedette" (desktop : colonne droite du hero, `featuredShop`).
- Supprimer la variable `featuredShop`.
- Conserver la section "Supérettes récentes" (les 3 premières cartes).
- Passer la home en layout 1 colonne pleine largeur sur desktop (hero seul).
- La pill du layout prend en charge l'affichage du contexte store.

---

## Flux utilisateur complet

```
[Accueil — pill ambre "Choisir"]
        ↓ clic pill
[/stores — liste des supérettes]
        ↓ clic sur "Aziza Montplaisir"
[dialog warning si Kadhia active ailleurs]
        ↓ confirmation (ou pas de Kadhia)
[selectStore("aziza-id")] → localStorage updated
[/stores/aziza-id — fiche supérette]
        ↓ "Commencer ma Kadhia"
[/stores/aziza-id/catalog]

Pendant tout le parcours suivant :
  pill violette "📍 Aziza Montplaisir ↕" visible en haut de chaque page
  sidebar desktop : bloc "Supérette active — Aziza Montplaisir | Changer →"
```

---

## Comportements QR code

| Scénario | Comportement |
|---|---|
| Scan QR → store sans Kadhia active | Auto-select silencieux |
| Scan QR → store différent + Kadhia active | Auto-select silencieux (QR = intention explicite) |
| Store déjà sélectionné, même store | Rien (idempotent) |

---

## Tests à écrire

- `StoreContextPill` : rendu avec store / sans store / SSR (pas de rendu avant hydratation)
- `useSelectedStore` : lecture/écriture localStorage, clearStore
- `StoreSwitchWarning` : affichage conditionnel selon Kadhia active
- `stores/page` : badge "active" sur la bonne carte
- `stores/[shopId]/page` : auto-select au chargement si pas de store

---

## Ce qui n'est PAS dans ce scope

- Supérettes "favorites" ou épinglées (post-MVP)
- Historique des supérettes visitées (géré par `/stores` existant)
- Persistance côté serveur de la sélection (inutile, localStorage suffit)
- Changement de l'URL en fonction du store sélectionné
