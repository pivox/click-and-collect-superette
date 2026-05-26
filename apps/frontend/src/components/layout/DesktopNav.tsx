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
  { href: "/stores",  label: "Stores",     icon: Search },
  { href: "/kadhia",  label: "Ma Kadhia",  icon: ShoppingBasket },
  { href: "/orders",  label: "Commandes",  icon: ClipboardList },
] as const;

/**
 * Desktop left sidebar — visible only on md+ screens.
 * Renders independently from page {children} to avoid double-mounting.
 */
export function DesktopNav() {
  const pathname = usePathname() ?? "/";
  return (
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
    </aside>
  );
}
