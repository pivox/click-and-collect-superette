"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Home, Search, ShoppingBasket, ClipboardList } from "lucide-react";
import { cn } from "@/lib/cn";

const ITEMS = [
  { href: "/", label: "Accueil", icon: Home },
  { href: "/stores", label: "Stores", icon: Search },
  { href: "/kadhia", label: "Kadhia", icon: ShoppingBasket },
  { href: "/orders", label: "Commandes", icon: ClipboardList },
] as const;

/**
 * Sticks to the bottom of the phone-shell. Highlights the active tab based
 * on the current pathname.
 */
export function BottomNav() {
  const pathname = usePathname() ?? "/";
  return (
    <nav
      className={cn(
        "fixed inset-x-0 bottom-0 z-10 grid grid-cols-4 gap-1 border-t border-line md:hidden",
        "bg-white/95 backdrop-blur-md px-3 pt-2 pb-3",
      )}
    >
      {ITEMS.map(({ href, label, icon: Icon }) => {
        const active = href === "/" ? pathname === "/" : pathname.startsWith(href);
        return (
          <Link
            key={href}
            href={href}
            aria-current={active ? "page" : undefined}
            className={cn(
              "grid place-items-center gap-1 text-[10px] font-extrabold",
              active ? "text-primary" : "text-muted",
            )}
          >
            <Icon size={20} strokeWidth={active ? 2.5 : 2} />
            <span>{label}</span>
          </Link>
        );
      })}
    </nav>
  );
}
