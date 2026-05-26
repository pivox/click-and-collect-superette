# Parcours client web — desktop & mobile Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rendre le parcours client `/(client)/` responsive desktop+mobile, ajouter l'auth client JWT avec login/register, et brancher les services sur l'API réelle.

**Architecture:** `/(client)/layout.tsx` rend deux shells (MobileShell + DesktopShell) empilés, visibles via classes Tailwind `md:hidden` / `hidden md:block`. Les pages ajoutent des classes `md:` pour les vues composites (ex. catalog + KadhiaPanel en aside). L'auth client est progressive : publique pour le catalogue, déclenchée au submit de la Kadhia. Les services togglent via `NEXT_PUBLIC_USE_MOCKS`.

**Tech Stack:** Next.js 14, React 18, Tailwind CSS, Vitest + React Testing Library, Axios, localStorage JWT (`jwt_token`).

---

> **Note de décomposition :** Ce plan couvre 3 sous-projets indépendants (A = layout, B = auth, C = API). La Phase A est livrable sans backend. La Phase B nécessite l'auth Symfony. La Phase C nécessite le backend complet. Ils peuvent être mergés séparément.

---

## Carte des fichiers

**Créer :**
- `src/components/product/KadhiaPanel.tsx` — aside sticky desktop sur la page catalogue
- `src/lib/auth/ClientAuthContext.tsx` — context JWT client (`jwt_token`)
- `src/app/(client)/login/page.tsx` — page de connexion client
- `src/app/(client)/register/page.tsx` — page d'inscription client
- `src/tests/client.layout.test.tsx` — tests du double shell
- `src/tests/client.kadhia-panel.test.tsx` — tests du KadhiaPanel
- `src/tests/client.auth.context.test.tsx` — tests du ClientAuthContext

**Modifier :**
- `src/components/layout/DesktopShell.tsx` — nav hrefs `/desktop/…` → routes `/(client)/`
- `src/app/(client)/layout.tsx` — double shell CSS
- `src/app/(client)/page.tsx` — home desktop grid 2 cols
- `src/app/(client)/stores/page.tsx` — grille 3 cols desktop
- `src/app/(client)/stores/[shopId]/page.tsx` — store detail desktop
- `src/app/(client)/stores/[shopId]/catalog/page.tsx` — `md:grid + KadhiaPanel`
- `src/app/(client)/kadhia/slot/page.tsx` — guard auth + vrai submit
- `src/app/(client)/orders/page.tsx` — appel `listOrders()`
- `src/app/(client)/orders/[orderId]/page.tsx` — layout 2-col desktop
- `src/lib/services/auth.service.ts` — `clientLogin`, `clientRegister`
- `src/lib/services/orders.service.ts` — `listOrders`, `getOrder` real
- `src/lib/services/kadhia.service.ts` — `submitKadhia` backend sync
- `src/lib/services/index.ts` — re-export `submitKadhia`, `listOrders`

**Supprimer :**
- `src/app/desktop/page.tsx`

---

## Phase A — Layout responsive & pages desktop

---

### Task 1 : DesktopShell — corriger la nav + supprimer `/desktop/`

**Fichiers :**
- Modifier : `src/components/layout/DesktopShell.tsx:15-22`
- Supprimer : `src/app/desktop/page.tsx`
- Créer : `src/tests/client.layout.test.tsx`

- [ ] **Étape 1 : Écrire le test qui vérifie les hrefs de la nav**

```tsx
// src/tests/client.layout.test.tsx
import { render, screen } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { DesktopShell } from '@/components/layout/DesktopShell';

vi.mock('next/navigation', () => ({ usePathname: () => '/' }));
vi.mock('next/link', () => ({
  default: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

describe('DesktopShell nav hrefs', () => {
  it('links to /(client)/ routes, not /desktop/', () => {
    render(<DesktopShell>content</DesktopShell>);
    const links = screen.getAllByRole('link');
    const hrefs = links.map((l) => l.getAttribute('href'));
    expect(hrefs).toContain('/');
    expect(hrefs).toContain('/stores');
    expect(hrefs).toContain('/kadhia');
    expect(hrefs).toContain('/orders');
    expect(hrefs.some((h) => h?.startsWith('/desktop'))).toBe(false);
  });
});
```

- [ ] **Étape 2 : Lancer le test pour vérifier qu'il échoue**

```bash
npx vitest run src/tests/client.layout.test.tsx
```

Attendu : FAIL — les hrefs pointent encore vers `/desktop/...`.

- [ ] **Étape 3 : Mettre à jour la nav dans DesktopShell**

```tsx
// src/components/layout/DesktopShell.tsx — remplacer le tableau NAV (lignes 15-22)
const NAV = [
  { href: "/",        label: "Accueil",    icon: Home },
  { href: "/stores",  label: "Stores",     icon: Search },
  { href: "/kadhia",  label: "Ma Kadhia",  icon: ShoppingBasket },
  { href: "/orders",  label: "Commandes",  icon: ClipboardList },
] as const;
```

Supprimer aussi les imports `Clock` et `QrCode` devenus inutilisés (ligne 9-10).

- [ ] **Étape 4 : Relancer le test**

```bash
npx vitest run src/tests/client.layout.test.tsx
```

Attendu : PASS.

- [ ] **Étape 5 : Supprimer `/desktop/page.tsx`**

```bash
rm src/app/desktop/page.tsx
```

Vérifier que le dossier `src/app/desktop/` est vide, le supprimer aussi :

```bash
rmdir src/app/desktop/
```

- [ ] **Étape 6 : Commit**

```bash
git add src/components/layout/DesktopShell.tsx src/tests/client.layout.test.tsx
git rm src/app/desktop/page.tsx
git commit -m "feat(client): fix DesktopShell nav to /(client)/ routes, remove /desktop/ prototype"
```

---

### Task 2 : Client layout — double shell CSS

**Fichiers :**
- Modifier : `src/app/(client)/layout.tsx`
- Compléter : `src/tests/client.layout.test.tsx`

- [ ] **Étape 1 : Ajouter le test du double shell dans `client.layout.test.tsx`**

