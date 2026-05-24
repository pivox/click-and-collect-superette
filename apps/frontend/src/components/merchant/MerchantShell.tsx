'use client';

import Link from 'next/link';
import { BarChart3, CalendarClock, Package, QrCode, Settings, ShoppingBasket } from 'lucide-react';
import { usePathname } from 'next/navigation';
import { Button } from '@/components/ui/Button';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { cn } from '@/lib/cn';

const ACTIVE_NAV = [
  { href: '/merchant', label: 'Dashboard', icon: BarChart3 },
  { href: '/merchant/commandes', label: 'Commandes', icon: ShoppingBasket },
  { href: '/merchant/retrait', label: 'Retrait', icon: QrCode },
];

const DISABLED_NAV = [
  { label: 'Créneaux', icon: CalendarClock },
  { label: 'Catalogue', icon: Package },
  { label: 'Paramètres', icon: Settings },
];

export function MerchantShell({ children }: { children: React.ReactNode }) {
  const { merchant, logout } = useMerchantAuth();
  const pathname = usePathname();

  return (
    <div className="flex min-h-screen bg-bg">
      <aside className="hidden w-64 shrink-0 flex-col border-r border-line bg-[#17211c] text-white md:flex">
        <div className="px-5 py-6">
          <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
            Kadhia Marchand
          </span>
          <strong className="mt-2 block text-base text-white">
            {merchant?.store.name ?? 'Supérette'}
          </strong>
        </div>
        <nav className="flex-1 space-y-1 px-3">
          {ACTIVE_NAV.map((item) => {
            const Icon = item.icon;
            const isActive =
              pathname === item.href || (item.href !== '/merchant' && pathname.startsWith(item.href));
            return (
              <Link
                key={item.href}
                href={item.href}
                className={cn(
                  'flex items-center gap-3 rounded-md px-3 py-2.5 text-sm transition-colors',
                  isActive
                    ? 'bg-white/10 font-semibold text-white'
                    : 'text-white/70 hover:bg-white/5 hover:text-white',
                )}
              >
                <Icon className="h-4 w-4" aria-hidden="true" />
                {item.label}
              </Link>
            );
          })}
          <div className="pt-3">
            {DISABLED_NAV.map((item) => {
              const Icon = item.icon;
              return (
                <button
                  key={item.label}
                  type="button"
                  disabled
                  title="Prévu dans une prochaine PR"
                  className="flex w-full cursor-not-allowed items-center gap-3 rounded-md px-3 py-2.5 text-left text-sm text-white/35"
                >
                  <Icon className="h-4 w-4" aria-hidden="true" />
                  {item.label}
                </button>
              );
            })}
          </div>
        </nav>
      </aside>

      <div className="flex min-w-0 flex-1 flex-col">
        <header className="flex min-h-16 items-center justify-between border-b border-line bg-card px-4 md:px-6">
          <div>
            <p className="text-sm font-bold text-ink md:hidden">
              {merchant?.store.name ?? 'Supérette'}
            </p>
            <p className="text-sm text-muted">{merchant?.email}</p>
          </div>
          <Button variant="ghost" size="md" onClick={logout}>
            Déconnexion
          </Button>
        </header>
        <nav className="flex gap-2 overflow-x-auto border-b border-line bg-card px-4 py-2 md:hidden">
          {ACTIVE_NAV.map((item) => (
            <Link
              key={item.href}
              href={item.href}
              className={cn(
                'rounded-md px-3 py-2 text-sm font-bold',
                pathname === item.href ? 'bg-primary text-white' : 'bg-soft text-ink',
              )}
            >
              {item.label}
            </Link>
          ))}
        </nav>
        <main className="flex-1 overflow-auto p-4 md:p-6">{children}</main>
      </div>
    </div>
  );
}
