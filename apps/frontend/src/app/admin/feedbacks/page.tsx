'use client';

import Link from 'next/link';
import { useCallback, useEffect, useRef, useState } from 'react';
import { CheckCircle2, Eye, EyeOff, RotateCcw, Settings } from 'lucide-react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { Button } from '@/components/ui/Button';
import {
  getAdminFeedback,
  listAdminFeedbacks,
  markAdminFeedbackRead,
  markAdminFeedbackUnread,
  reopenAdminFeedback,
  resolveAdminFeedback,
} from '@/lib/services/admin/feedbacks.service';
import { listStores } from '@/lib/services/admin/stores.service';
import type {
  FeedbackAppArea,
  FeedbackEntry,
  FeedbackStatus,
  FeedbackType,
  FeedbackUserRole,
} from '@/lib/types/admin/feedbacks.types';
import type { Store } from '@/lib/types/admin/stores.types';

const PAGE_SIZE = 20;

const STATUS_LABELS: Record<FeedbackStatus, string> = {
  unread: 'Non lu',
  read: 'Lu',
  resolved: 'Résolu',
};

const TYPE_LABELS: Record<FeedbackType, string> = {
  bug: 'Bug',
  idea: 'Idée',
  confusing: 'Incompréhension',
  other: 'Autre',
};

const AREA_LABELS: Record<FeedbackAppArea, string> = {
  client: 'Client',
  merchant: 'Marchand',
  admin: 'Admin',
};

const ROLE_LABELS: Record<FeedbackUserRole, string> = AREA_LABELS;

function statusClass(status: FeedbackStatus): string {
  if (status === 'resolved') return 'bg-green-100 text-green-700';
  if (status === 'read') return 'bg-[var(--status-wait-bg)] text-[var(--status-wait)]';

  return 'bg-status-cancel-bg text-status-cancel';
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) return '-';

  return new Date(value).toLocaleString('fr-TN');
}