```tsx
// Ajouter dans src/tests/client.layout.test.tsx (après l'import existant)
import ClientLayout from '@/app/(client)/layout';
import { BottomNav } from '@/components/layout/BottomNav';
import { MobileShell } from '@/components/layout/MobileShell';

vi.mock('@/components/layout/MobileShell', () => ({
  MobileShell: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="mobile-shell">{children}</div>
  ),
}));
vi.mock('@/components/layout/DesktopShell', () => ({
  DesktopShell: ({ children }: { children: React.ReactNode }) => (
    <div data-testid="desktop-shell">{children}</div>
  ),
}));
vi.mock('@/components/layout/BottomNav', () => ({
  BottomNav: () => <nav data-testid="bottom-nav" />,
}));

describe('ClientLayout double shell', () => {
  it('rend les deux shells dans le DOM', () => {
    const { container } = render(<ClientLayout>page</ClientLayout>);
    expect(container.querySelector('[data-testid="mobile-shell"]')).toBeTruthy();
    expect(container.querySelector('[data-testid="desktop-shell"]')).toBeTruthy();
  });

  it('le wrapper mobile a la classe md:hidden', () => {
    const { container } = render(<ClientLayout>page</ClientLayout>);
    const mobileWrapper = container.querySelector('[data-testid="mobile-shell"]')?.parentElement;
    expect(mobileWrapper?.className).toContain('md:hidden');
  });

  it('le wrapper desktop a la classe hidden md:block', () => {
    const { container } = render(<ClientLayout>page</ClientLayout>);
    const desktopWrapper = container.querySelector('[data-testid="desktop-shell"]')?.parentElement;
    expect(desktopWrapper?.className).toContain('hidden');
    expect(desktopWrapper?.className).toContain('md:block');
  });
});
```

- [ ] **Étape 2 : Lancer pour vérifier l'échec**

```bash
npx vitest run src/tests/client.layout.test.tsx
```

Attendu : FAIL — le layout actuel ne rend qu'un MobileShell.

- [ ] **Étape 3 : Réécrire `/(client)/layout.tsx`**

```tsx
// src/app/(client)/layout.tsx
import type { Metadata } from "next";
import { MobileShell } from "@/components/layout/MobileShell";
import { DesktopShell } from "@/components/layout/DesktopShell";
import { BottomNav } from "@/components/layout/BottomNav";

export const metadata: Metadata = {
  title: "Kadhia · Click & Collect",
};

export default function ClientLayout({ children }: { children: React.ReactNode }) {
  return (
    <>
      <div className="md:hidden">
        <MobileShell>
          {children}
          <BottomNav />
        </MobileShell>
      </div>
      <div className="hidden md:block">
        <DesktopShell>{children}</DesktopShell>
      </div>
    </>
  );
}
```

- [ ] **Étape 4 : Relancer les tests**

```bash
npx vitest run src/tests/client.layout.test.tsx
```

Attendu : tous PASS.

- [ ] **Étape 5 : Commit**

```bash
git add src/app/\(client\)/layout.tsx src/tests/client.layout.test.tsx
git commit -m "feat(client): layout CSS double shell mobile/desktop"
```

---

### Task 3 : Home page — layout desktop 2 colonnes

**Fichiers :**
- Modifier : `src/app/(client)/page.tsx`

Pas de test unitaire pour du layout CSS pur — vérification visuelle via `npm run dev`.

- [ ] **Étape 1 : Ajouter les classes desktop dans `page.tsx`**

```tsx
// src/app/(client)/page.tsx — version complète
import Link from "next/link";
import { Hero } from "@/components/layout/Hero";
import { Button } from "@/components/ui/Button";
import { StoreCard } from "@/components/store/StoreCard";
import { Card } from "@/components/ui/Card";
import { listShops } from "@/lib/services";

export default async function HomePage() {
  const shops = await listShops();
  const featured = shops.slice(0, 3);
  const featuredShop = shops[0];

  return (
    <>
      {/* Desktop hero : pitch gauche + store featured droite */}
      <div className="hidden md:grid md:grid-cols-[1.3fr_0.7fr] md:gap-5 md:mb-6">
        <Hero
          badge="Supérettes de quartier"
          title="Ta Kadhia prête sans attendre"
          subtitle="Scanne le QR code d'une supérette ou trouve un magasin proche."
          actions={
            <>
              <Link href="/stores">
                <Button variant="secondary">Scanner un QR code</Button>
              </Link>
              <Link href="/stores">
                <Button variant="ghost">Chercher une supérette</Button>
              </Link>
            </>
          }
        />
        {featuredShop && (
          <Card className="rounded-xl">
            <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
              Store reconnu
            </span>
            <h2 className="mt-2 text-h2 font-extrabold">{featuredShop.name}</h2>
            <p className="text-sm text-muted">
              {[featuredShop.address, featuredShop.city].filter(Boolean).join(", ")}
            </p>
            <div className="mt-4 grid grid-cols-3 gap-3">
              {featuredShop.distanceKm != null && (
                <KPI label="distance" value={`${featuredShop.distanceKm} km`} />
              )}
              {featuredShop.nextPickupAt && (
                <KPI label="prochain retrait" value={featuredShop.nextPickupAt} />
              )}
              {featuredShop.rating != null && (
                <KPI label="note" value={featuredShop.rating.toFixed(1)} />
              )}
            </div>
          </Card>
        )}
      </div>

      {/* Mobile hero */}
      <div className="md:hidden">
        <Hero
          badge="Supérettes de quartier"
          title="Ta Kadhia prête sans attendre"
          subtitle="Scanne le QR code d'une supérette ou trouve un magasin proche."
          actions={
            <>
              <Link href="/stores">
                <Button variant="secondary" full>Scanner un QR code</Button>
              </Link>
              <Link href="/stores">
                <Button variant="ghost" full>Chercher une supérette</Button>
              </Link>
            </>
          }
        />
      </div>

      {/* Stores récents */}
      <section className="mt-5">
        <header className="mb-2.5 flex items-baseline justify-between">
          <h2 className="text-h3 font-extrabold m-0">Stores récents</h2>
          <Link href="/stores" className="text-xs font-extrabold text-primary">
            Voir tout
          </Link>
        </header>
        <div className="grid gap-2.5 md:grid-cols-3">
          {featured.map((s) => (
            <StoreCard key={s.id} shop={s} href={`/stores/${s.id}`} />
          ))}
        </div>
      </section>
    </>
  );
}

function KPI({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md bg-soft p-3">
      <strong className="block text-base">{value}</strong>
      <span className="text-xs text-muted">{label}</span>
    </div>
  );
}
```

- [ ] **Étape 2 : Vérification visuelle**

```bash
npm run dev
```

Ouvrir `http://localhost:3000/` dans un navigateur, redimensionner à >768px et vérifier le layout 2 colonnes.

- [ ] **Étape 3 : Commit**

```bash
git add src/app/\(client\)/page.tsx
git commit -m "feat(client/home): desktop 2-col hero + featured store card"
```

---

### Task 4 : Stores list & store detail — desktop

**Fichiers :**
- Modifier : `src/app/(client)/stores/page.tsx`
- Modifier : `src/app/(client)/stores/[shopId]/page.tsx`

- [ ] **Étape 1 : Stores list — grille 3 cols desktop**

