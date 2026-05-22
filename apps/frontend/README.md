# Frontend — Click & Collect Supérette

Interface client de l'application click & collect pour les supérettes tunisiennes.
Construite avec **Next.js 14** (App Router), **Tailwind CSS** et **React Query**.

## Démarrage rapide

```bash
cp .env.local.dist .env.local   # configurer les variables d'environnement
npm install
npm run dev                     # http://localhost:3000
```

## Variables d'environnement

| Variable | Description | Défaut |
|---|---|---|
| `NEXT_PUBLIC_API_URL` | URL de base de l'API backend | `http://localhost:8000` |
| `NEXT_PUBLIC_USE_MOCKS` | `1` = données mock, `0` = vraie API | `1` |

Copier `.env.local.dist` en `.env.local` et ajuster selon l'environnement.

## Commandes

```bash
npm run dev      # serveur de développement (hot reload)
npm run build    # build de production
npm run start    # serveur de production (après build)
npm run lint     # ESLint
npx tsc --noEmit # vérification TypeScript sans émission
```

## Architecture

```
src/
├── app/
│   ├── page.tsx               # Landing — choix parcours mobile / desktop
│   ├── (client)/              # Parcours client mobile (phone shell)
│   │   ├── layout.tsx         # MobileShell + BottomNav
│   │   ├── page.tsx           # Accueil mobile
│   │   ├── stores/            # Liste et fiche supérette
│   │   ├── kadhia/            # Panier + sélection créneau
│   │   └── orders/            # Suivi commande + QR retrait
│   └── desktop/               # Parcours client desktop (sidebar)
├── components/
│   ├── ui/                    # Primitives : Button, Badge, Card, Pill…
│   ├── layout/                # Shells : MobileShell, TopBar, BottomNav…
│   ├── product/               # ProductCard, KadhiaLineRow
│   └── store/                 # StoreCard
├── lib/
│   ├── services/              # Couche service (mock ↔ API réelle)
│   ├── mock/                  # Données de développement
│   ├── api.ts                 # Axios client (JWT Bearer)
│   ├── cn.ts                  # Utilitaire clsx + tailwind-merge
│   └── format.ts              # Formatage TND, dates, heures
└── types/
    └── index.ts               # Types partagés (Shop, Order, Kadhia…)
```

## Couche services

Chaque service expose des fonctions async dont la signature ne change pas
entre le mode mock et le mode API réelle. Pour basculer sur le vrai backend :

```bash
# .env.local
NEXT_PUBLIC_USE_MOCKS=0
NEXT_PUBLIC_API_URL=http://localhost:8000
```

| Service | Fonctions |
|---|---|
| `stores.service` | `listShops`, `getShop`, `getShopBySlug` |
| `catalog.service` | `listCatalog` |
| `kadhia.service` | `getCurrentKadhia`, `addLine`, `updateLineQuantity`, `clearKadhia` |
| `slots.service` | `listSlotsForShop` |
| `orders.service` | `getOrder`, `projectTimeline` |

## Design system

Les tokens de design sont définis dans `tailwind.config.ts` et les variables
CSS dans `src/app/globals.css`. Couleurs de marque : vert `#1f7a4d` + jaune `#ffcf5a`.
Support RTL intégré (`html[dir="rtl"]`) pour le parcours arabe.

## Stack

| Outil | Usage |
|---|---|
| Next.js 14 | App Router, Server Components, Static/Dynamic rendering |
| Tailwind CSS 3 | Styling, design tokens |
| React Query 5 | Gestion d'état serveur (à brancher sur les services) |
| next-intl | Internationalisation FR / AR |
| Axios | Client HTTP + intercepteur JWT |
| Vitest | Tests unitaires |
| TypeScript 5 | Typage strict |
