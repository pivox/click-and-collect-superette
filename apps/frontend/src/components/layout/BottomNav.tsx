"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { Home, Search, ShoppingBasket, ClipboardList, Bell, LogIn, UserCircle } from "lucide-react";
import { cn } from "@/lib/cn";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { useClientNotifications } from "@/lib/notifications/ClientNotificationsContext";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";

const NAV_ITEMS = [
  { href: "/", labelKey: "client.nav.home", icon: Home },
  { href: "/stores", labelKey: "client.nav.stores", icon: Search },
  { href: "/kadhia", labelKey: "client.nav.kadhia", icon: ShoppingBasket },
  { href: "/orders", labelKey: "client.nav.orders", icon: ClipboardList },
] as const;

function getNotificationsAriaLabel(count: number, t: (key: string) => string): string {
  if (count === 1) {
    return t("client.nav.notificationsSingular");
  }
  const template = t("client.nav.notificationsPlural");
  return template.replace("{count}", String(count));
}

export function BottomNav() {
  const pathname = usePathname() ?? "/";
  const { user } = useClientAuth();
  const { unreadCount } = useClientNotifications();
  const { t } = useClientLocale();

  const ProfileIcon = user ? UserCircle : LogIn;
  const profileLabel = user ? t("client.nav.profile") : t("client.nav.login");
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
      {NAV_ITEMS.map(({ href, labelKey, icon: Icon }) => {
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
            <span>{t(labelKey)}</span>
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
          <span
            aria-label={unreadCount > 0 ? getNotificationsAriaLabel(unreadCount, t) : ''}
            aria-live="polite"
            aria-atomic="true"
            aria-hidden={unreadCount === 0}
            className={`absolute -right-2 -top-1.5 flex min-w-[16px] items-center justify-center rounded-full bg-red-500 px-1 text-[10px] font-black text-white leading-none py-0.5 ${unreadCount === 0 ? 'hidden' : ''}`}
          >
            {badgeText}
          </span>
        </span>
        <span>{t("client.nav.notifs")}</span>
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