```tsx
// src/app/(client)/stores/page.tsx — version complète
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { SearchInput } from "@/components/ui/SearchInput";
import { StoreCard } from "@/components/store/StoreCard";
import { listShops } from "@/lib/services";

export default async function StoresPage() {
  const shops = await listShops();
  return (
    <>
      <TopBar
        title="Trouver une supérette"
        subtitle="Scan QR ou recherche par nom"
        backHref="/"
      />
      <SearchInput placeholder="Nom de la supérette, quartier…" className="mb-4 md:max-w-lg" />
      <div className="grid gap-2.5 md:grid-cols-3">
        {shops.map((s) => (
          <StoreCard key={s.id} shop={s} href={`/stores/${s.id}`} />
        ))}
      </div>
      <p className="mt-4 text-center text-xs text-muted">
        Tu peux aussi{" "}
        <Link href="/" className="font-extrabold text-primary">
          scanner directement
        </Link>{" "}
        le QR à l&apos;entrée.
      </p>
    </>
  );
}
```

- [ ] **Étape 2 : Store detail — layout desktop 2 colonnes**

```tsx
// src/app/(client)/stores/[shopId]/page.tsx — version complète
import Link from "next/link";
import { notFound } from "next/navigation";
import { Hero } from "@/components/layout/Hero";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { getShop } from "@/lib/services";

export default async function StoreDetailPage({
  params,
}: {
  params: { shopId: string };
}) {
  const shop = await getShop(params.shopId);
  if (!shop) notFound();

  const badgeText = shop.isActive
    ? `Ouverte · Retrait dès ${shop.nextPickupAt ?? "—"}`
    : "Fermée";

  return (
    <>
      <TopBar
        title={shop.name}
        subtitle={[shop.address, shop.city].filter(Boolean).join(" · ")}
        backHref="/"
      />

      {/* Desktop : hero gauche + infos droite */}
      <div className="md:grid md:grid-cols-[1.3fr_0.7fr] md:gap-5">
        <Hero
          badge={badgeText}
          title={shop.name}
          subtitle="Produits du quotidien, boissons, lait, pâtes, conserves, hygiène et snacks."
        />

        <div className="mt-4 md:mt-0 space-y-3">
          <div className="grid grid-cols-2 gap-2.5">
            <Card compact>
              <strong className="block text-sm">Horaires</strong>
              <span className="mt-1 block text-xs text-muted">
                {shop.opensAt} — {shop.closesAt}
              </span>
            </Card>
            <Card compact>
              <strong className="block text-sm">Distance</strong>
              <span className="mt-1 block text-xs text-muted">
                {shop.distanceKm != null ? `${shop.distanceKm} km` : "—"}
              </span>
            </Card>
          </div>

          <Card>
            <Summary>
              <SummaryRow label="Paiement" value="Sur place" />
              <SummaryRow
                label="Créneau"
                value={shop.nextPickupAt ? `Dès ${shop.nextPickupAt}` : "—"}
              />
              <SummaryRow label="Note" value={shop.rating?.toFixed(1) ?? "—"} />
            </Summary>
          </Card>

          {/* CTA inline sur desktop, sticky sur mobile */}
          <div className="hidden md:block">
            <Link href={`/stores/${shop.id}/catalog`}>
              <Button full>Commencer ma Kadhia</Button>
            </Link>
          </div>
        </div>
      </div>

      <StickyBottom className="md:hidden">
        <Link href={`/stores/${shop.id}/catalog`}>
          <Button full>Commencer ma Kadhia</Button>
        </Link>
      </StickyBottom>
    </>
  );
}
```

- [ ] **Étape 3 : Vérification visuelle**

```bash
npm run dev
```

Ouvrir `http://localhost:3000/stores` et une page store. Vérifier la grille 3 cols et le detail 2-col à >768px.

- [ ] **Étape 4 : Commit**

```bash
git add src/app/\(client\)/stores/page.tsx "src/app/(client)/stores/[shopId]/page.tsx"
git commit -m "feat(client/stores): desktop 3-col list + store detail 2-col layout"
```

---

### Task 5 : KadhiaPanel — composant aside desktop

**Fichiers :**
- Créer : `src/components/product/KadhiaPanel.tsx`
- Créer : `src/tests/client.kadhia-panel.test.tsx`

- [ ] **Étape 1 : Écrire les tests**

```tsx
// src/tests/client.kadhia-panel.test.tsx
import { render, screen, act } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { KadhiaPanel } from '@/components/product/KadhiaPanel';
import type { Kadhia } from '@/types';

vi.mock('next/link', () => ({
  default: ({ href, children }: { href: string; children: React.ReactNode }) => (
    <a href={href}>{children}</a>
  ),
}));

const mockKadhiaEmpty: Kadhia = {
  id: 'k-1',
  shopId: 'shop-1',
  status: 'draft',
  lines: [],
  totalTnd: '0.000',
};

const mockKadhiaWithLines: Kadhia = {
  id: 'k-1',
  shopId: 'shop-1',
  status: 'draft',
  lines: [
    {
      id: 'l-1',
      productOffer: {
        id: 'p-1',
        productReferenceId: 'ref-1',
        nameFr: 'Lait Vitalait 1L',
        nameAr: null,
        brand: 'Vitalait',
        volume: 1000,
        unit: 'ml',
        priceTnd: '3.000',
        isAvailable: true,
        photoUrl: null,
        category: 'dairy',
      },
      quantity: 2,
      unitPriceTnd: '3.000',
      lineTotalTnd: '6.000',
    },
  ],
  totalTnd: '6.000',
};

describe('KadhiaPanel', () => {
  it('affiche un état vide quand la kadhia est vide', () => {
    render(<KadhiaPanel kadhia={mockKadhiaEmpty} shopId="shop-1" />);
    expect(screen.getByText(/kadhia vide/i)).toBeTruthy();
  });

  it('affiche les lignes quand la kadhia a des articles', () => {
    render(<KadhiaPanel kadhia={mockKadhiaWithLines} shopId="shop-1" />);
    expect(screen.getByText('Lait Vitalait 1L')).toBeTruthy();
    expect(screen.getByText('6.000 TND')).toBeTruthy();
  });

  it('affiche le total', () => {
    render(<KadhiaPanel kadhia={mockKadhiaWithLines} shopId="shop-1" />);
    expect(screen.getByText(/total/i)).toBeTruthy();
  });

  it('le CTA pointe vers /kadhia/slot quand la kadhia a des lignes', () => {
    render(<KadhiaPanel kadhia={mockKadhiaWithLines} shopId="shop-1" />);
    const cta = screen.getByRole('link', { name: /créneau/i });
    expect(cta.getAttribute('href')).toBe('/kadhia/slot');
  });

  it('le CTA est désactivé quand la kadhia est vide', () => {
    render(<KadhiaPanel kadhia={mockKadhiaEmpty} shopId="shop-1" />);
    const btn = screen.getByRole('button', { name: /créneau/i });
    expect(btn).toBeDisabled();
  });
});
```

