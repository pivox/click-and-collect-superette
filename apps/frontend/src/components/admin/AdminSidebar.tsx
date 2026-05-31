'use client';
import Link from 'next/link';
import { usePathname } from 'next/navigation';
import {
  ClipboardList,
  LayoutDashboard,
  Package,
  Store,
  Users,
  type LucideIcon,
} from 'lucide-react';
import { cn } from '@/lib/cn';

type SubItem = { href: string; label: string };
type NavItem = { href: string; label: string; icon: LucideIcon; children?: SubItem[] };

interface AdminSidebarProps {
  className?: string;
  onNavigate?: () => void;
}

const NAV_ITEMS: NavItem[] = [
  { href: '/admin/dashboard', label: 'Tableau de bord', icon: LayoutDashboard },
  { href: '/admin/marchands', label: 'Marchands', icon: Users },
  { href: '/admin/superettes', label: 'Supérettes', icon: Store },
  {
    href: '/admin/referentiel/produits',
    label: 'Référentiel produits',
    icon: Package,
    children: [
      { href: '/admin/referentiel/categories', label: 'Catégories' },
      { href: '/admin/referentiel/marques', label: 'Marques' },
      { href: '/admin/referentiel/produits', label: 'Produits' },
      { href: '/admin/referentiel/propositions', label: 'Propositions' },
    ],
  },
  { href: '/admin/audit', label: 'Audit logs', icon: ClipboardList },
];

export function AdminSidebar({ className, onNavigate }: AdminSidebarProps) {
  const pathname = usePathname();

  return (
    <nav
      aria-label="Navigation admin"
      className={cn('flex w-60 shrink-0 flex-col bg-[#1a1f1b] text-white/70', className)}
    >
      <div className="px-5 py-6">
        <span className="text-xs font-extrabold uppercase tracking-widest text-primary">
          Kadhia Admin
        </span>
      </div>
      <ul className="flex-1 space-y-0.5 px-3 pb-6">
        {NAV_ITEMS.map((item) => {
          const Icon = item.icon;
          const isActive = item.children
            ? pathname.startsWith('/admin/referentiel')
            : pathname === item.href || pathname.startsWith(item.href + '/');
          const isExpanded = !!item.children && pathname.startsWith('/admin/referentiel');
          return (
            <li key={item.href}>
              <Link
                href={item.href}
                onClick={onNavigate}
                className={cn(
                  'flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors',
                  isActive
                    ? 'bg-white/10 font-semibold text-white'
                    : 'hover:bg-white/5 hover:text-white',
                  )}
                >
                <Icon className="h-4 w-4 shrink-0" aria-hidden="true" />
                <span className="min-w-0">{item.label}</span>
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
                          onClick={onNavigate}
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
