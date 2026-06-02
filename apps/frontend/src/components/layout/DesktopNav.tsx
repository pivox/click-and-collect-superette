'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import {
  Home,
  Search,
  ShoppingBasket,
  ClipboardList,
  LogIn,
  LogOut,
} from 'lucide-react';
import { cn } from '@/lib/cn';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useHydrated } from '@/lib/hooks/useHydrated';

const NAV = [
  { href: '/',        label: 'Accueil',    icon: Home },
  { href: '/stores',  label: 'Supérettes', icon: Search },
  { href: '/kadhia',  label: 'Kadhia',     icon: ShoppingBasket },
  { href: '/orders',  label: 'Commandes',  icon: ClipboardList },
] as const;

/**
 * Desktop left sidebar — visible only on md+ screens.
 * Renders independently from page {children} to avoid double-mounting.
 */
export function DesktopNav() {
  const pathname = usePathname() ?? '/';
  const { user, logout } = useClientAuth();
  const { selectedStore } = useSelectedStore();
  const isHydrated = useHydrated();
  const router = useRouter();

  function handleLogout() {
    logout();
    router.push('/');
  }

  const avatarLetters = user
    ? (user.name || user.email)
        .split(/[\s@._-]+/)
        .filter(Boolean)
        .map((w) => w[0])
        .join('')
        .toUpperCase()
        .slice(0, 2)
    : '';

  return (
    <aside className="sticky top-0 hidden h-screen overflow-y-auto border-r border-line bg-white p-6 md:flex md:flex-col md:justify-between">
      <div>
        <div className="mb-7 flex items-center gap-3">
          <div className="grid h-12 w-12 place-items-center rounded-md bg-primary text-white text-lg font-black">
            K
          </div>
          <div>
            <strong className="block text-base">Kadhia</strong>
            <span className="text-xs text-muted">
              Click &amp; Collect Supérette
            </span>
          </div>
        </div>
        <nav className="grid gap-2">
          {NAV.map(({ href, label, icon: Icon }) => {
            const active = pathname === href;
            return (
              <Link
                key={href}
                href={href}
                className={cn(
                  'flex items-center gap-3 rounded-md px-4 py-3 text-sm font-extrabold transition-colors',
                  active
                    ? 'bg-soft text-primary-dark'
                    : 'text-muted hover:bg-soft hover:text-primary-dark',
                )}
              >
                <Icon size={18} />
                {label}
              </Link>
            );
          })}
        </nav>
      </div>

      <div className="mt-6 space-y-4">
        {/* Bloc supérette active */}
        {isHydrated && (
          <div className="border-t border-line pt-4">
            <p className="mb-2 text-[10px] font-extrabold uppercase tracking-widest text-muted">
              Supérette active
            </p>
            <Link
              href="/stores"
              className="flex items-center gap-3 rounded-lg border border-line bg-soft px-3 py-2.5 transition-colors hover:border-primary/30 hover:bg-primary/5"
            >
              <div className="grid h-8 w-8 shrink-0 place-items-center rounded-md bg-primary/10 text-sm font-black text-primary-dark">
                {selectedStore ? (selectedStore.logoLetter ?? selectedStore.name.charAt(0)) : '?'}
              </div>
              <div className="min-w-0 flex-1">
                <strong className="block truncate text-xs">
                  {selectedStore ? selectedStore.name : 'Aucune supérette'}
                </strong>
                <span className="text-[10px] text-primary">Changer →</span>
              </div>
            </Link>
          </div>
        )}

        {/* Bloc user — bottom of sidebar */}
        <div className="border-t border-line pt-4">
          {user ? (
            <div className="flex items-center gap-3">
              <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-primary text-sm font-black text-white">
                {avatarLetters}
              </div>
              <div className="min-w-0 flex-1">
                <strong className="block truncate text-xs">
                  {user.name || 'Client'}
                </strong>
                <span className="block truncate text-[11px] text-muted">
                  {user.email}
                </span>
              </div>
              <button
                type="button"
                onClick={handleLogout}
                aria-label="Se déconnecter"
                className="grid h-8 w-8 shrink-0 place-items-center rounded-md text-muted hover:bg-soft hover:text-red-600"
              >
                <LogOut size={16} />
              </button>
            </div>
          ) : (
            <div className="grid gap-2">
              <Link
                href="/login"
                className="flex items-center gap-2 rounded-md px-4 py-2.5 text-sm font-extrabold text-muted hover:bg-soft hover:text-primary-dark"
              >
                <LogIn size={16} />
                Se connecter
              </Link>
              <Link
                href="/register"
                className="flex items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-extrabold text-white hover:bg-primary-dark"
              >
                Créer un compte
              </Link>
            </div>
          )}
        </div>
      </div>
    </aside>
  );
}