- [ ] **Étape 2 : Lancer pour vérifier l'échec**

```bash
npx vitest run src/tests/client.kadhia-panel.test.tsx
```

Attendu : FAIL — composant inexistant.

- [ ] **Étape 3 : Créer `KadhiaPanel.tsx`**

```tsx
// src/components/product/KadhiaPanel.tsx
"use client";

import Link from "next/link";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { KadhiaLineRow } from "@/components/product/KadhiaLineRow";
import { formatTnd } from "@/lib/format";
import type { Kadhia } from "@/types";

interface KadhiaPanelProps {
  kadhia: Kadhia | null;
  shopId: string;
}

export function KadhiaPanel({ kadhia, shopId }: KadhiaPanelProps) {
  const lines = kadhia?.lines ?? [];
  const total = kadhia?.totalTnd ?? "0.000";
  const isEmpty = lines.length === 0;

  return (
    <Card className="sticky top-7 rounded-xl p-5">
      <div className="mb-3 flex items-baseline justify-between">
        <h2 className="m-0 text-h2 font-extrabold">Ma Kadhia</h2>
        {!isEmpty && (
          <span className="text-xs font-extrabold text-primary">
            {lines.reduce((a, l) => a + l.quantity, 0)} article
            {lines.reduce((a, l) => a + l.quantity, 0) > 1 ? "s" : ""}
          </span>
        )}
      </div>

      {isEmpty ? (
        <p className="py-4 text-center text-sm text-muted">
          Kadhia vide — ajoute des produits
        </p>
      ) : (
        <>
          <div className="grid gap-2 mb-4">
            {lines.map((l) => (
              <KadhiaLineRow key={l.id} line={l} />
            ))}
          </div>
          <Summary>
            <SummaryRow label="Total estimé" value={formatTnd(total)} total />
          </Summary>
        </>
      )}

      <div className="mt-4">
        {isEmpty ? (
          <Button full disabled>
            Choisir un créneau
          </Button>
        ) : (
          <Link href="/kadhia/slot">
            <Button full>Choisir un créneau</Button>
          </Link>
        )}
      </div>

      <p className="mt-2 text-xs text-muted">
        Prix figés à la soumission.
      </p>
    </Card>
  );
}
```

- [ ] **Étape 4 : Relancer les tests**

```bash
npx vitest run src/tests/client.kadhia-panel.test.tsx
```

Attendu : tous PASS.

- [ ] **Étape 5 : Commit**

```bash
git add src/components/product/KadhiaPanel.tsx src/tests/client.kadhia-panel.test.tsx
git commit -m "feat(client): KadhiaPanel composant aside desktop"
```

---

### Task 6 : Catalog page — layout desktop + KadhiaPanel

**Fichiers :**
- Modifier : `src/app/(client)/stores/[shopId]/catalog/page.tsx`

- [ ] **Étape 1 : Réécrire la page catalogue**

Le state passe de `cartCount: number` à `kadhia: Kadhia | null` pour alimenter le `KadhiaPanel`.

```tsx
// src/app/(client)/stores/[shopId]/catalog/page.tsx
"use client";

import { useEffect, useMemo, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { SearchInput } from "@/components/ui/SearchInput";
import { ProductCard } from "@/components/product/ProductCard";
import { KadhiaPanel } from "@/components/product/KadhiaPanel";
import { ShoppingBasket } from "lucide-react";
import {
  addLine,
  getCurrentKadhia,
  getShop,
  listCatalog,
} from "@/lib/services";
import type { Kadhia, ProductOffer, Shop } from "@/types";
import { PRODUCT_CATEGORIES } from "@/lib/mock/products.mock";

export default function CatalogPage({
  params,
}: {
  params: { shopId: string };
}) {
  const { shopId } = params;
  const [category, setCategory] = useState<"all" | ProductOffer["category"]>("all");
  const [search, setSearch] = useState("");
  const [products, setProducts] = useState<ProductOffer[]>([]);
  const [kadhia, setKadhia] = useState<Kadhia | null>(null);
  const [shop, setShop] = useState<Shop | null>(null);

  useEffect(() => {
    void listCatalog({ shopId, category, search }).then(setProducts);
  }, [shopId, category, search]);

  useEffect(() => {
    void getCurrentKadhia(shopId).then(setKadhia);
  }, [shopId]);

  useEffect(() => {
    void getShop(shopId).then(setShop);
  }, [shopId]);

  const onAdd = async (p: ProductOffer) => {
    const next = await addLine(shopId, p, 1);
    setKadhia(next);
  };

  const cartCount = useMemo(
    () => kadhia?.lines.reduce((acc, l) => acc + l.quantity, 0) ?? 0,
    [kadhia],
  );

  const cartLabel = cartCount === 0
    ? "Kadhia vide"
    : `${cartCount} article${cartCount > 1 ? "s" : ""}`;

  return (
    <>
      <TopBar
        title="Catalogue"
        subtitle={shop?.name}
        backHref={`/stores/${shopId}`}
        action={
          <Link
            href="/kadhia"
            aria-label="Voir ma Kadhia"
            className="relative grid h-10 w-10 place-items-center rounded-[15px] border border-line bg-card shadow-[0_8px_18px_rgba(18,30,20,.06)] md:hidden"
          >
            <ShoppingBasket size={18} />
            {cartCount > 0 && (
              <span className="absolute -right-1 -top-1 grid h-5 min-w-[20px] place-items-center rounded-full bg-primary px-1 text-[10px] font-black text-white">
                {cartCount}
              </span>
            )}
          </Link>
        }
      />

      <SearchInput
        placeholder="Rechercher un produit"
        value={search}
        onChange={(e) => setSearch(e.currentTarget.value)}
        className="mb-3"
      />

      <PillRow className="mb-4">
        {PRODUCT_CATEGORIES.map((c) => (
          <Pill
            key={c.key}
            active={category === c.key}
            onClick={() => setCategory(c.key)}
          >
            {c.labelFr}
          </Pill>
        ))}
      </PillRow>

      {/* Desktop : catalogue + KadhiaPanel sticky */}
      <div className="md:grid md:grid-cols-[1fr_360px] md:gap-5 md:items-start">
        <section>
          <header className="mb-2.5 flex items-baseline justify-between">
            <h3 className="m-0 text-h3 font-extrabold">Produits</h3>
            <Link href="/kadhia" className="text-xs font-extrabold text-primary md:hidden">
              {cartLabel}
            </Link>
          </header>
          <div className="grid grid-cols-2 gap-2.5 md:grid-cols-3">
            {products.map((p) => (
              <ProductCard key={p.id} product={p} onAdd={onAdd} />
            ))}
          </div>
        </section>

        <div className="hidden md:block">
          <KadhiaPanel kadhia={kadhia} shopId={shopId} />
        </div>
      </div>
    </>
  );
}
```

