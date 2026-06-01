import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import ProduitsPage from '@/app/admin/referentiel/produits/page';
import { listBrands } from '@/lib/services/admin/brands.service';
import { listCategories } from '@/lib/services/admin/categories.service';
import {
  listProductReferences,
} from '@/lib/services/admin/product-references.service';
import { runProductAiEnrichment } from '@/lib/services/admin/product-ai-enrichment.service';

vi.mock('@/lib/services/admin/brands.service', () => ({
  listBrands: vi.fn(),
}));

vi.mock('@/lib/services/admin/categories.service', () => ({
  listCategories: vi.fn(),
}));

vi.mock('@/lib/services/admin/product-references.service', async () => {
  const actual = await vi.importActual<typeof import('@/lib/services/admin/product-references.service')>(
    '@/lib/services/admin/product-references.service',
  );

  return {
    ...actual,
    listProductReferences: vi.fn(),
  };
});

vi.mock('@/lib/services/admin/product-ai-enrichment.service', () => ({
  runProductAiEnrichment: vi.fn(),
}));

describe('Admin product AI enrichment panel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listBrands).mockResolvedValue({ id: 'brands', items: [], page: 1, limit: 50, total: 0 });
    vi.mocked(listCategories).mockResolvedValue({ id: 'categories', items: [], page: 1, limit: 50, total: 0 });
    vi.mocked(listProductReferences).mockResolvedValue({
      id: 'products',
      items: [],
      page: 1,
      limit: 20,
      total: 0,
    });
    vi.mocked(runProductAiEnrichment).mockResolvedValue({
      jobs_created: 12,
      jobs_submitted: 12,
      jobs_applied_total: 0,
      jobs_failed_total: 0,
      active_batches_checked: 1,
      openai_skipped: false,
    });
  });

  it('lets an admin launch AI enrichment with a product count and shows the result', async () => {
    render(React.createElement(ProduitsPage));

    fireEvent.change(await screen.findByLabelText('Nombre de produits à rechercher par IA'), {
      target: { value: '12' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Lancer la recherche IA' }));

    await waitFor(() => expect(runProductAiEnrichment).toHaveBeenCalledWith({ limit: 12 }));
    expect(await screen.findByText('12 jobs créés')).toBeInTheDocument();
    expect(screen.getByText('12 jobs soumis')).toBeInTheDocument();
    expect(screen.getByText('1 batch vérifié')).toBeInTheDocument();
  });
});
