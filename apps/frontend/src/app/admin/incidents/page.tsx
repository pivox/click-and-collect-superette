'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { Button } from '@/components/ui/Button';
import {
  addAdminIncidentNote,
  closeAdminIncident,
  getAdminIncident,
  listAdminIncidents,
  startAdminIncidentProcessing,
} from '@/lib/services/admin/incidents.service';
import type { Incident, IncidentStatus, IncidentType } from '@/lib/types/admin/incidents.types';

const PAGE_SIZE = 20;

const TYPE_LABELS: Record<IncidentType, string> = {
  customer_absent: 'Client absent',
  merchant_late: 'Marchand en retard',
  missing_product: 'Produit manquant',
  price_error: 'Erreur de prix',
  order_not_prepared: 'Commande non préparée',
  qr_issue: 'Problème QR',
  late_cancellation: 'Annulation tardive',
  other: 'Autre',
};

const STATUS_LABELS: Record<IncidentStatus, string> = {
  open: 'Ouvert',
  in_progress: 'En cours',
  closed: 'Clôturé',
};

function statusClass(status: IncidentStatus): string {
  if (status === 'closed') return 'bg-green-100 text-green-700';
  if (status === 'in_progress') return 'bg-[var(--status-wait-bg)] text-[var(--status-wait)]';

  return 'bg-status-cancel-bg text-status-cancel';
}

function formatDateTime(value: string | null | undefined): string {
  if (!value) return '-';

  return new Date(value).toLocaleString('fr-TN');
}