- [ ] **Étape 2 : Vérification visuelle**

```bash
npm run dev
```

Ouvrir `http://localhost:3000/stores/shop-el-amel/catalog` à >768px. Vérifier que la KadhiaPanel apparaît à droite, sticky, et se met à jour quand on ajoute un produit.

- [ ] **Étape 3 : Commit**

```bash
git add "src/app/(client)/stores/[shopId]/catalog/page.tsx"
git commit -m "feat(client/catalog): desktop layout + KadhiaPanel sticky aside"
```

---

### Task 7 : Order tracking — layout desktop 2 colonnes

**Fichiers :**
- Modifier : `src/app/(client)/orders/[orderId]/page.tsx`

- [ ] **Étape 1 : Ajouter la grille 2 colonnes desktop**

```tsx
// src/app/(client)/orders/[orderId]/page.tsx — version complète
import Link from "next/link";
import { notFound } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { Timeline } from "@/components/ui/Timeline";
import { Button } from "@/components/ui/Button";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { getOrder, projectTimeline } from "@/lib/services";
import { formatTnd, formatTime } from "@/lib/format";

export default async function OrderTrackingPage({
  params,
}: {
  params: { orderId: string };
}) {
  const order = await getOrder(params.orderId);
  if (!order) notFound();

  const badge = orderStatusBadge(order.status);
  const steps = projectTimeline(order);
  const showQrCta = order.status === "ready" || order.status === "pickup_pending";

  return (
    <>
      <TopBar
        title={order.code}
        subtitle="Superette El Amel"
        backHref="/orders"
      />

      <div className="md:grid md:grid-cols-2 md:gap-5 md:items-start">
        {/* Colonne gauche : timeline */}
        <div>
          <Card>
            <Badge tone={badge.tone}>{badge.label}</Badge>
            <div className="mt-3">
              <Summary>
                <SummaryRow
                  label="Retrait"
                  value={
                    order.pickupSlot
                      ? `Aujourd'hui · ${formatTime(order.pickupSlot.startsAt)}`
                      : "—"
                  }
                />
                <SummaryRow
                  label="Total"
                  value={formatTnd(order.totalAmountTnd)}
                />
                <SummaryRow label="Code" value={order.code} />
              </Summary>
            </div>
          </Card>

          <section className="mt-4">
            <h3 className="mb-2.5 text-h3 font-extrabold">Suivi</h3>
            <Card>
              <Timeline steps={steps} />
            </Card>
          </section>
        </div>

        {/* Colonne droite : note + CTA */}
        <div>
          {order.customerNote && (
            <section className="mt-4 md:mt-0">
              <h3 className="mb-2.5 text-h3 font-extrabold">Ta note</h3>
              <Card className="text-sm text-muted">{order.customerNote}</Card>
            </section>
          )}

          {/* CTA inline sur desktop */}
          <div className="hidden md:block mt-4">
            {showQrCta ? (
              <Link href={`/orders/${order.code}/pickup`}>
                <Button full>Afficher le QR retrait</Button>
              </Link>
            ) : (
              <Button full disabled>
                QR retrait — disponible quand prête
              </Button>
            )}
          </div>
        </div>
      </div>

      {/* CTA sticky sur mobile */}
      <StickyBottom className="md:hidden">
        {showQrCta ? (
          <Link href={`/orders/${order.code}/pickup`}>
            <Button full>Afficher le QR retrait</Button>
          </Link>
        ) : (
          <Button full disabled>
            QR retrait — disponible quand la commande est prête
          </Button>
        )}
      </StickyBottom>
    </>
  );
}
```

- [ ] **Étape 2 : Vérification visuelle**

```bash
npm run dev
```

Ouvrir `http://localhost:3000/orders/CMD-4821` à >768px. Vérifier le layout 2 colonnes.

- [ ] **Étape 3 : Commit**

```bash
git add "src/app/(client)/orders/[orderId]/page.tsx"
git commit -m "feat(client/orders): order tracking 2-col desktop layout"
```

---

## Phase B — Auth client (progressive)

---

### Task 8 : ClientAuthContext + auth.service

**Fichiers :**
- Modifier : `src/lib/services/auth.service.ts`
- Créer : `src/lib/auth/ClientAuthContext.tsx`
- Créer : `src/tests/client.auth.context.test.tsx`

- [ ] **Étape 1 : Écrire les tests**

```tsx
// src/tests/client.auth.context.test.tsx
import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

vi.mock('next/navigation', () => ({
  useRouter: () => ({ push: vi.fn(), replace: vi.fn() }),
}));

vi.mock('@/lib/services/auth.service', () => ({
  clientLogin: vi.fn(),
  clientRegister: vi.fn(),
  decodeJwtPayload: vi.fn(),
}));

import { ClientAuthProvider, useClientAuth } from '@/lib/auth/ClientAuthContext';
import { clientLogin, decodeJwtPayload } from '@/lib/services/auth.service';

function TestConsumer() {
  const auth = useClientAuth();
  if (auth.isLoading) return <span>loading</span>;
  return (
    <div>
      <span data-testid="user">{auth.user?.email ?? 'none'}</span>
      <button onClick={() => auth.login('a@b.com', 'pass')}>login</button>
      <button onClick={() => auth.logout()}>logout</button>
    </div>
  );
}

describe('ClientAuthContext', () => {
  beforeEach(() => {
    localStorage.clear();
    vi.clearAllMocks();
  });

  it('user est null sans token en localStorage', async () => {
    render(
      <ClientAuthProvider>
        <TestConsumer />
      </ClientAuthProvider>,
    );
    await waitFor(() => screen.getByTestId('user'));
    expect(screen.getByTestId('user').textContent).toBe('none');
  });

  it('restore le user depuis localStorage au montage', async () => {
    localStorage.setItem('jwt_token', 'tok');
    vi.mocked(decodeJwtPayload).mockReturnValue({
      email: 'u@test.com',
      name: 'User Test',
      roles: ['ROLE_CUSTOMER'],
    });
    render(
      <ClientAuthProvider>
        <TestConsumer />
      </ClientAuthProvider>,
    );
    await waitFor(() =>
      expect(screen.getByTestId('user').textContent).toBe('u@test.com'),
    );
  });

  it('login stocke le token et met à jour le user', async () => {
    vi.mocked(clientLogin).mockResolvedValue({
      token: 'new-tok',
      email: 'login@test.com',
      name: 'Login User',
    });
    render(
      <ClientAuthProvider>
        <TestConsumer />
      </ClientAuthProvider>,
    );
    await waitFor(() => screen.getByRole('button', { name: 'login' }));
    await act(async () => {
      screen.getByRole('button', { name: 'login' }).click();
    });
    await waitFor(() =>
      expect(screen.getByTestId('user').textContent).toBe('login@test.com'),
    );
    expect(localStorage.getItem('jwt_token')).toBe('new-tok');
  });

  it('logout vide le token et met user à null', async () => {
    localStorage.setItem('jwt_token', 'tok');
    vi.mocked(decodeJwtPayload).mockReturnValue({
      email: 'u@test.com',
      name: 'U',
      roles: ['ROLE_CUSTOMER'],
    });
    render(
      <ClientAuthProvider>
        <TestConsumer />
      </ClientAuthProvider>,
    );
    await waitFor(() =>
      expect(screen.getByTestId('user').textContent).toBe('u@test.com'),
    );
    act(() => screen.getByRole('button', { name: 'logout' }).click());
    await waitFor(() =>
      expect(screen.getByTestId('user').textContent).toBe('none'),
    );
    expect(localStorage.getItem('jwt_token')).toBeNull();
  });
});
```

