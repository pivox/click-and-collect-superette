'use client';
import { useState, useEffect, useCallback } from 'react';
import Link from 'next/link';
import { listMerchants } from '@/lib/services/admin/merchants.service';
import { listStores } from '@/lib/services/admin/stores.service';
import { listProductReferences } from '@/lib/services/admin/product-references.service';
import { listProposals } from '@/lib/services/admin/proposals.service';
import { listAuditLogs } from '@/lib/services/admin/audit-logs.service';
import type { AuditLog } from '@/lib/types/admin/audit-logs.types';

interface KpiCard {
  label: string;
  value: string | number;
  href: string;
}

interface ActionBlock {
  label: string;
  count: number;
  description: string;
  href: string;
  cta: string;
  urgent?: boolean;
}

export default function AdminDashboard() {
  const [kpis, setKpis] = useState<KpiCard[]>([
    { label: 'Marchands', value: '—', href: '/admin/marchands' },
    { label: 'Supérettes actives', value: '—', href: '/admin/superettes' },
    { label: 'Produits approuvés', value: '—', href: '/admin/referentiel/produits' },
    { label: 'Propositions en attente', value: '—', href: '/admin/referentiel/propositions' },
  ]);
  const [pendingProposals, setPendingProposals] = useState(0);
  const [recentLogs, setRecentLogs] = useState<AuditLog[]>([]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  const load = useCallback(() => {
    setIsLoading(true);
    setError(null);
    void Promise.all([
      listMerchants(1, 1),
      listStores({ page: 1, limit: 1, isActive: true }),
      listProductReferences({ status: 'approved', page: 1, limit: 1 }),
      listProposals('pending', 1),
      listAuditLogs({ page: 1, limit: 5 }),
    ])
      .then(([merchants, stores, products, proposals, logs]) => {
        const pending = Array.isArray(proposals) ? proposals.length : 0;
        setPendingProposals(pending);
        setRecentLogs(logs.items ?? []);
        setKpis([
          { label: 'Marchands', value: merchants.total, href: '/admin/marchands' },
          { label: 'Supérettes actives', value: stores.total, href: '/admin/superettes' },
          { label: 'Produits approuvés', value: products.total, href: '/admin/referentiel/produits' },
          { label: 'Propositions en attente', value: pending, href: '/admin/referentiel/propositions' },
        ]);
      })
      .catch((err: unknown) => {
        console.error('[dashboard] load failed', err);
        setError('Impossible de charger les indicateurs.');
      })
      .finally(() => setIsLoading(false));
  }, []);

  useEffect(() => { load(); }, [load]);

  const actionBlocks: ActionBlock[] = [
    ...(pendingProposals > 0
      ? [{
          label: 'Propositions à traiter',
          count: pendingProposals,
          description: `${pendingProposals} proposition${pendingProposals > 1 ? 's' : ''} en attente de validation.`,
          href: '/admin/referentiel/propositions',
          cta: 'Traiter les propositions',
          urgent: true,
        }]
      : []),
  ];

  return (
    <div>
      <h1 className="text-h1 font-black">Tableau de bord</h1>
      <p className="mt-1 text-muted">Bienvenue dans le backoffice Kadhia.</p>

      {error && (
        <div className="mt-4 flex items-center gap-3 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          <span className="flex-1">{error}</span>
          <button
            onClick={load}
            className="shrink-0 font-semibold underline"
          >
            Réessayer
          </button>
        </div>
      )}

      {/* Action blocks urgents */}
      {!isLoading && actionBlocks.length > 0 && (
        <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
          {actionBlocks.map((block) => (
            <div
              key={block.label}
              className={`rounded-xl border-2 p-4 ${
                block.urgent
                  ? 'border-yellow-300 bg-yellow-50'
                  : 'border-line bg-card'
              }`}
            >
              <div className="flex items-start justify-between">
                <div>
                  <p className="text-xs font-bold uppercase tracking-wide text-muted">
                    {block.label}
                  </p>
                  <p className={`mt-1 text-3xl font-black ${block.urgent ? 'text-yellow-700' : 'text-ink'}`}>
                    {block.count}
                  </p>
                  <p className="mt-1 text-sm text-muted">{block.description}</p>
                </div>
              </div>
              <Link
                href={block.href}
                className={`mt-3 inline-block rounded-md px-3 py-1.5 text-xs font-semibold transition-colors ${
                  block.urgent
                    ? 'bg-yellow-500 text-white hover:bg-yellow-600'
                    : 'bg-primary text-white hover:brightness-90'
                }`}
              >
                {block.cta} →
              </Link>
            </div>
          ))}
        </div>
      )}

      {/* KPI cards */}
      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {kpis.map((kpi) => (
          <Link key={kpi.label} href={kpi.href}>
            <div className="rounded-xl bg-card p-5 shadow-card transition-shadow hover:shadow-floating">
              <span className="text-sm text-muted">{kpi.label}</span>
              <strong className={`mt-1 block text-h2 font-black ${isLoading ? 'text-muted' : ''}`}>
                {isLoading ? '…' : kpi.value}
              </strong>
            </div>
          </Link>
        ))}
      </div>

      {/* Accès rapides */}
      <div className="mt-8">
        <h2 className="mb-3 text-sm font-bold uppercase tracking-wide text-muted">Accès rapides</h2>
        <div className="flex flex-wrap gap-2">
          {[
            { label: 'Marchands', href: '/admin/marchands' },
            { label: 'Supérettes', href: '/admin/superettes' },
            { label: 'Référentiel produits', href: '/admin/referentiel/produits' },
            { label: 'Propositions', href: '/admin/referentiel/propositions' },
            { label: 'Audit', href: '/admin/audit' },
          ].map((link) => (
            <Link
              key={link.href}
              href={link.href}
              className="rounded-full border border-line bg-card px-3 py-1.5 text-xs font-semibold text-ink hover:bg-soft"
            >
              {link.label}
            </Link>
          ))}
        </div>
      </div>

      {/* Actions récentes */}
      {!isLoading && recentLogs.length > 0 && (
        <div className="mt-8">
          <div className="mb-3 flex items-center justify-between">
            <h2 className="text-sm font-bold uppercase tracking-wide text-muted">Actions récentes</h2>
            <Link href="/admin/audit" className="text-xs text-primary underline">
              Voir tout
            </Link>
          </div>
          <div className="rounded-xl border border-line bg-card overflow-hidden">
            {recentLogs.map((log) => (
              <div
                key={log.id}
                className="flex flex-col gap-1 border-b border-line px-4 py-3 last:border-0 sm:flex-row sm:items-center sm:gap-3"
              >
                <div className="min-w-0 flex-1">
                  <p className="break-words text-sm font-medium">{log.summary ?? log.action}</p>
                  <p className="break-all text-xs text-muted">{log.admin_email}</p>
                </div>
                <span className="shrink-0 text-xs text-muted">
                  {new Date(log.created_at).toLocaleString('fr-TN', {
                    dateStyle: 'short',
                    timeStyle: 'short',
                  })}
                </span>
              </div>
            ))}
          </div>
        </div>
      )}
    </div>
  );
}
