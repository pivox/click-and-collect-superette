'use client';
import { useState, useEffect, useCallback } from 'react';
import { AdminTable, type Column } from '@/components/admin/ui/AdminTable';
import { useSort } from '@/lib/hooks/useSort';
import { listAuditLogs } from '@/lib/services/admin/audit-logs.service';
import type { AuditLog } from '@/lib/types/admin/audit-logs.types';

const PAGE_SIZE = 20;
const UUID_RE = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

function CopyButton({ value }: { value: string }) {
  const [copied, setCopied] = useState(false);

  const handleCopy = () => {
    void navigator.clipboard.writeText(value).then(() => {
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    });
  };

  return (
    <button
      type="button"
      onClick={handleCopy}
      title="Copier l'identifiant"
      className="ml-1 shrink-0 rounded px-1 py-0.5 text-xs text-muted hover:bg-soft hover:text-ink"
    >
      {copied ? '✓' : '⧉'}
    </button>
  );
}

export default function AuditPage() {
  const [logs, setLogs] = useState<AuditLog[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [page, setPage] = useState(1);
  const [total, setTotal] = useState(0);
  const [adminFilter, setAdminFilter] = useState('');
  const [debouncedAdmin, setDebouncedAdmin] = useState('');
  const [actionFilter, setActionFilter] = useState('');
  const [debouncedAction, setDebouncedAction] = useState('');
  const [resourceTypeFilter, setResourceTypeFilter] = useState('');
  const [debouncedResourceType, setDebouncedResourceType] = useState('');

  const { sorted, sortKey, sortDir, toggleSort } = useSort(logs);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedAdmin(adminFilter), 400);
    return () => clearTimeout(t);
  }, [adminFilter]);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedAction(actionFilter), 400);
    return () => clearTimeout(t);
  }, [actionFilter]);

  useEffect(() => {
    const t = setTimeout(() => setDebouncedResourceType(resourceTypeFilter), 400);
    return () => clearTimeout(t);
  }, [resourceTypeFilter]);

  const adminUuid = UUID_RE.test(debouncedAdmin.trim()) ? debouncedAdmin.trim() : undefined;
  const isInvalidAdminInput = debouncedAdmin.trim() !== '' && adminUuid === undefined;

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      const data = await listAuditLogs({
        page,
        limit: PAGE_SIZE,
        admin: adminUuid,
        action: debouncedAction.trim() || undefined,
        resource_type: debouncedResourceType.trim() || undefined,
      });
      setLogs(data.items);
      setTotal(data.total);
    } catch {
      setError("Impossible de charger les logs d'audit.");
    } finally {
      setIsLoading(false);
    }
  }, [page, adminUuid, debouncedAction, debouncedResourceType]);

  useEffect(() => { void load(); }, [load]);
  useEffect(() => { setPage(1); }, [adminUuid, debouncedAction, debouncedResourceType]);

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
      key: 'summary',
      label: 'Action',
      render: (row) => (
        <div>
          <p className="text-sm font-medium text-ink">{row.summary ?? row.action}</p>
          <code className="mt-0.5 inline-block rounded bg-soft px-1.5 py-0.5 text-xs text-muted">
            {row.action}
          </code>
        </div>
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
            <div className="flex items-center">
              <span
                className="max-w-[100px] truncate text-xs text-muted"
                title={row.resource_id}
              >
                {row.resource_id}
              </span>
              <CopyButton value={row.resource_id} />
            </div>
          )}
        </div>
      ),
    },
    {
      key: 'admin_email',
      label: 'Admin',
      sortable: true,
      render: (row) => <span className="text-xs text-muted">{row.admin_email}</span>,
    },
  ];

  return (
    <div>
      <div className="mb-5">
        <h1 className="text-h1 font-black">Audit logs</h1>
        <p className="mt-1 text-sm text-muted">
          Journal des actions critiques effectuées par les administrateurs.
        </p>
      </div>
      <div className="mb-4 flex flex-wrap gap-3">
        <input
          type="text"
          placeholder="Action (ex: store.archive)…"
          value={actionFilter}
          onChange={(e) => setActionFilter(e.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
        <input
          type="text"
          placeholder="Type ressource (ex: store)…"
          value={resourceTypeFilter}
          onChange={(e) => setResourceTypeFilter(e.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
        <div>
          <input
            type="text"
            placeholder="UUID admin…"
            value={adminFilter}
            onChange={(e) => setAdminFilter(e.target.value)}
            className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
          {isInvalidAdminInput && (
            <p className="mt-1 text-xs text-status-cancel">Entrez un UUID valide pour filtrer.</p>
          )}
        </div>
      </div>
      {error && (
        <div className="mb-4 flex items-center gap-3 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          <span className="flex-1">{error}</span>
          <button onClick={() => void load()} className="shrink-0 font-semibold underline">
            Réessayer
          </button>
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