- [ ] **Étape 2 : Lancer pour vérifier l'échec**

```bash
npx vitest run src/tests/client.auth.context.test.tsx
```

Attendu : FAIL — modules inexistants.

- [ ] **Étape 3 : Ajouter `clientLogin` et `clientRegister` dans auth.service.ts**

```ts
// Ajouter à la fin de src/lib/services/auth.service.ts

export interface ClientUser {
  token: string;
  email: string;
  name: string;
}

export async function clientLogin(email: string, password: string): Promise<ClientUser> {
  const { data } = await apiClient.post<{ token: string }>('/api/auth/login', {
    email,
    password,
  });
  const payload = decodeJwtPayload(data.token);
  const roles = Array.isArray(payload.roles) ? (payload.roles as string[]) : [];
  if (!roles.includes('ROLE_CUSTOMER')) {
    throw new Error('Accès réservé aux clients');
  }
  return {
    token: data.token,
    email: typeof payload.email === 'string' ? payload.email : email,
    name: typeof payload.name === 'string' ? payload.name : email,
  };
}

export async function clientRegister(
  email: string,
  password: string,
  name: string,
): Promise<void> {
  await apiClient.post('/api/auth/register', { email, password, name });
}
```

- [ ] **Étape 4 : Créer `ClientAuthContext.tsx`**

```tsx
// src/lib/auth/ClientAuthContext.tsx
'use client';

import React, { createContext, useContext, useEffect, useState } from 'react';
import { useRouter } from 'next/navigation';
import {
  clientLogin as apiClientLogin,
  decodeJwtPayload,
  type ClientUser,
} from '@/lib/services/auth.service';

interface ClientAuthContextValue {
  user: ClientUser | null;
  isLoading: boolean;
  login: (email: string, password: string) => Promise<void>;
  logout: () => void;
}

const ClientAuthContext = createContext<ClientAuthContextValue | null>(null);

export function ClientAuthProvider({ children }: { children: React.ReactNode }) {
  const [user, setUser] = useState<ClientUser | null>(null);
  const [isLoading, setIsLoading] = useState(true);
  const router = useRouter();

  useEffect(() => {
    const token = localStorage.getItem('jwt_token');
    if (token) {
      try {
        const payload = decodeJwtPayload(token);
        setUser({
          token,
          email: typeof payload.email === 'string' ? payload.email : '',
          name: typeof payload.name === 'string' ? payload.name : '',
        });
      } catch {
        localStorage.removeItem('jwt_token');
      }
    }
    setIsLoading(false);
  }, []);

  const login = async (email: string, password: string) => {
    const clientUser = await apiClientLogin(email, password);
    localStorage.setItem('jwt_token', clientUser.token);
    setUser(clientUser);
    router.push('/');
  };

  const logout = () => {
    localStorage.removeItem('jwt_token');
    setUser(null);
    router.push('/login');
  };

  return (
    <ClientAuthContext.Provider value={{ user, isLoading, login, logout }}>
      {children}
    </ClientAuthContext.Provider>
  );
}

export function useClientAuth(): ClientAuthContextValue {
  const ctx = useContext(ClientAuthContext);
  if (!ctx) throw new Error('useClientAuth must be used inside ClientAuthProvider');
  return ctx;
}
```

- [ ] **Étape 5 : Ajouter `ClientAuthProvider` dans le layout client**

```tsx
// src/app/(client)/layout.tsx — ajouter l'import et wrapper
import { ClientAuthProvider } from "@/lib/auth/ClientAuthContext";

export default function ClientLayout({ children }: { children: React.ReactNode }) {
  return (
    <ClientAuthProvider>
      <div className="md:hidden">
        <MobileShell>
          {children}
          <BottomNav />
        </MobileShell>
      </div>
      <div className="hidden md:block">
        <DesktopShell>{children}</DesktopShell>
      </div>
    </ClientAuthProvider>
  );
}
```

- [ ] **Étape 6 : Relancer les tests**

```bash
npx vitest run src/tests/client.auth.context.test.tsx
```

Attendu : tous PASS.

- [ ] **Étape 7 : Commit**

```bash
git add src/lib/services/auth.service.ts src/lib/auth/ClientAuthContext.tsx \
  src/app/\(client\)/layout.tsx src/tests/client.auth.context.test.tsx
git commit -m "feat(client/auth): ClientAuthContext + clientLogin + clientRegister"
```

---

### Task 9 : Login, register pages + guard sur slot

**Fichiers :**
- Créer : `src/app/(client)/login/page.tsx`
- Créer : `src/app/(client)/register/page.tsx`
- Modifier : `src/app/(client)/kadhia/slot/page.tsx`

- [ ] **Étape 1 : Page login**

```tsx
// src/app/(client)/login/page.tsx
'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter, useSearchParams } from 'next/navigation';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';

export default function ClientLoginPage() {
  const { login } = useClientAuth();
  const router = useRouter();
  const searchParams = useSearchParams();
  const redirect = searchParams.get('redirect') ?? '/';

  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await login(email, password);
      router.push(redirect);
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Identifiants incorrects');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-[80vh] items-center justify-center px-4">
      <Card className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
            Kadhia
          </span>
          <h1 className="mt-1 text-h2 font-black">Connexion</h1>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="email">
              Email
            </label>
            <input
              id="email"
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="password">
              Mot de passe
            </label>
            <input
              id="password"
              type="password"
              required
              autoComplete="current-password"
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          {error && (
            <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
              {error}
            </p>
          )}

          <Button full type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Connexion…' : 'Se connecter'}
          </Button>
        </form>

        <p className="mt-4 text-center text-sm text-muted">
          Pas encore de compte ?{' '}
          <Link href="/register" className="font-extrabold text-primary">
            Créer un compte
          </Link>
        </p>
      </Card>
    </div>
  );
}
```