export default function AdminFeedbacksPage() {
  const [items, setItems] = useState<FeedbackEntry[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [detail, setDetail] = useState<FeedbackEntry | null>(null);
  const [adminNote, setAdminNote] = useState('');
  const [statusFilter, setStatusFilter] = useState<FeedbackStatus | ''>('');
  const [roleFilter, setRoleFilter] = useState<FeedbackUserRole | ''>('');
  const [typeFilter, setTypeFilter] = useState<FeedbackType | ''>('');
  const [areaFilter, setAreaFilter] = useState<FeedbackAppArea | ''>('');
  const [shopFilter, setShopFilter] = useState('');
  const [createdFromFilter, setCreatedFromFilter] = useState('');
  const [createdToFilter, setCreatedToFilter] = useState('');
  const [contactFilter, setContactFilter] = useState<boolean | ''>('');
  const [stores, setStores] = useState<Store[]>([]);
  const [storesError, setStoresError] = useState<string | null>(null);
  const [actionSuccess, setActionSuccess] = useState<string | null>(null);
  const listRequestSeq = useRef(0);
  const detailRequestSeq = useRef(0);

  const load = useCallback(async () => {
    const requestSeq = listRequestSeq.current + 1;
    listRequestSeq.current = requestSeq;
    setIsLoading(true);
    setError(null);
    try {
      const data = await listAdminFeedbacks({
        page,
        limit: PAGE_SIZE,
        status: statusFilter,
        userRole: roleFilter,
        type: typeFilter,
        appArea: areaFilter,
        shop: shopFilter.trim(),
        contactConsent: contactFilter,
        createdFrom: createdFromFilter,
        createdTo: createdToFilter,
      });
      if (listRequestSeq.current !== requestSeq) return;
      setItems(data.items);
      setTotal(data.total);
    } catch (err) {
      if (listRequestSeq.current !== requestSeq) return;
      console.error('[admin-feedbacks] list failed', err);
      setError('Impossible de charger les feedbacks.');
    } finally {
      if (listRequestSeq.current === requestSeq) {
        setIsLoading(false);
      }
    }
  }, [
    areaFilter,
    contactFilter,
    createdFromFilter,
    createdToFilter,
    page,
    roleFilter,
    shopFilter,
    statusFilter,
    typeFilter,
  ]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    let isMounted = true;

    listStores({ page: 1, limit: 50, isActive: true })
      .then((data) => {
        if (!isMounted) return;
        setStores(data.items);
        setStoresError(null);
      })
      .catch((err: unknown) => {
        if (!isMounted) return;
        console.error('[admin-feedbacks] stores load failed', err);
        setStores([]);
        setStoresError('Impossible de charger la liste des supérettes. Filtre par ID disponible.');
      });

    return () => {
      isMounted = false;
    };
  }, []);

  const resetPage = () => {
    setActionSuccess(null);
    setPage(1);
  };

  const openDetail = async (id: string) => {
    const requestSeq = detailRequestSeq.current + 1;
    detailRequestSeq.current = requestSeq;
    setError(null);
    setActionSuccess(null);
    try {
      const data = await getAdminFeedback(id);
      if (detailRequestSeq.current !== requestSeq) return;
      setDetail(data);
      setAdminNote(data.adminNote ?? '');
    } catch (err) {
      if (detailRequestSeq.current !== requestSeq) return;
      console.error('[admin-feedbacks] detail failed', err);
      setError('Impossible de charger le détail feedback.');
    }
  };

  const closeDetail = () => {
    detailRequestSeq.current += 1;
    setDetail(null);
    setAdminNote('');
  };

  const updateDetail = async (next: FeedbackEntry) => {
    setDetail(next);
    setAdminNote(next.adminNote ?? '');
    await load();
  };

  const runAction = async (
    action: () => Promise<FeedbackEntry>,
    successMessage: string,
    errorMessage: string,
  ) => {
    setIsSaving(true);
    setError(null);
    setActionSuccess(null);
    try {
      await updateDetail(await action());
      setActionSuccess(successMessage);
    } catch (err) {
      console.error('[admin-feedbacks] action failed', err);
      setError(errorMessage);
    } finally {
      setIsSaving(false);
    }
  };

  const selectedStoreId = stores.some((store) => store.id === shopFilter) ? shopFilter : '';

  const columns: Column<FeedbackEntry>[] = [
    {
      key: 'type',
      label: 'Retour',
      render: (row) => (
        <div>
          <div className="font-medium">{TYPE_LABELS[row.type]}</div>
          <div className="max-w-sm truncate text-xs text-muted">{row.messageExcerpt ?? row.message ?? '-'}</div>
        </div>
      ),
    },
    {
      key: 'status',
      label: 'Statut',
      render: (row) => (
        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${statusClass(row.status)}`}>
          {STATUS_LABELS[row.status]}
        </span>
      ),
    },
    {
      key: 'userRole',
      label: 'Rôle',
      render: (row) => ROLE_LABELS[row.userRole ?? 'client'],
    },
    {
      key: 'appArea',
      label: 'Zone',
      render: (row) => AREA_LABELS[row.appArea],
    },
    {
      key: 'user',
      label: 'Utilisateur',
      render: (row) => row.user.email ?? row.user.id,
    },
    {
      key: 'shop',
      label: 'Supérette',
      render: (row) => row.shop?.name ?? '-',
    },
    {
      key: 'createdAt',
      label: 'Date',
      render: (row) => formatDateTime(row.createdAt),
    },
    {
      key: 'actions',
      label: '',
      render: (row) => (
        <button
          type="button"
          onClick={() => void openDetail(row.id)}
          className="text-xs font-semibold text-primary hover:underline"
        >
          Voir détail
        </button>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-h1 font-black">Feedbacks</h1>
          <p className="mt-1 text-sm text-muted">Retours client, marchand et admin envoyés depuis les pages Kadhia.</p>
        </div>
        <div className="flex flex-wrap gap-2">
          <Link
            href="/admin/feedbacks/settings"
            className="inline-flex min-h-[44px] items-center justify-center gap-2 rounded-md border border-line bg-white px-4 text-sm font-black text-ink hover:bg-soft"
          >
            <Settings className="h-4 w-4" aria-hidden="true" />
            Paramètres
          </Link>
          <Button variant="ghost" size="md" onClick={() => void load()}>
            {error ? 'Réessayer' : 'Actualiser'}
          </Button>
        </div>
      </div>

      <div className="mb-4 grid gap-3 md:grid-cols-3 xl:grid-cols-8">
        <select
          aria-label="Filtrer par statut"
          value={statusFilter}
          onChange={(event) => {
            resetPage();
            setStatusFilter(event.target.value as FeedbackStatus | '');
          }}
          className="rounded-md border border-line bg-card px-3 py-2 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        >
          <option value="">Tous statuts</option>
          <option value="unread">Non lus</option>
          <option value="read">Lus</option>
          <option value="resolved">Résolus</option>
        </select>
        <select
          aria-label="Filtrer par rôle"
          value={roleFilter}
          onChange={(event) => {
            resetPage();
            setRoleFilter(event.target.value as FeedbackUserRole | '');
          }}
          className="rounded-md border border-line bg-card px-3 py-2 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        >
          <option value="">Tous rôles</option>
          <option value="client">Client</option>
          <option value="merchant">Marchand</option>
          <option value="admin">Admin</option>
        </select>
        <select
          aria-label="Filtrer par type"
          value={typeFilter}
          onChange={(event) => {
            resetPage();
            setTypeFilter(event.target.value as FeedbackType | '');
          }}
          className="rounded-md border border-line bg-card px-3 py-2 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        >
          <option value="">Tous types</option>
          <option value="bug">Bug</option>
          <option value="idea">Idée</option>
          <option value="confusing">Incompréhension</option>
          <option value="other">Autre</option>
        </select>
        <select
          aria-label="Filtrer par zone"
          value={areaFilter}
          onChange={(event) => {
            resetPage();
            setAreaFilter(event.target.value as FeedbackAppArea | '');
          }}
          className="rounded-md border border-line bg-card px-3 py-2 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        >
          <option value="">Toutes zones</option>
          <option value="client">Client</option>
          <option value="merchant">Marchand</option>
          <option value="admin">Admin</option>
        </select>
        <label className="sr-only" htmlFor="feedback-created-from">Filtrer à partir du</label>
        <input
          id="feedback-created-from"
          aria-label="Filtrer à partir du"
          type="date"
          value={createdFromFilter}
          onChange={(event) => {
            resetPage();
            setCreatedFromFilter(event.target.value);
          }}
          className="rounded-md border border-line px-3 py-2 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
        <label className="sr-only" htmlFor="feedback-created-to">Filtrer jusqu&apos;au</label>
        <input
          id="feedback-created-to"
          aria-label="Filtrer jusqu'au"
          type="date"
          value={createdToFilter}
          onChange={(event) => {
            resetPage();
            setCreatedToFilter(event.target.value);
          }}
          className="rounded-md border border-line px-3 py-2 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
        <div className="grid gap-2 xl:col-span-2 xl:grid-cols-2">
          {!storesError && (
            <select
              aria-label="Choisir une supérette"
              value={selectedStoreId}
              onChange={(event) => {
                resetPage();
                setShopFilter(event.target.value);
              }}
              className="rounded-md border border-line bg-card px-3 py-2 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
            >
              <option value="">Supérette connue</option>
              {stores.map((store) => (
                <option key={store.id} value={store.id}>
                  {store.name}
                </option>
              ))}
            </select>
          )}
          <input
            aria-label="Filtrer par supérette"
            type="text"
            placeholder={storesError ? 'ID supérette' : 'ID supérette manuel'}
            value={shopFilter}
            onChange={(event) => {
              resetPage();
              setShopFilter(event.target.value);
            }}
            className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <select
          aria-label="Filtrer par consentement contact"
          value={contactFilter === '' ? '' : String(contactFilter)}
          onChange={(event) => {
            resetPage();
            setContactFilter(event.target.value === '' ? '' : event.target.value === 'true');
          }}
          className="rounded-md border border-line bg-card px-3 py-2 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        >
          <option value="">Contact indifférent</option>
          <option value="true">Contact accepté</option>
          <option value="false">Contact refusé</option>
        </select>
      </div>

      {storesError && (
        <div className="mb-4 rounded-md border border-[var(--status-wait-bg)] bg-[var(--status-wait-bg)] px-4 py-2 text-sm text-[var(--status-wait)]">
          {storesError}
        </div>
      )}

      {error && (
        <div role="alert" aria-atomic="true" className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}
      {actionSuccess && !detail && (
        <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-800">
          {actionSuccess}
        </div>
      )}

      <AdminTable
        columns={columns}
        data={items}
        isLoading={isLoading}
        emptyMessage="Aucun feedback à afficher."
        pagination={{ page, total, limit: PAGE_SIZE, onPageChange: setPage }}
      />

      {detail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <section
            role="dialog"
            aria-label="Détail feedback"
            className="max-h-[90vh] w-full max-w-3xl overflow-auto rounded-md bg-card p-5 shadow-xl"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-black text-ink">{TYPE_LABELS[detail.type]}</h2>
                <p className="mt-1 text-sm text-muted">{AREA_LABELS[detail.appArea]} · {detail.appSubArea ?? '-'}</p>
              </div>
              <button type="button" className="text-sm font-bold text-muted" onClick={closeDetail}>
                Fermer
              </button>
            </div>

            {actionSuccess && (
              <div className="mt-5 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-800">
                {actionSuccess}
              </div>
            )}

            <section className="mt-5 rounded-md border border-line bg-soft px-3 py-3">
              <h3 className="text-sm font-black text-ink">Message complet</h3>
              <p className="mt-2 whitespace-pre-wrap text-sm text-ink">{detail.message}</p>
            </section>

            <div className="mt-5 flex flex-wrap gap-2">
              {detail.status === 'unread' && (
                <Button variant="ghost" size="md" disabled={isSaving} onClick={() => void runAction(() => markAdminFeedbackRead(detail.id), 'Retour marqué comme lu.', 'Impossible de marquer ce retour comme lu.')}>
                  <Eye className="h-4 w-4" aria-hidden="true" />
                  Lu
                </Button>
              )}
              {detail.status === 'read' && (
                <Button variant="ghost" size="md" disabled={isSaving} onClick={() => void runAction(() => markAdminFeedbackUnread(detail.id), 'Retour marqué comme non lu.', 'Impossible de marquer ce retour comme non lu.')}>
                  <EyeOff className="h-4 w-4" aria-hidden="true" />
                  Non lu
                </Button>
              )}
              {detail.status !== 'resolved' && (
                <Button variant="primary" size="md" disabled={isSaving} onClick={() => void runAction(() => resolveAdminFeedback(detail.id, adminNote.trim()), 'Retour résolu.', 'Impossible de résoudre ce retour.')}>
                  <CheckCircle2 className="h-4 w-4" aria-hidden="true" />
                  Résolu
                </Button>
              )}
              {detail.status === 'resolved' && (
                <Button variant="ghost" size="md" disabled={isSaving} onClick={() => void runAction(() => reopenAdminFeedback(detail.id), 'Retour réouvert.', 'Impossible de réouvrir ce retour.')}>
                  <RotateCcw className="h-4 w-4" aria-hidden="true" />
                  Réouvrir
                </Button>
              )}
            </div>

            <section className="mt-5 rounded-md border border-line bg-soft p-3">
              <h3 className="text-sm font-black text-ink">Note admin</h3>
              <textarea
                aria-label="Note admin"
                value={adminNote}
                onChange={(event) => setAdminNote(event.target.value)}
                className="mt-2 min-h-24 w-full rounded-md border border-line bg-card px-3 py-2 text-sm text-ink"
              />
            </section>

            <section className="mt-5">
              <h3 className="text-sm font-black text-ink">Contexte page et appareil</h3>
              <dl className="mt-3 grid gap-3 md:grid-cols-2">
                <Detail label="Statut" value={STATUS_LABELS[detail.status]} />
                <Detail label="Page" value={detail.pageUrl ?? '-'} />
                <Detail label="Route" value={detail.routeName ?? '-'} />
                <Detail label="Titre page" value={detail.pageTitle ?? '-'} />
                <Detail label="Zone" value={AREA_LABELS[detail.appArea]} />
                <Detail label="Sous-zone" value={detail.appSubArea ?? '-'} />
                <Detail label="Locale" value={detail.locale ?? '-'} />
                <Detail label="Viewport" value={detail.viewportWidth && detail.viewportHeight ? `${detail.viewportWidth} × ${detail.viewportHeight}` : '-'} />
                <Detail label="User agent" value={detail.userAgent ?? '-'} />
              </dl>
            </section>

            <section className="mt-5">
              <h3 className="text-sm font-black text-ink">Acteurs et traitement</h3>
              <dl className="mt-3 grid gap-3 md:grid-cols-2">
                <Detail label="Utilisateur" value={detail.user.email ?? detail.user.id} />
                <Detail label="Rôle utilisateur" value={ROLE_LABELS[detail.userRole ?? 'client']} />
                <Detail label="Supérette" value={detail.shop?.name ?? '-'} />
                <Detail label="Consentement contact" value={detail.contactConsent ? 'Consentement donné' : 'Pas de consentement'} />
                <Detail label="Lu le" value={formatDateTime(detail.readAt)} />
                <Detail label="Résolu le" value={formatDateTime(detail.resolvedAt)} />
                <Detail label="Créé le" value={formatDateTime(detail.createdAt)} />
              </dl>
            </section>
          </section>
        </div>
      )}
    </div>
  );
}

function Detail({ label, value }: { label: string; value: string }) {
  return (
    <div className="rounded-md border border-line bg-soft px-3 py-2">
      <dt className="text-xs font-semibold uppercase text-muted">{label}</dt>
      <dd className="mt-1 break-words text-sm font-bold text-ink">{value}</dd>
    </div>
  );
}