export default function AdminIncidentsPage() {
  const [items, setItems] = useState<Incident[]>([]);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [merchantFilter, setMerchantFilter] = useState('');
  const [clientFilter, setClientFilter] = useState('');
  const [statusFilter, setStatusFilter] = useState<IncidentStatus | ''>('');
  const [detail, setDetail] = useState<Incident | null>(null);
  const [note, setNote] = useState('');
  const [isDetailLoading, setIsDetailLoading] = useState(false);
  const [isSaving, setIsSaving] = useState(false);
  const detailRequestSeq = useRef(0);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listAdminIncidents({
        page,
        limit: PAGE_SIZE,
        merchant: merchantFilter.trim(),
        client: clientFilter.trim(),
        status: statusFilter,
      });
      setItems(data.items);
      setTotal(data.total);
    } catch (err) {
      console.error('[admin-incidents] list failed', err);
      setError('Impossible de charger les incidents.');
    } finally {
      setIsLoading(false);
    }
  }, [page, merchantFilter, clientFilter, statusFilter]);

  useEffect(() => {
    void load();
  }, [load]);

  useEffect(() => {
    setPage(1);
  }, [merchantFilter, clientFilter, statusFilter]);

  const openDetail = async (id: string) => {
    const requestSeq = detailRequestSeq.current + 1;
    detailRequestSeq.current = requestSeq;
    setIsDetailLoading(true);
    setError(null);
    try {
      const data = await getAdminIncident(id);
      if (detailRequestSeq.current !== requestSeq) return;
      setDetail(data);
      setNote('');
    } catch (err) {
      if (detailRequestSeq.current !== requestSeq) return;
      console.error('[admin-incidents] detail failed', err);
      setError('Impossible de charger le détail incident.');
    } finally {
      if (detailRequestSeq.current === requestSeq) {
        setIsDetailLoading(false);
      }
    }
  };

  const closeDetail = () => {
    detailRequestSeq.current += 1;
    setDetail(null);
    setNote('');
    setIsDetailLoading(false);
  };

  const refreshDetail = async (next: Incident) => {
    setDetail(next);
    await load();
  };

  const submitNote = async () => {
    if (!detail || !note.trim()) return;
    setIsSaving(true);
    setError(null);
    try {
      await refreshDetail(await addAdminIncidentNote(detail.id, note.trim()));
      setNote('');
    } catch (err) {
      console.error('[admin-incidents] add note failed', err);
      setError('Impossible d’ajouter la note interne.');
    } finally {
      setIsSaving(false);
    }
  };

  const startProcessing = async () => {
    if (!detail) return;
    setIsSaving(true);
    try {
      await refreshDetail(await startAdminIncidentProcessing(detail.id));
    } catch (err) {
      console.error('[admin-incidents] start failed', err);
      setError('Impossible de prendre en charge cet incident.');
    } finally {
      setIsSaving(false);
    }
  };

  const closeIncident = async () => {
    if (!detail) return;
    setIsSaving(true);
    try {
      await refreshDetail(await closeAdminIncident(detail.id));
    } catch (err) {
      console.error('[admin-incidents] close failed', err);
      setError('Impossible de clôturer cet incident.');
    } finally {
      setIsSaving(false);
    }
  };

  const columns: Column<Incident>[] = [
    {
      key: 'type',
      label: 'Incident',
      render: (row) => (
        <div>
          <div className="font-medium">{TYPE_LABELS[row.type]}</div>
          <div className="text-xs text-muted">{row.order_id}</div>
        </div>
      ),
    },
    {
      key: 'merchant_email',
      label: 'Marchand',
      render: (row) => (
        <div>
          <div className="font-medium">{row.merchant_email}</div>
          <div className="text-xs text-muted">{row.merchant_id}</div>
        </div>
      ),
    },
    {
      key: 'customer_email',
      label: 'Client',
      render: (row) => row.customer_email,
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
      key: 'created_at',
      label: 'Créé le',
      render: (row) => formatDateTime(row.created_at),
    },
    {
      key: 'actions',
      label: '',
      render: (row) => (
        <button
          type="button"
          onClick={() => void openDetail(row.id)}
          disabled={isDetailLoading}
          aria-label="Voir le détail incident"
          className="text-xs font-semibold text-primary hover:underline disabled:text-muted"
        >
          {isDetailLoading ? 'Chargement…' : 'Voir détail'}
        </button>
      ),
    },
  ];

  return (
    <div>
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-h1 font-black">Incidents support</h1>
          <p className="mt-1 text-sm text-muted">Suivi des problèmes terrain liés aux commandes.</p>
        </div>
        <Button variant="ghost" size="md" onClick={() => void load()}>
          {error ? 'Réessayer' : 'Actualiser'}
        </Button>
      </div>

      <div className="mb-4 grid gap-3 md:grid-cols-[1fr_1fr_180px]">
        <input
          type="text"
          placeholder="ID marchand"
          value={merchantFilter}
          onChange={(event) => setMerchantFilter(event.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
        <input
          type="text"
          placeholder="ID client"
          value={clientFilter}
          onChange={(event) => setClientFilter(event.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
        <select
          aria-label="Filtrer par statut"
          value={statusFilter}
          onChange={(event) => setStatusFilter(event.target.value as IncidentStatus | '')}
          className="rounded-md border border-line bg-card px-3 py-2 text-sm text-ink outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        >
          <option value="">Tous</option>
          <option value="open">Ouverts</option>
          <option value="in_progress">En cours</option>
          <option value="closed">Clôturés</option>
        </select>
      </div>

      {error && (
        <div className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}

      <AdminTable
        columns={columns}
        data={items}
        isLoading={isLoading}
        emptyMessage="Aucun incident à afficher."
        pagination={{ page, total, limit: PAGE_SIZE, onPageChange: setPage }}
      />

      {detail && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
          <section
            role="dialog"
            aria-label="Détail incident"
            className="max-h-[90vh] w-full max-w-2xl overflow-auto rounded-md bg-card p-5 shadow-xl"
          >
            <div className="flex items-start justify-between gap-3">
              <div>
                <h2 className="text-lg font-black text-ink">{TYPE_LABELS[detail.type]}</h2>
                <p className="mt-1 text-sm text-muted">{detail.order_id}</p>
              </div>
              <button type="button" className="text-sm font-bold text-muted" onClick={closeDetail}>
                Fermer
              </button>
            </div>

            <dl className="mt-5 grid gap-3 sm:grid-cols-2">
              <Detail label="Statut" value={STATUS_LABELS[detail.status]} />
              <Detail label="Marchand" value={detail.merchant_email} />
              <Detail label="Client" value={detail.customer_email} />
              <Detail label="Signalé le" value={formatDateTime(detail.occurred_at)} />
            </dl>

            <div className="mt-5 flex flex-wrap gap-2">
              {detail.status === 'open' && (
                <Button variant="primary" size="md" onClick={() => void startProcessing()} disabled={isSaving}>
                  Prendre en charge
                </Button>
              )}
              {detail.status === 'in_progress' && (
                <Button variant="primary" size="md" onClick={() => void closeIncident()} disabled={isSaving}>
                  Clôturer
                </Button>
              )}
            </div>

            <section className="mt-5 rounded-md border border-line bg-soft p-3">
              <h3 className="text-sm font-black text-ink">Note interne</h3>
              <textarea
                className="mt-2 min-h-24 w-full rounded-md border border-line bg-card px-3 py-2 text-sm text-ink"
                value={note}
                onChange={(event) => setNote(event.target.value)}
              />
              <Button className="mt-2" variant="primary" size="md" onClick={() => void submitNote()} disabled={isSaving || !note.trim()}>
                Ajouter la note
              </Button>
            </section>

            <section className="mt-5 grid gap-3 md:grid-cols-2">
              <div>
                <h3 className="text-sm font-black text-ink">Notes</h3>
                <div className="mt-2 space-y-2">
                  {(detail.notes ?? []).length === 0 ? (
                    <p className="text-sm text-muted">Aucune note interne.</p>
                  ) : (
                    detail.notes?.map((item) => (
                      <div key={item.id} className="rounded-md border border-line bg-soft px-3 py-2">
                        <p className="text-sm text-ink">{item.note}</p>
                        <p className="mt-1 text-xs text-muted">{item.created_by_admin_email}</p>
                      </div>
                    ))
                  )}
                </div>
              </div>
              <div>
                <h3 className="text-sm font-black text-ink">Historique</h3>
                <div className="mt-2 space-y-2">
                  {(detail.history ?? []).map((entry) => (
                    <div key={`${entry.event}-${entry.occurred_at}`} className="rounded-md border border-line bg-soft px-3 py-2">
                      <p className="text-sm font-semibold text-ink">{entry.event}</p>
                      {entry.summary && <p className="mt-1 text-xs text-muted">{entry.summary}</p>}
                    </div>
                  ))}
                </div>
              </div>
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
      <dd className="mt-1 text-sm font-bold text-ink">{value}</dd>
    </div>
  );
}