- [ ] **Étape 2 : Page register**

```tsx
// src/app/(client)/register/page.tsx
'use client';

import { useState, type FormEvent } from 'react';
import Link from 'next/link';
import { useRouter } from 'next/navigation';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { clientRegister } from '@/lib/services/auth.service';

export default function ClientRegisterPage() {
  const router = useRouter();
  const [name, setName] = useState('');
  const [email, setEmail] = useState('');
  const [password, setPassword] = useState('');
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  const handleSubmit = async (e: FormEvent) => {
    e.preventDefault();
    setError(null);
    setIsSubmitting(true);
    try {
      await clientRegister(email, password, name);
      router.push('/login');
    } catch (err) {
      setError(err instanceof Error ? err.message : "Erreur lors de l'inscription");
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <div className="flex min-h-[80vh] items-center justify-center px-4">
      <Card className="w-full max-w-sm">
        <div className="mb-6 text-center">
          <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
            Kadhia
          </span>
          <h1 className="mt-1 text-h2 font-black">Créer un compte</h1>
        </div>

        <form onSubmit={handleSubmit} className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="name">
              Nom
            </label>
            <input
              id="name"
              type="text"
              required
              autoComplete="name"
              value={name}
              onChange={(e) => setName(e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="reg-email">
              Email
            </label>
            <input
              id="reg-email"
              type="email"
              required
              autoComplete="email"
              value={email}
              onChange={(e) => setEmail(e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-semibold" htmlFor="reg-password">
              Mot de passe
            </label>
            <input
              id="reg-password"
              type="password"
              required
              autoComplete="new-password"
              minLength={8}
              value={password}
              onChange={(e) => setPassword(e.target.value)}
              className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            />
          </div>

          {error && (
            <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
              {error}
            </p>
          )}

          <Button full type="submit" disabled={isSubmitting}>
            {isSubmitting ? 'Inscription…' : "Créer mon compte"}
          </Button>
        </form>

        <p className="mt-4 text-center text-sm text-muted">
          Déjà un compte ?{' '}
          <Link href="/login" className="font-extrabold text-primary">
            Se connecter
          </Link>
        </p>
      </Card>
    </div>
  );
}
```

- [ ] **Étape 3 : Guard auth dans la page slot**

```tsx
// src/app/(client)/kadhia/slot/page.tsx — ajouter le guard en haut du composant
// Après les imports existants, ajouter :
import { useClientAuth } from '@/lib/auth/ClientAuthContext';

// Dans le composant SlotPage, ajouter AVANT le premier return :
export default function SlotPage() {
  const router = useRouter();
  const { user, isLoading } = useClientAuth();
  // ... états existants ...

  // Redirect si non authentifié (progressive auth)
  useEffect(() => {
    if (!isLoading && !user) {
      router.push('/login?redirect=/kadhia/slot');
    }
  }, [isLoading, user, router]);

  if (isLoading || !user) return null;

  // ... reste du composant identique ...
}
```

- [ ] **Étape 4 : Vérification manuelle**

```bash
npm run dev
```

1. Aller sur `http://localhost:3000/kadhia/slot` sans être connecté → doit rediriger vers `/login?redirect=/kadhia/slot`.
2. Aller sur `/register` → formulaire s'affiche, après soumission redirige vers `/login`.
3. Aller sur `/login` → formulaire s'affiche.

- [ ] **Étape 5 : Commit**

```bash
git add src/app/\(client\)/login/page.tsx src/app/\(client\)/register/page.tsx \
  src/app/\(client\)/kadhia/slot/page.tsx
git commit -m "feat(client/auth): login + register pages + progressive auth guard on slot"
```

---

## Phase C — Connexion API réelle

---

### Task 10 : listOrders + getOrder réel

**Fichiers :**
- Modifier : `src/lib/services/orders.service.ts`
- Modifier : `src/lib/services/index.ts`
- Modifier : `src/app/(client)/orders/page.tsx`

> ⚠ Vérifier les chemins exacts des endpoints contre `/api/docs.json` avant de lancer.
> Backend local : `NEXT_PUBLIC_API_URL=http://localhost:8000`, `NEXT_PUBLIC_USE_MOCKS=0`.

- [ ] **Étape 1 : Ajouter `listOrders` dans orders.service.ts**

```ts
// src/lib/services/orders.service.ts — ajouter après les imports

export async function listOrders(): Promise<Order[]> {
  if (USE_MOCKS) {
    return mockDelay([MOCK_ORDER]);
  }
  const { data } = await apiClient.get<Order[]>('/api/me/orders');
  return data;
}
```

Mettre à jour `getOrder` pour utiliser le chemin `/api/me/orders/{id}` :

```ts
export async function getOrder(orderId: string): Promise<Order | null> {
  if (USE_MOCKS) {
    return mockDelay(MOCK_ORDER);
  }
  const { data } = await apiClient.get<Order>(`/api/me/orders/${orderId}`);
  return data;
}
```

- [ ] **Étape 2 : Exporter `listOrders` depuis index.ts**

```ts
// src/lib/services/index.ts — ajouter à la ligne des exports orders
export * from "./orders.service";  // déjà présent, listOrders est maintenant inclus
```

- [ ] **Étape 3 : Mettre à jour la page orders pour utiliser `listOrders`**

```tsx
// src/app/(client)/orders/page.tsx — version complète
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { formatTnd } from "@/lib/format";
import { listOrders } from "@/lib/services";

export default async function OrdersListPage() {
  const orders = await listOrders();

  return (
    <>
      <TopBar title="Mes commandes" subtitle="Historique et en cours" backHref="/" />
      {orders.length === 0 ? (
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">Aucune commande pour le moment.</p>
        </Card>
      ) : (
        <div className="grid gap-2.5 md:grid-cols-2">
          {orders.map((o) => {
            const badge = orderStatusBadge(o.status);
            return (
              <Link key={o.id} href={`/orders/${o.code}`}>
                <Card compact className="hover:bg-soft transition-colors">
                  <div className="flex items-baseline justify-between">
                    <strong className="text-sm">{o.code}</strong>
                    <Badge tone={badge.tone}>{badge.label}</Badge>
                  </div>
                  <div className="mt-2 flex items-baseline justify-between text-xs text-muted">
                    <span>Superette El Amel</span>
                    <span className="font-black text-ink">
                      {formatTnd(o.totalAmountTnd)}
                    </span>
                  </div>
                </Card>
              </Link>
            );
          })}
        </div>
      )}
    </>
  );
}
```

- [ ] **Étape 4 : Commit**

```bash
git add src/lib/services/orders.service.ts src/app/\(client\)/orders/page.tsx
git commit -m "feat(client/orders): listOrders + real API endpoints"
```

---

### Task 11 : submitKadhia — sync localStorage → backend

