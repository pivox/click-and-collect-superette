"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Home, Search, ShoppingBasket, ClipboardList, Bell, LogIn, UserCircle } from "lucide-react";
import { cn } from "@/lib/cn";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { useClientNotifications } from "@/lib/notifications/ClientNotificationsContext";

const NAV_ITEMS = [
  { href: "/", label: "Accueil", icon: Home },
  { href: "/stores", label: "Supérettes", icon: Search },
  { href: "/kadhia", label: "Kadhia", icon: ShoppingBasket },
  { href: "/orders", label: "Commandes", icon: ClipboardList },
] as const;

export function BottomNav() {
  const pathname = usePathname() ?? "/";
  const { user } = useClientAuth();
  const { unreadCount } = useClientNotifications();

  const ProfileIcon = user ? UserCircle : LogIn;
  const profileLabel = user ? "Profil" : "Connexion";
  const profileHref = user ? "/profile" : "/login";
  const profileActive = pathname.startsWith("/profile") || (!user && pathname.startsWith("/login"));
  const notifActive = pathname.startsWith("/notifications");
  const badgeText = unreadCount > 99 ? "99+" : String(unreadCount);

  return (
    <nav
      className={cn(
        "fixed inset-x-0 bottom-0 z-10 grid grid-cols-6 gap-1 border-t border-line md:hidden",
        "bg-white/95 backdrop-blur-md px-3 pt-2 pb-3",
      )}
    >
      {NAV_ITEMS.map(({ href, label, icon: Icon }) => {
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

      {/* Notifications */}
      <Link
        href="/notifications"
        aria-current={notifActive ? "page" : undefined}
        className={cn(
          "relative grid place-items-center gap-1 text-[10px] font-extrabold",
          notifActive ? "text-primary" : "text-muted",
        )}
      >
        <span className="relative">
          <Bell size={20} strokeWidth={notifActive ? 2.5 : 2} />
          {unreadCount > 0 && (
            <span
              aria-label={`${unreadCount} notification${unreadCount > 1 ? "s" : ""} non lue${unreadCount > 1 ? "s" : ""}`}
              className="absolute -right-2 -top-1.5 flex min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[9px] font-black text-white leading-none py-0.5"
            >
              {badgeText}
            </span>
          )}
        </span>
        <span>Notifs</span>
      </Link>

      {/* Profile */}
      <Link
        href={profileHref}
        aria-current={profileActive ? "page" : undefined}
        className={cn(
          "grid place-items-center gap-1 text-[10px] font-extrabold",
          profileActive ? "text-primary" : "text-muted",
        )}
      >
        <ProfileIcon size={20} strokeWidth={2} />
        <span>{profileLabel}</span>
      </Link>
    </nav>
  );
}
