"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import {
  Home,
  Search,
  ShoppingBasket,
  ClipboardList,
} from "lucide-react";
import { cn } from "@/lib/cn";

const NAV = [
  { href: "/",        label: "Accueil",    icon: Home },
  { href: "/stores",  label: "Supérettes", icon: Search },
  { href: "/kadhia",  label: "Ma Kadhia",  icon: ShoppingBasket },
  { href: "/orders",  label: "Commandes",  icon: ClipboardList },
] as const;

/**
 * Desktop sidebar matching user-web-flow.html structure. The 280px-wide
 * left rail with a brand block, nav, and a featured shop card.
 */
export function DesktopShell({
  children,
  featuredShopName,
  featuredShopHours,
}: {
  children: React.ReactNode;
  featuredShopName?: string;
  featuredShopHours?: string;
}) {
  const pathname = usePathname() ?? "/";
  return (
    <div className="min-h-screen grid grid-cols-1 md:grid-cols-[280px_1fr]">
      <aside className="sticky top-0 hidden h-screen overflow-y-auto border-r border-line bg-white p-6 md:block">
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
                  "flex items-center gap-3 rounded-md px-4 py-3 text-sm font-extrabold transition-colors",
                  active
                    ? "bg-soft text-primary-dark"
                    : "text-muted hover:bg-soft hover:text-primary-dark",
                )}
              >
                <Icon size={18} />
                {label}
              </Link>
            );
          })}
        </nav>
        {featuredShopName && (
          <div className="mt-7 rounded-lg bg-hero-emerald p-4 text-white">
            <strong className="block text-sm">{featuredShopName}</strong>
            {featuredShopHours && (
              <span className="mt-1.5 block text-xs text-white/80">
                {featuredShopHours}
              </span>
            )}
          </div>
        )}
      </aside>
      <main className="p-7">{children}</main>
    </div>
  );
}
