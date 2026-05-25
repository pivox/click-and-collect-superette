'use client';

import { Button } from '@/components/ui/Button';
import { cn } from '@/lib/cn';
import { formatTnd } from '@/lib/format';
import type { MerchantCatalogProduct } from '@/lib/types/merchant-catalog.types';

interface MerchantCatalogTableProps {
  products: MerchantCatalogProduct[];
  emptyMessage: string;
}

function statusBadge(label: string, active: boolean, tone: 'success' | 'muted') {
  return (
    <span
      className={cn(
        'inline-flex min-h-[28px] items-center rounded-md px-2 text-xs font-black',
        tone === 'success' && active
          ? 'bg-status-ready-bg text-status-ready'
          : 'bg-soft text-muted',
      )}
    >
      {label}
    </span>
  );
}

function productFormat(product: MerchantCatalogProduct): string {
  return [product.volume, product.unit].filter(Boolean).join(' ') || 'Format non renseigné';
}

export function MerchantCatalogTable({ emptyMessage, products }: MerchantCatalogTableProps) {
  if (products.length === 0) {
    return <p className="p-5 text-sm text-muted">{emptyMessage}</p>;
  }

  return (
    <div className="overflow-x-auto">
      <table className="w-full min-w-[760px] text-left text-sm">
        <thead className="border-b border-line text-xs uppercase text-muted">
          <tr>
            <th className="px-4 py-3 font-black">Produit</th>
            <th className="px-4 py-3 font-black">Catégorie</th>
            <th className="px-4 py-3 font-black">Prix</th>
            <th className="px-4 py-3 font-black">Statuts</th>
            <th className="px-4 py-3 font-black">Note marchand</th>
            <th className="px-4 py-3 text-right font-black">Action</th>
          </tr>
        </thead>
        <tbody className="divide-y divide-line">
          {products.map((product) => (
            <tr key={product.id}>
              <td className="px-4 py-4 align-top">
                <strong className="block text-base">{product.name_fr}</strong>
                <span className="mt-1 block text-muted">{product.brand}</span>
                <span className="mt-1 block text-muted">{productFormat(product)}</span>
              </td>
              <td className="px-4 py-4 align-top">
                {product.merchant_category_name ?? product.category}
              </td>
              <td className="px-4 py-4 align-top font-black">{formatTnd(product.price_tnd)}</td>
              <td className="px-4 py-4 align-top">
                <div className="flex flex-wrap gap-2">
                  {product.is_available
                    ? statusBadge('Disponible', true, 'success')
                    : statusBadge('Indisponible', false, 'muted')}
                  {product.is_visible
                    ? statusBadge('Visible', true, 'success')
                    : statusBadge('Masqué', false, 'muted')}
                </div>
              </td>
              <td className="max-w-[220px] px-4 py-4 align-top text-muted">
                {product.merchant_note || '—'}
              </td>
              <td className="px-4 py-4 text-right align-top">
                <Button
                  type="button"
                  variant="ghost"
                  size="md"
                  disabled
                  title="Prévu dans une prochaine étape"
                >
                  Modifier
                </Button>
              </td>
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  );
}
