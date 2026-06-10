'use client';

import Link from 'next/link';
import { usePathname, useRouter } from 'next/navigation';
import {
  Home,
  Search,
  ShoppingBasket,
  ClipboardList,
  Bell,
  LogIn,
  LogOut,
} from 'lucide-react';
import { cn } from '@/lib/cn';
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { useSelectedStore } from '@/lib/store/SelectedStoreContext';
import { useHydrated } from '@/lib/hooks/useHydrated';
import { useClientNotifications } from '@/lib/notifications/ClientNotificationsContext';
import { useClientLocale } from '@/lib/i18n/ClientLocaleContext';

const NAV = [
  { href: '/',               labelKey: 'client.nav.home',          icon: Home },
  { href: '/stores',         labelKey: 'client.nav.stores',        icon: Search },
  { href: '/kadhia',         labelKey: 'client.nav.kadhia',        icon: ShoppingBasket },
  { href: '/orders',         labelKey: 'client.nav.orders',        icon: ClipboardList },
  { href: '/notifications',  labelKey: 'client.nav.notifications', icon: Bell },
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
  const { unreadCount } = useClientNotifications();
  const { t } = useClientLocale();

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
    <aside className="client-theme-sidebar sticky top-0 hidden h-screen overflow-y-auto border-r border-line p-6 md:flex md:flex-col md:justify-between">
      <div>
        <div className="mb-7 flex items-center gap-3">
          <div className="grid h-12 w-12 place-items-center rounded-md bg-primary text-white text-lg font-black">
            K
          </div>
          <div>
            <strong className="block text-base">Kadhia</strong>
            <span className="text-xs text-muted">{t('client.nav.brandTagline')}</span>
          </div>
        </div>
        <nav className="grid gap-2">
          {NAV.map(({ href, labelKey, icon: Icon }) => {
            const active = pathname === href || (href !== '/' && pathname.startsWith(href));
            const isNotifications = href === '/notifications';
            const badge = isNotifications && unreadCount > 0 ? (unreadCount > 99 ? '99+' : String(unreadCount)) : null;
            const badgeLabel = badge ? `${badge === '99+' ? '99 ou plus' : badge} notification${badge !== '1' ? 's' : ''} non lue${badge !== '1' ? 's' : ''}` : '';
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
                {t(labelKey)}
                {badge && (
                  <span
                    aria-label={badgeLabel}
                    aria-live="polite"
                    aria-atomic="true"
                    className="ml-auto flex min-w-5 items-center justify-center rounded-full bg-red-500 px-1.5 py-0.5 text-[11px] font-black text-white"
                  >
                    {badge}
                  </span>
                )}
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
              {t('client.nav.activeStore')}
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
                  {selectedStore ? selectedStore.name : t('client.nav.noStore')}
                </strong>
                <span className="text-[10px] text-primary">{t('client.nav.change')}</span>
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
                  {user.name || t('client.nav.clientFallback')}
                </strong>
                <span className="block truncate text-[11px] text-muted">
                  {user.email}
                </span>
              </div>
              <button
                type="button"
                onClick={handleLogout}
                aria-label={t('client.nav.logout')}
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
                {t('client.nav.signIn')}
              </Link>
              <Link
                href="/register"
                className="flex items-center justify-center rounded-md bg-primary px-4 py-2.5 text-sm font-extrabold text-white hover:bg-primary-dark"
              >
                {t('client.nav.register')}
              </Link>
            </div>
          )}
        </div>
      </div>
    </aside>
  );
}