**Fichiers :**
- Modifier : `src/lib/services/kadhia.service.ts`
- Modifier : `src/lib/services/index.ts`
- Modifier : `src/app/(client)/kadhia/slot/page.tsx`

> ⚠ Vérifier les endpoints Kadhia sur `/api/docs.json` :
> - Créer : `POST /api/me/stores/{storeId}/kadhias`
> - Ajouter ligne : `PUT /api/me/kadhias/{kadhiaId}/lines/{merchantProductId}`
> - Soumettre : `POST /api/me/kadhias/{kadhiaId}/submit`

- [ ] **Étape 1 : Ajouter `submitKadhia` dans kadhia.service.ts**

```ts
// src/lib/services/kadhia.service.ts — ajouter à la fin

export interface SubmitKadhiaParams {
  shopId: string;
  pickupSlotId: string;
  customerNote?: string;
}

export interface SubmittedOrder {
  orderId: string;
  orderCode: string;
}

export async function submitKadhia(params: SubmitKadhiaParams): Promise<SubmittedOrder> {
  const { shopId, pickupSlotId, customerNote } = params;

  if (USE_MOCKS) {
    write(null); // vide localStorage
    return mockDelay({ orderId: MOCK_SUBMIT_ORDER_ID, orderCode: 'CMD-4821' });
  }

  // 1. Lire la Kadhia en localStorage
  const local = read();
  if (!local || local.lines.length === 0) {
    throw new Error('Kadhia vide');
  }

  // 2. Créer la Kadhia backend
  const { data: backendKadhia } = await apiClient.post<{ id: string }>(
    `/api/me/stores/${shopId}/kadhias`,
    {},
  );

  // 3. Synchroniser les lignes
  for (const line of local.lines) {
    await apiClient.put(
      `/api/me/kadhias/${backendKadhia.id}/lines/${line.productOffer.id}`,
      { quantity: line.quantity },
    );
  }

  // 4. Soumettre
  const { data: order } = await apiClient.post<{ id: string; code: string }>(
    `/api/me/kadhias/${backendKadhia.id}/submit`,
    { pickupSlotId, customerNote },
  );

  // 5. Vider localStorage
  write(null);

  return { orderId: order.id, orderCode: order.code };
}

const MOCK_SUBMIT_ORDER_ID = 'order-demo-4821';
```

- [ ] **Étape 2 : Exporter depuis index.ts**

```ts
// src/lib/services/index.ts — ajouter dans les exports kadhia
export * from "./kadhia.service";  // submitKadhia est maintenant inclus
```

- [ ] **Étape 3 : Mettre à jour la page slot pour appeler `submitKadhia`**

```tsx
// src/app/(client)/kadhia/slot/page.tsx
// Remplacer le handler du bouton "Envoyer la commande" :

// Ajouter l'import :
import { submitKadhia } from '@/lib/services';

// Ajouter l'état :
const [isSubmitting, setIsSubmitting] = useState(false);
const [submitError, setSubmitError] = useState<string | null>(null);

// Remplacer le onClick du bouton :
const handleSubmit = async () => {
  if (!activeId) return;
  setIsSubmitting(true);
  setSubmitError(null);
  try {
    const result = await submitKadhia({
      shopId: DEMO_SHOP_ID,
      pickupSlotId: activeId,
      customerNote: note.trim() || undefined,
    });
    router.push(`/orders/${result.orderCode}`);
  } catch (err) {
    setSubmitError(
      err instanceof Error ? err.message : 'Erreur lors de la soumission',
    );
  } finally {
    setIsSubmitting(false);
  }
};

// Dans le JSX, remplacer la StickyBottom :
<StickyBottom>
  {submitError && (
    <p className="mb-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
      {submitError}
    </p>
  )}
  <Button
    full
    disabled={!activeId || isSubmitting}
    onClick={handleSubmit}
  >
    {isSubmitting ? 'Envoi en cours…' : 'Envoyer la commande'}
  </Button>
</StickyBottom>
```

- [ ] **Étape 4 : Vérification manuelle avec USE_MOCKS=1**

```bash
npm run dev
```

Ajouter des produits → aller sur `/kadhia/slot` → choisir un créneau → soumettre → doit rediriger vers `/orders/CMD-4821`.

- [ ] **Étape 5 : Commit**

```bash
git add src/lib/services/kadhia.service.ts src/lib/services/index.ts \
  src/app/\(client\)/kadhia/slot/page.tsx
git commit -m "feat(client/kadhia): submitKadhia sync localStorage → backend + slot page wired"
```

---

### Task 12 : Flip USE_MOCKS + vérification endpoint catalog

**Fichiers :**
- Créer/modifier : `.env.local`
- Vérifier : `src/lib/services/catalog.service.ts` et `slots.service.ts`

> Cette tâche nécessite le backend Symfony actif sur `http://localhost:8000`.

- [ ] **Étape 1 : Vérifier les endpoints catalog et slots sur OpenAPI**

```bash
curl http://localhost:8000/api/docs.json | jq '.paths | keys | map(select(test("store|shop|catalog|slot")))'
```

Comparer avec les chemins dans les services :
- `catalog.service.ts` : `GET /shops/${shopId}/catalog`
- `slots.service.ts` : `GET /shops/${shopId}/slots`

Corriger si nécessaire d'après la sortie du curl.

- [ ] **Étape 2 : Créer `.env.local` avec USE_MOCKS=0**

```bash
# .env.local
NEXT_PUBLIC_API_URL=http://localhost:8000
NEXT_PUBLIC_USE_MOCKS=0
```

- [ ] **Étape 3 : Démarrer le backend et le frontend**

Terminal 1 :
```bash
cd apps/backend && symfony server:start
```

Terminal 2 :
```bash
npm run dev
```

- [ ] **Étape 4 : Smoke test manuel du parcours complet**

1. `http://localhost:3000/` → liste des stores réels
2. Cliquer sur une supérette → catalogue réel
3. Ajouter 2 produits → KadhiaPanel se met à jour (desktop)
4. Cliquer "Choisir un créneau" → redirect `/login` si non connecté
5. Se connecter avec un compte client réel
6. Choisir un créneau → soumettre → redirect `/orders/{code}`
7. Voir l'ordre avec timeline réelle

- [ ] **Étape 5 : Commit**

```bash
echo "NEXT_PUBLIC_USE_MOCKS=0" >> .env.local
git add .env.local src/lib/services/catalog.service.ts src/lib/services/slots.service.ts
git commit -m "feat(client/api): USE_MOCKS=0, verify catalog + slots endpoints"
```

---

## Vérification finale

```bash
# Lint
npm run lint

# Tests
npx vitest run

# Build
npm run build
```

Les 3 commandes doivent passer sans erreur avant de créer la PR.
