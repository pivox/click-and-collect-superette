'use client';

import Link from 'next/link';
import { Bell, Check, RefreshCw } from 'lucide-react';
import { useCallback, useEffect, useMemo, useState } from 'react';
import { Button } from '@/components/ui/Button';
import { Card } from '@/components/ui/Card';
import { cn } from '@/lib/cn';
import {
  listMerchantNotifications,
  markAllMerchantNotificationsRead,
  markMerchantNotificationRead,
} from '@/lib/services/merchant-notifications.service';
import type { MerchantNotificationItem } from '@/lib/types/merchant.types';

type NotificationFilter = 'all' | 'unread';

const PAGE_SIZE = 20;
const LOAD_ERROR = "Les notifications n'ont pas pu être chargées. Réessaie dans un instant.";
const MUTATION_ERROR =
  "La notification n'a pas pu être mise à jour. Réessaie dans un instant.";

function dispatchNotificationRefresh(): void {
  window.dispatchEvent(new Event('merchant-notifications:refresh'));
}

function formatNotificationDate(iso: string): string {
  try {
    return new Intl.DateTimeFormat('fr-FR', {
      day: '2-digit',
      month: '2-digit',
      hour: '2-digit',
      minute: '2-digit',
    }).format(new Date(iso));
  } catch {
    return iso;
  }
}

