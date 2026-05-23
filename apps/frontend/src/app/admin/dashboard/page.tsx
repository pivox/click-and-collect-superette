'use client';
import { useState, useEffect } from 'react';
import { listMerchants } from '@/lib/services/admin/merchants.service';
import { listStores } from '@/lib/services/admin/stores.service';
import { listProductReferences } from '@/lib/services/admin/product-references.service';
import { listProposals } from '@/lib/services/admin/proposals.service';

interface KpiCard {
  label: string;
  value: string | number;
}

export default function AdminDashboard() {
  const [kpis, setKpis] = useState<KpiCard[]>([
    { label: 'Marchands', value: '—' },
    { label: 'Supérettes actives', value: '—' },
    { label: 'Produits approuvés', value: '—' },
    { label: 'Propositions en attente', value: '—' },
  ]);
  const [isLoading, setIsLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    void Promise.all([
      listMerchants(1, 1),
      listStores({ page: 1, limit: 1, isActive: true }),
      listProductReferences({ status: 'approved', page: 1, limit: 1 }),
      listProposals('pending', 1),
    ])
      .then(([merchants, stores, products, proposals]) => {
        setKpis([
          { label: 'Marchands', value: merchants.total },
          { label: 'Supérettes actives', value: stores.total },
          { label: 'Produits approuvés', value: products.total },
          { label: 'Propositions en attente', value: Array.isArray(proposals) ? proposals.length : 0 },
        ]);
      })
      .catch(() => {
        setError('Impossible de charger les indicateurs.');
      })
      .finally(() => setIsLoading(false));
  }, []);

  return (
    <div>
      <h1 className="text-h1 font-black">Tableau de bord</h1>
      <p className="mt-1 text-muted">Bienvenue dans le backoffice Kadhia.</p>

      {error && (
        <div className="mt-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}

      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {kpis.map((kpi) => (
          <div key={kpi.label} className="rounded-xl bg-card p-5 shadow-card">
            <span className="text-sm text-muted">{kpi.label}</span>
            <strong className={`mt-1 block text-h2 font-black ${isLoading ? 'text-muted' : ''}`}>
              {isLoading ? '…' : kpi.value}
            </strong>
          </div>
        ))}
      </div>
    </div>
  );
}
