'use client';
import { useState, useEffect, useCallback } from 'react';
import { ProposalRow } from '@/components/admin/referentiel/propositions/ProposalRow';
import { useSort } from '@/lib/hooks/useSort';
import { listProposals } from '@/lib/services/admin/proposals.service';
import type { Proposal } from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

type StatusFilter = 'pending' | 'approved' | 'rejected';
type ExpansionMode = 'approve' | 'reject';

const STATUS_LABELS: Record<StatusFilter, string> = {
  pending: 'En attente',
  approved: 'Approuvé',
  rejected: 'Rejeté',
};

interface SortThProps<T> {
  col: keyof T;
  label: string;
  sortKey: keyof T | null;
  sortDir: 'asc' | 'desc';
  onToggle: (col: keyof T) => void;
}

function SortTh<T>({ col, label, sortKey, sortDir, onToggle }: SortThProps<T>) {
  return (
    <th
      onClick={() => onToggle(col)}
      className="cursor-pointer select-none px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted hover:text-ink"
    >
      {label}
      {sortKey === col && <span className="ml-1">{sortDir === 'asc' ? '↑' : '↓'}</span>}
    </th>
  );
}

export default function PropositionsPage() {
  const [proposals, setProposals] = useState<Proposal[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [statusFilter, setStatusFilter] = useState<StatusFilter>('pending');
  const [search, setSearch] = useState('');
  const [expandedId, setExpandedId] = useState<string | null>(null);
  const [expandedMode, setExpandedMode] = useState<ExpansionMode | null>(null);

  const filtered = proposals.filter((p) =>
    search ? p.name_fr.toLowerCase().includes(search.toLowerCase()) : true,
  );
  const { sorted, sortKey, sortDir, toggleSort } = useSort(filtered);

  const load = useCallback(async () => {
    setIsLoading(true);
    setError(null);
    try {
      setProposals(await listProposals(statusFilter));
    } catch {
      setError('Impossible de charger les propositions.');
    } finally {
      setIsLoading(false);
    }
  }, [statusFilter]);

  useEffect(() => {
    setExpandedId(null);
    setExpandedMode(null);
    void load();
  }, [load]);

  const handleToggle = (id: string, mode: ExpansionMode | null) => {
    if (mode === null) { setExpandedId(null); setExpandedMode(null); return; }
    setExpandedId(id);
    setExpandedMode(mode);
  };

  return (
    <div>
      <h1 className="mb-5 text-h1 font-black">Propositions</h1>
      <div className="mb-4 flex flex-wrap items-center gap-4">
        <div className="flex gap-2">
          {(['pending', 'approved', 'rejected'] as StatusFilter[]).map((s) => (
            <button
              key={s}
              onClick={() => setStatusFilter(s)}
              className={cn(
                'rounded-full px-3 py-1 text-xs font-semibold transition-colors',
                statusFilter === s
                  ? 'bg-primary text-white'
                  : 'bg-soft text-muted hover:bg-line',
              )}
            >
              {STATUS_LABELS[s]}
            </button>
          ))}
        </div>
        <input
          type="text"
          placeholder="Rechercher par nom…"
          value={search}
          onChange={(e) => setSearch(e.target.value)}
          className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
        />
      </div>
      {error && (
        <div className="mb-4 flex items-center gap-3 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          <span className="flex-1">{error}</span>
          <button onClick={() => void load()} className="shrink-0 font-semibold underline">
            Réessayer
          </button>
        </div>
      )}
      <div className="rounded-xl border border-line bg-card overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-sm">
            <thead className="border-b border-line bg-soft">
              <tr>
                <SortTh<Proposal>
                  col="name_fr"
                  label="Nom proposé"
                  sortKey={sortKey}
                  sortDir={sortDir}
                  onToggle={toggleSort}
                />
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted">Marque</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted">Catégorie</th>
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted">Proposé par</th>
                <SortTh<Proposal>
                  col="created_at"
                  label="Date"
                  sortKey={sortKey}
                  sortDir={sortDir}
                  onToggle={toggleSort}
                />
                <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-muted">Actions</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-line">
              {isLoading ? (
                Array.from({ length: 5 }).map((_, i) => (
                  <tr key={i}>
                    {Array.from({ length: 6 }).map((_, j) => (
                      <td key={j} className="px-4 py-3">
                        <div className="h-4 w-3/4 animate-pulse rounded bg-soft" />
                      </td>
                    ))}
                  </tr>
                ))
              ) : sorted.length === 0 ? (
                <tr>
                  <td colSpan={6} className="px-4 py-12 text-center text-sm text-muted">
                    Aucune proposition trouvée.
                  </td>
                </tr>
              ) : (
                sorted.map((p) => (
                  <ProposalRow
                    key={p.id}
                    proposal={p}
                    isExpanded={expandedId === p.id ? expandedMode : null}
                    onToggle={handleToggle}
                    onProcessed={() => void load()}
                  />
                ))
              )}
            </tbody>
          </table>
        </div>
      </div>
    </div>
  );
}