export default function MerchantNotificationsPage() {
  const [items, setItems] = useState<MerchantNotificationItem[]>([]);
  const [total, setTotal] = useState(0);
  const [page, setPage] = useState(1);
  const [filter, setFilter] = useState<NotificationFilter>('all');
  const [isLoading, setIsLoading] = useState(true);
  const [isMutating, setIsMutating] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [mutationError, setMutationError] = useState<string | null>(null);

  const unreadOnly = filter === 'unread';
  const hasNextPage = page * PAGE_SIZE < total;
  const hasPreviousPage = page > 1;
  const hasUnreadOnPage = useMemo(() => items.some((item) => !item.is_read), [items]);
  const showMarkAllRead = hasUnreadOnPage || (unreadOnly && total > 0);

  const loadNotifications = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listMerchantNotifications({
        page,
        ...(unreadOnly ? { unread: true } : {}),
      });
      setItems(data.items);
      setTotal(data.total);
    } catch {
      setError(LOAD_ERROR);
    } finally {
      setIsLoading(false);
    }
  }, [page, unreadOnly]);

  useEffect(() => {
    void loadNotifications();
  }, [loadNotifications]);

  const changeFilter = (nextFilter: NotificationFilter) => {
    setFilter(nextFilter);
    setPage(1);
  };

  const refresh = async () => {
    await loadNotifications();
    dispatchNotificationRefresh();
  };

  const markOneRead = async (notificationId: string) => {
    setIsMutating(true);
    setMutationError(null);
    try {
      await markMerchantNotificationRead(notificationId);
      await loadNotifications();
      dispatchNotificationRefresh();
    } catch {
      setMutationError(MUTATION_ERROR);
    } finally {
      setIsMutating(false);
    }
  };

  const markAllRead = async () => {
    setIsMutating(true);
    setMutationError(null);
    try {
      await markAllMerchantNotificationsRead();
      await loadNotifications();
      dispatchNotificationRefresh();
    } catch {
      setMutationError(MUTATION_ERROR);
    } finally {
      setIsMutating(false);
    }
  };

  return (
    <section className="mx-auto flex w-full max-w-5xl flex-col gap-5">
      <div className="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p className="text-sm font-bold text-primary">Supérette</p>
          <h1 className="text-2xl font-black text-ink">Notifications</h1>
        </div>
        <div className="flex flex-wrap gap-2">
          {showMarkAllRead && (
            <Button
              type="button"
              variant="ghost"
              size="md"
              disabled={isMutating}
              onClick={markAllRead}
            >
              <Check className="h-4 w-4" aria-hidden="true" />
              Tout marquer comme lu
            </Button>
          )}
          <Button type="button" variant="ghost" size="md" disabled={isLoading} onClick={refresh}>
            <RefreshCw className="h-4 w-4" aria-hidden="true" />
            Actualiser
          </Button>
        </div>
      </div>

      <div className="flex gap-2" role="group" aria-label="Filtres notifications">
        <button
          type="button"
          className={cn(
            'rounded-md px-4 py-2 text-sm font-black',
            filter === 'all' ? 'bg-primary text-white' : 'bg-soft text-ink',
          )}
          onClick={() => changeFilter('all')}
        >
          Toutes
        </button>
        <button
          type="button"
          className={cn(
            'rounded-md px-4 py-2 text-sm font-black',
            filter === 'unread' ? 'bg-primary text-white' : 'bg-soft text-ink',
          )}
          onClick={() => changeFilter('unread')}
        >
          Non lues
        </button>
      </div>

      {mutationError && (
        <div className="rounded-md border border-danger/30 bg-danger/10 px-4 py-3 text-sm font-semibold text-danger">
          {mutationError}
        </div>
      )}

      {isLoading && (
        <Card className="text-sm font-semibold text-muted">Chargement des notifications...</Card>
      )}

      {!isLoading && error && (
        <Card className="flex flex-col gap-3">
          <p className="text-sm font-semibold text-danger">{error}</p>
          <Button
            type="button"
            variant="ghost"
            size="md"
            className="self-start"
            onClick={loadNotifications}
          >
            Réessayer
          </Button>
        </Card>
      )}

      {!isLoading && !error && items.length === 0 && (
        <Card className="flex flex-col items-center gap-3 py-10 text-center">
          <Bell className="h-8 w-8 text-muted" aria-hidden="true" />
          <p className="font-black text-ink">
            {unreadOnly ? 'Aucune notification non lue.' : 'Aucune notification pour le moment.'}
          </p>
        </Card>
      )}

      {!isLoading && !error && items.length > 0 && (
        <div className="space-y-3">
          {items.map((notification) => (
            <Card
              key={notification.id}
              as="article"
              className={cn(
                'flex flex-col gap-3',
                !notification.is_read && 'border-primary/40 bg-primary/5',
              )}
            >
              <div className="flex flex-col gap-2 md:flex-row md:items-start md:justify-between">
                <div className="min-w-0">
                  <div className="flex flex-wrap items-center gap-2">
                    <h2 className="text-base font-black text-ink">{notification.title_fr}</h2>
                    {!notification.is_read && (
                      <span className="rounded-full bg-secondary px-2 py-0.5 text-xs font-black text-[#332500]">
                        Non lue
                      </span>
                    )}
                  </div>
                  <p className="mt-1 text-sm leading-6 text-muted">{notification.body_fr}</p>
                </div>
                <time
                  className="shrink-0 text-xs font-bold text-muted"
                  dateTime={notification.created_at}
                >
                  {formatNotificationDate(notification.created_at)}
                </time>
              </div>
              <div className="flex flex-wrap gap-2">
                {notification.order_id && (
                  <Link
                    href={`/merchant/commandes/${notification.order_id}`}
                    className="inline-flex min-h-[44px] items-center justify-center rounded-md border border-line bg-white px-4 text-sm font-black text-ink hover:bg-soft"
                  >
                    Voir la commande
                  </Link>
                )}
                {!notification.is_read && (
                  <Button
                    type="button"
                    variant="ghost"
                    size="md"
                    disabled={isMutating}
                    onClick={() => markOneRead(notification.id)}
                  >
                    Marquer comme lu
                  </Button>
                )}
              </div>
            </Card>
          ))}
        </div>
      )}

      {!isLoading && !error && total > PAGE_SIZE && (
        <div className="flex items-center justify-between gap-3">
          <Button
            type="button"
            variant="ghost"
            size="md"
            disabled={!hasPreviousPage || isLoading}
            onClick={() => setPage((current) => Math.max(1, current - 1))}
          >
            Page précédente
          </Button>
          <span className="text-sm font-bold text-muted">Page {page}</span>
          <Button
            type="button"
            variant="ghost"
            size="md"
            disabled={!hasNextPage || isLoading}
            onClick={() => setPage((current) => current + 1)}
          >
            Page suivante
          </Button>
        </div>
      )}
    </section>
  );
}
