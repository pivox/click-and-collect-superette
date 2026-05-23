'use client';
import { useState, useEffect } from 'react';
import { listMerchants } from '@/lib/services/admin/merchants.service';
import { listStores } from '@/lib/services/admin/stores.service';
import { listProductReferences } from '@/lib/services/admin/product-references.service';
import { listProposals } from '@/lib/services/admin/proposals.service';

interface KpiCard {
  label: string;
  value: string | number;
  sub?: string;
}

export default function AdminDashboard() {
  const [kpis, setKpis] = useState<KpiCard[]>([
    { label: 'Marchands', value: '—' },
    { label: 'Supérettes actives', value: '—' },
    { label: 'Produits approuvés', value: '—' },
    { label: 'Propositions en attente', value: '—' },
  ]);
  const [isLoading, setIsLoading] = useState(true);

  useEffect(() => {
    void Promise.all([
      listMerchants(1, 1),
      listStores({ page: 1, limit: 1, status: 'active' }),
      listProductReferences({ status: 'approved', page: 1, limit: 1 }),
      listProposals('pending', 1),
    ])
      .then(([merchants, stores, products, proposals]) => {
        setKpis([
          { label: 'Marchands', value: merchants.total },
          { label: 'Supérettes actives', value: stores.total },
          { label: 'Produits approuvés', value: products.total },
          { label: 'Propositions en attente', value: proposals.length },
        ]);
      })
      .finally(() => setIsLoading(false));
  }, []);

  return (
    <div>
      <h1 className="text-h1 font-black">Tableau de bord</h1>
      <p className="mt-1 text-muted">Bienvenue dans le backoffice Kadhia.</p>

      <div className="mt-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        {kpis.map((kpi) => (
          <div key={kpi.label} className="rounded-xl bg-card p-5 shadow-card">
            <span className="text-sm text-muted">{kpi.label}</span>
            <strong className={`mt-1 block text-h2 font-black ${isLoading ? 'text-muted' : ''}`}>
              {isLoading ? '…' : kpi.value}
            </strong>
            {kpi.sub && <span className="text-xs text-muted">{kpi.sub}</span>}
          </div>
        ))}
      </div>
    </div>
  );
}
