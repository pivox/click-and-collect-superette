'use client';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import { cn } from '@/lib/cn';

type SubItem = { href: string; label: string };
type NavItem = { href: string; label: string; icon: string; children?: SubItem[] };

const NAV_ITEMS: NavItem[] = [
  { href: '/admin/dashboard', label: 'Tableau de bord', icon: '▦' },
  { href: '/admin/marchands', label: 'Marchands', icon: '👤' },
  { href: '/admin/superettes', label: 'Supérettes', icon: '🏪' },
  {
    href: '/admin/referentiel/produits',
    label: 'Référentiel produits',
    icon: '📦',
    children: [
      { href: '/admin/referentiel/categories', label: 'Catégories' },
      { href: '/admin/referentiel/marques', label: 'Marques' },
      { href: '/admin/referentiel/produits', label: 'Produits' },
      { href: '/admin/referentiel/propositions', label: 'Propositions' },
    ],
  },
  { href: '/admin/audit', label: 'Audit logs', icon: '📋' },
];

export function AdminSidebar() {
  const pathname = usePathname();

  return (
    <nav className="flex w-60 shrink-0 flex-col bg-[#1a1f1b] text-white/70">
      <div className="px-5 py-6">
        <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
          Kadhia Admin
        </span>
      </div>
      <ul className="flex-1 space-y-0.5 px-3 pb-6">
        {NAV_ITEMS.map((item) => {
          const isActive = item.children
            ? pathname.startsWith('/admin/referentiel')
            : pathname === item.href || pathname.startsWith(item.href + '/');
          const isExpanded = !!item.children && pathname.startsWith('/admin/referentiel');
          return (
            <li key={item.href}>
              <Link
                href={item.href}
                className={cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
                  isActive
                    ? 'bg-white/10 font-semibold text-white'
                    : 'hover:bg-white/5 hover:text-white',
                )}
              >
                <span className="text-base leading-none">{item.icon}</span>
                {item.label}
              </Link>
              {isExpanded && item.children && (
                <ul className="mt-0.5 space-y-0.5 pl-9">
                  {item.children.map((child) => {
                    const isChildActive =
                      pathname === child.href || pathname.startsWith(child.href + '/');
                    return (
                      <li key={child.href}>
                        <Link
                          href={child.href}
                          className={cn(
                            'block rounded-lg px-3 py-2 text-xs transition-colors',
                            isChildActive
                              ? 'bg-white/10 font-semibold text-white'
                              : 'hover:bg-white/5 hover:text-white',
                          )}
                        >
                          {child.label}
                        </Link>
                      </li>
                    );
                  })}
                </ul>
              )}
            </li>
          );
        })}
      </ul>
    </nav>
  );
}
