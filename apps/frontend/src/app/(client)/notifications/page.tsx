"use client";

import { useCallback, useEffect, useRef, useState } from "react";
import { useRouter } from "next/navigation";
import { Bell } from "lucide-react";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import {
  listClientNotifications,
  markClientNotificationRead,
  markAllClientNotificationsRead,
  type ClientNotificationItem,
} from "@/lib/services/client-notifications.service";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { useClientNotifications } from "@/lib/notifications/ClientNotificationsContext";
import { cn } from "@/lib/cn";

const PAGE_SIZE = 20;

function formatRelativeTime(iso: string): string {
  const diff = Date.now() - new Date(iso).getTime();
  const minutes = Math.floor(diff / 60_000);
  if (minutes < 1) return "à l'instant";
  if (minutes < 60) return `il y a ${minutes} min`;
  const hours = Math.floor(minutes / 60);
  if (hours < 24) return `il y a ${hours}h`;
  const days = Math.floor(hours / 24);
  if (days === 1) return "hier";
  return `il y a ${days}j`;
}

export default function ClientNotificationsPage() {
  const router = useRouter();
  const { user, isLoading: authLoading } = useClientAuth();
  const { refreshUnread } = useClientNotifications();

  const [items, setItems] = useState<ClientNotificationItem[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [mutating, setMutating] = useState(false);
  const latestRequestId = useRef(0);

  useEffect(() => {
    if (!authLoading && !user) {
      router.push("/login?redirect=/notifications");
    }
  }, [authLoading, user, router]);

  const load = useCallback(async () => {
    const requestId = latestRequestId.current + 1;
    latestRequestId.current = requestId;
    setIsLoading(true);
    setError(null);
    try {
      const data = await listClientNotifications({ page });
      if (requestId !== latestRequestId.current) return;
      setItems(data.items);
      setTotal(data.total);
    } catch {
      if (requestId !== latestRequestId.current) return;
      setError("Impossible de charger les notifications. Réessaie dans un instant.");
    } finally {
      if (requestId === latestRequestId.current) setIsLoading(false);
    }
  }, [page]);

  useEffect(() => {
    if (authLoading || !user) return;
    void load();
  }, [authLoading, user, load]);

  const handleNotificationClick = async (notification: ClientNotificationItem) => {
    if (!notification.is_read) {
      try {
        await markClientNotificationRead(notification.id);
        await refreshUnread();
        setItems((prev) =>
          prev.map((n) => (n.id === notification.id ? { ...n, is_read: true } : n)),
        );
      } catch {
        // best-effort — still navigate
      }
    }
    if (notification.order_id) {
      router.push(`/orders/${notification.order_id}`);
    }
  };

  const handleMarkAllRead = async () => {
    setMutating(true);
    try {
      await markAllClientNotificationsRead();
      await refreshUnread();
      setItems((prev) => prev.map((n) => ({ ...n, is_read: true })));
    } catch {
      // best-effort
    } finally {
      setMutating(false);
    }
  };

  if (authLoading || !user) return null;

  const hasUnread = items.some((n) => !n.is_read);
  const hasNextPage = page * PAGE_SIZE < total;
  const hasPrevPage = page > 1;

  return (
    <>
      <TopBar
        title="Notifications"
        subtitle={total > 0 ? `${total} notification${total > 1 ? "s" : ""}` : undefined}
        backHref="/"
        action={
          hasUnread ? (
            <Button variant="ghost" size="md" disabled={mutating} onClick={() => void handleMarkAllRead()}>
              Tout lire
            </Button>
          ) : undefined
        }
      />

      {error && (
        <div role="alert" className="mb-4 rounded-md bg-red-50 px-3 py-2">
          <p className="text-sm text-red-600">{error}</p>
          <button
            type="button"
            onClick={() => void load()}
            className="mt-2 text-sm font-extrabold text-primary underline"
          >
            Réessayer
          </button>
        </div>
      )}

      {isLoading && (
        <div className="grid gap-2.5">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-20 animate-pulse rounded-lg bg-gray-100" />
          ))}
        </div>
      )}

      {!isLoading && !error && items.length === 0 && (
        <Card className="flex flex-col items-center gap-3 py-10 text-center">
          <Bell size={32} className="text-muted" aria-hidden="true" />
          <p className="font-extrabold text-ink">Aucune notification pour l&apos;instant.</p>
        </Card>
      )}

      {!isLoading && !error && items.length > 0 && (
        <div className="grid gap-2.5">
          {items.map((notification) => (
            <button
              key={notification.id}
              type="button"
              onClick={() => void handleNotificationClick(notification)}
              className={cn(
                "w-full rounded-lg border p-4 text-left transition-colors hover:bg-soft",
                notification.is_read
                  ? "border-line bg-card"
                  : "border-primary/30 bg-primary/5",
              )}
            >
              <div className="flex items-start justify-between gap-3">
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-2">
                    {!notification.is_read && (
                      <span className="inline-block h-2 w-2 shrink-0 rounded-full bg-primary" />
                    )}
                    <strong className="text-sm font-extrabold text-ink">
                      {notification.title_fr}
                    </strong>
                  </div>
                  <p className="mt-1 text-xs text-muted leading-relaxed">
                    {notification.body_fr}
                  </p>
                </div>
                <time
                  className="shrink-0 text-xs text-muted"
                  dateTime={notification.created_at}
                >
                  {formatRelativeTime(notification.created_at)}
                </time>
              </div>
              {notification.order_id && (
                <span className="mt-2 block text-xs font-extrabold text-primary">
                  Voir la commande →
                </span>
              )}
            </button>
          ))}
        </div>
      )}

      {!isLoading && !error && total > PAGE_SIZE && (
        <div className="mt-4 flex items-center justify-between gap-3">
          <Button
            variant="ghost"
            size="md"
            disabled={!hasPrevPage || isLoading}
            onClick={() => setPage((p) => Math.max(1, p - 1))}
          >
            Page précédente
          </Button>
          <span className="text-sm font-bold text-muted">Page {page}</span>
          <Button
            variant="ghost"
            size="md"
            disabled={!hasNextPage || isLoading}
            onClick={() => setPage((p) => p + 1)}
          >
            Page suivante
          </Button>
        </div>
      )}
    </>
  );
}
