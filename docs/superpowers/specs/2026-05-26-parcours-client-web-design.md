# Parcours client web — desktop & mobile

**Date :** 2026-05-26
**Auteur :** Haythem MABROUK
**Branche cible :** à créer depuis `main`

---

## Contexte

Le frontend (Next.js 14, racine du monorepo) possède un parcours client mobile
esquissé dans `src/app/(client)/` et une page prototype desktop statique dans
`src/app/desktop/page.tsx`. Ce sprint initialise le parcours client complet :
layout responsive partagé, pages desktop adaptées, auth client JWT, connexion à
l'API réelle.

---

## Décisions d'architecture

### 1. Routes partagées — un seul arbre `/(client)/`

Pas de route group séparé pour le desktop. Toutes les routes client (`/`, `/stores`,
`/kadhia`, `/orders`, etc.) vivent dans `src/app/(client)/`. Le layout détecte le
form factor via CSS Tailwind uniquement.

### 2. Toggle CSS double shell

`src/app/(client)/layout.tsx` rend les deux shells empilés :

```tsx
<>
  <div className="md:hidden">
    <MobileShell>{children}<BottomNav /></MobileShell>
  </div>
  <div className="hidden md:block">
    <DesktopShell>{children}</DesktopShell>
  </div>
</>
```

Les children sont rendus deux fois dans le DOM. Acceptable car les pages sont
légères côté SSR. Fallback `useMediaQuery` disponible si une page client lourde
pose problème.

### 3. `DesktopShell` — nav mise à jour

Les hrefs passent de `/desktop/...` aux vraies routes `/(client)/` :
`/`, `/stores`, `/kadhia`, `/orders`.

### 4. `/desktop/page.tsx` supprimée

La route prototype devient obsolète et est retirée.

### 5. Auth progressive

Le catalogue et la navigation sont publics (pas d'auth requise). L'auth est
déclenchée uniquement au moment de la soumission de commande.

```
Catalogue → localStorage Kadhia (sans auth)
           ↓
         Slot page → si non authentifié :
                       → /login?redirect=/kadhia/slot
                       → retour auto après login
           ↓
         Submit → sync localStorage → backend
                  POST /api/me/stores/{storeId}/kadhias + lignes + soumission
                        ↓
                  Redirect /orders/{orderId}
```

### 6. Kadhia en localStorage jusqu'à la soumission

Le draft Kadhia reste côté client (localStorage) pendant toute la phase de
composition. `submitKadhia()` crée la Kadhia backend, synchronise les lignes,
puis soumet. Si la création backend échoue, la localStorage reste intacte et
l'utilisateur reste sur la page slot.

---

## Layouts par page

### Home `/`

| Breakpoint | Layout |
|---|---|
| Mobile | Hero pleine largeur + liste StoreCard verticale |
| Desktop `md+` | Hero 2 colonnes (`1.3fr / 0.7fr`) — pitch gauche, StoreCard featured droite. Stores récents en grille 3 cols. |

### Stores list `/stores`

| Breakpoint | Layout |
|---|---|
| Mobile | SearchInput + StoreCard verticale |
| Desktop | SearchInput large + grille 3 cols |

### Store detail `/stores/[shopId]`

| Breakpoint | Layout |
|---|---|
| Mobile | Hero + stats 2 cols + CTA sticky bottom |
| Desktop | Hero 2 colonnes — infos gauche, KPI + CTA droite |

### Catalog `/stores/[shopId]/catalog` ⭐ principal changement

| Breakpoint | Layout |
|---|---|
| Mobile | Grille produits 2 cols, icône panier en haut à droite |
| Desktop | `md:grid-cols-[1fr_360px]` — catalogue gauche (grille 3-4 cols) + `<KadhiaPanel>` sticky droite |

`<KadhiaPanel>` (nouveau composant `hidden md:block`) :
- Lignes de la Kadhia courante (lecture localStorage)
- Sous-total + total estimé
- CTA "Choisir un créneau" → `/kadhia/slot`

### Kadhia `/kadhia` + Slot `/kadhia/slot`

Desktop : contenu centré `max-w-2xl mx-auto`. Pas de layout spécifique.

### Order tracking `/orders/[orderId]`

| Breakpoint | Layout |
|---|---|
| Mobile | Vertical : statut + timeline + CTA QR |
| Desktop | `md:grid-cols-2` — timeline gauche, résumé + CTA droite |

### Pickup QR `/orders/[orderId]/pickup`

Desktop : carte centrée `max-w-md mx-auto`.

---

## Auth client

### Nouveaux fichiers

| Fichier | Rôle |
|---|---|
| `src/lib/auth/ClientAuthContext.tsx` | Context JWT client (pattern identique à `AdminAuthContext`). Token dans `localStorage` clé `client_token`. Rôle vérifié : `ROLE_CUSTOMER`. |
| `src/app/(client)/login/page.tsx` | Formulaire email / password. Redirige vers `?redirect` ou `/` après succès. Lien vers `/register`. |
| `src/app/(client)/register/page.tsx` | Formulaire inscription. Redirige vers `/login` après succès. |

### Ajouts dans `auth.service.ts`

```ts
clientLogin(email, password)   // POST /api/auth/login + vérif ROLE_CUSTOMER
clientRegister(email, password, name) // POST /api/auth/register
```

---

## API wiring

| Service | Endpoint réel | Notes |
|---|---|---|
| `stores.service.ts` | `GET /api/stores` | Toggle `USE_MOCKS` déjà en place. Adapter types `Shop` ↔ réponse API. |
| `stores.service.ts` | `GET /api/stores/{id}` | Idem |
| `stores.service.ts` | `GET /api/stores/by-qr/{token}` | QR scan |
| `catalog.service.ts` | `GET /api/shops/{id}/products` | ⚠ Vérifier chemin exact vs `/api/stores/{id}/products` sur OpenAPI avant premier appel |
| `slots.service.ts` | `GET /api/stores/{id}/pickup-slots` | |
| `kadhia.service.ts` | localStorage → `submitKadhia()` | Crée Kadhia backend + lignes + soumission. Auth requise. |
| `orders.service.ts` | `GET /api/me/orders` | Auth client requise |
| `orders.service.ts` | `GET /api/me/orders/{id}` | Auth client requise |

`NEXT_PUBLIC_USE_MOCKS=0` dans `.env.local` à la fin du sprint.

---

## Périmètre exclu

- RTL arabe — clés i18n présentes, intégration `next-intl` sur `/(client)/` reportée.
- QR scan caméra — bouton "Scanner" redirige vers `/stores` pour l'instant.
- Notifications push PWA.
- Mise à jour temps réel du statut commande (polling / Mercure).
- Kadhia `partially_accepted` — affichage message uniquement, pas de re-draft modifiable.

---

## Risques

| Risque | Mitigation |
|---|---|
| Double rendu children (2 shells CSS) | Acceptable pour pages légères. `useMediaQuery` en fallback si nécessaire. |
| Sync localStorage → backend partielle à la soumission | `submitKadhia()` transactionnel côté UX : erreur = reste sur page slot, localStorage intacte. |
| Chemin endpoint catalog `/api/shops/` vs `/api/stores/` | Valider contre OpenAPI avant premier appel réel. |
| `clientLogin` refuse `ROLE_MERCHANT` | Comportement attendu et correct. |
