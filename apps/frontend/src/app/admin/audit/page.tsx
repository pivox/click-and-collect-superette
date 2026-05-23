'use client';
import { useState, useEffect, useCallback } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { useSort } from '@/lib/hooks/useSort';
import { listAuditLogs } from '@/lib/services/admin/audit-logs.service';
import type { AuditLog } from '@/lib/types/admin/audit-logs.types';

const PAGE_SIZE = 20;

export default function AuditPage() {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [adminFilter, setAdminFilter] = useState('');
  const [debouncedAdmin, setDebouncedAdmin] = useState('');

  const { sorted, sortKey, sortDir, toggleSort } = useSort(logs);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedAdmin(adminFilter), 400);
    return () => clearTimeout(t);
  }, [adminFilter]);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listAuditLogs({
        page,
        limit: PAGE_SIZE,
        admin: debouncedAdmin || undefined,
      });
      setLogs(data.items);
      setTotal(data.total);
    } catch {
      setError('Impossible de charger les logs d\'audit.');
    } finally {
      setIsLoading(false);
    }
  }, [page, debouncedAdmin]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { setPage(1); }, [debouncedAdmin]);

  const columns: Column<AuditLog>[] = [
    {
      key: 'created_at',
      label: 'Date',
      sortable: true,
      render: (row) => (
        <div className="whitespace-nowrap text-sm">
          {new Date(row.created_at).toLocaleString('fr-TN', {
            dateStyle: 'short',
            timeStyle: 'short',
          })}
        </div>
      ),
    },
    {
      key: 'admin_email',
      label: 'Admin',
      sortable: true,
      render: (row) => <span className="text-xs text-muted">{row.admin_email}</span>,
    },
    {
      key: 'action',
      label: 'Action',
      sortable: true,
      render: (row) => (
        <code className="rounded bg-soft px-1.5 py-0.5 text-xs text-ink">{row.action}</code>
      ),
    },
    {
      key: 'resource_type',
      label: 'Ressource',
      sortable: true,
      render: (row) => (
        <div>
          <span className="text-sm">{row.resource_type}</span>
          {row.resource_id && (
            <div className="max-w-[120px] truncate text-xs text-muted" title={row.resource_id}>
              {row.resource_id}
            </div>
          )}
        </div>
      ),
    },
    {
      key: 'summary',
      label: 'Résumé',
      render: (row) => <span className="text-sm">{row.summary}</span>,
    },
    {
      key: 'ip_address',
      label: 'IP',
      render: (row) =>
        row.ip_address ? (
          <code className="text-xs text-muted">{row.ip_address}</code>
        ) : (
          <span className="text-muted">—</span>
        ),
    },
  ];

  return (
    <div>
      <div className="mb-5">
        <h1 className="text-h1 font-black">Audit logs</h1>
        <p className="mt-1 text-sm text-muted">Journal des actions critiques effectuées par les administrateurs.</p>
      </div>
      <div className="mb-4">
        <input
          type="text"
          placeholder="Filtrer par email admin…"
          value={adminFilter}
          onChange={(e) => setAdminFilter(e.target.value)}
          className="w-full max-w-sm rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
      </div>
      {error && (
        <div className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}
      <AdminTable
        columns={columns}
        data={sorted}
        isLoading={isLoading}
        emptyMessage="Aucun log trouvé."
        pagination={{ page, total, limit: PAGE_SIZE, onPageChange: setPage }}
        sortKey={sortKey as string | null}
        sortDir={sortDir}
        onSort={(key) => toggleSort(key as keyof AuditLog)}
      />
    </div>
  );
}
