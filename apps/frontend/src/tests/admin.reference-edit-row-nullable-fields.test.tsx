import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { BrandEditRow } from '@/components/admin/referentiel/marques/BrandEditRow';
import { CategoryEditRow } from '@/components/admin/referentiel/categories/CategoryEditRow';
import { ProductReferenceEditRow } from '@/components/admin/referentiel/produits/ProductReferenceEditRow';
import { updateBrand } from '@/lib/services/admin/brands.service';
import { updateCategory } from '@/lib/services/admin/categories.service';
import { updateProductReference } from '@/lib/services/admin/product-references.service';
import type { Brand, Category, ProductReference } from '@/lib/types/admin/referentiel.types';

vi.mock('@/lib/services/admin/brands.service', () => ({
  createBrand: vi.fn(),
  updateBrand: vi.fn(),
}));

vi.mock('@/lib/services/admin/categories.service', () => ({
  createCategory: vi.fn(),
  updateCategory: vi.fn(),
}));

vi.mock('@/lib/services/admin/product-references.service', () => ({
  updateProductReference: vi.fn(),
  rejectProductReference: vi.fn(),
}));

const brand: Brand = {
  id: 'brand-1',
  canonical_name: 'Vitalait',
  slug: 'vitalait',
  aliases: ['vita'],
  country: 'TN',
  is_active: true,
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
};

const category: Category = {
  id: 'category-1',
  name_fr: 'Lait',
  name_ar: 'حليب',
  slug: 'lait',
  is_active: true,
  sort_order: 1,
  parent_id: null,
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
};

const product: ProductReference = {
  id: 'product-1',
  name_fr: 'Lait demi-ecreme',
  name_ar: 'حليب',
  variant_fr: null,
  variant_ar: null,
  brand_id: brand.id,
  brand_name: brand.canonical_name,
  category_id: category.id,
  category_name_fr: category.name_fr,
  category_name_ar: category.name_ar,
  unit: 'piece',
  volume: '1L',
  barcode: '6191234567890',
  aliases: [],
  country: 'TN',
  status: 'approved',
  created_at: '2026-01-01T00:00:00Z',
  updated_at: '2026-01-01T00:00:00Z',
  quality_score: 90,
  quality_level: 'good',
};

describe('admin reference quick edit nullable fields', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(updateBrand).mockResolvedValue(brand);
    vi.mocked(updateCategory).mockResolvedValue(category);
    vi.mocked(updateProductReference).mockResolvedValue(product);
  });

  it('sends null when clearing optional product fields', async () => {
    const onSaved = vi.fn();
    const { container } = render(
      <table>
        <tbody>
          <ProductReferenceEditRow
            product={product}
            brands={[brand]}
            categories={[category]}
            colSpan={8}
            onSaved={onSaved}
            onCancel={vi.fn()}
          />
        </tbody>
      </table>,
    );

    const arabicName = container.querySelector('input[dir="rtl"]') as HTMLInputElement;
    fireEvent.change(arabicName, { target: { value: '' } });
    fireEvent.change(screen.getByPlaceholderText('ex. 3017620422003'), { target: { value: '' } });
    fireEvent.change(screen.getByPlaceholderText('ex. 500ml'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(updateProductReference).toHaveBeenCalled());
    expect(updateProductReference).toHaveBeenCalledWith(product.id, expect.objectContaining({
      nameAr: null,
      barcode: null,
      volume: null,
    }));
    expect(onSaved).toHaveBeenCalled();
  });

  it('sends null when clearing the category Arabic name', async () => {
    render(
      <table>
        <tbody>
          <CategoryEditRow
            category={category}
            colSpan={5}
            onSaved={vi.fn()}
            onCancel={vi.fn()}
          />
        </tbody>
      </table>,
    );

    fireEvent.change(screen.getByPlaceholderText('اسم الفئة…'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(updateCategory).toHaveBeenCalled());
    expect(updateCategory).toHaveBeenCalledWith(category.id, expect.objectContaining({
      nameAr: null,
    }));
  });

  it('sends null when clearing the brand country', async () => {
    render(
      <table>
        <tbody>
          <BrandEditRow
            brand={brand}
            colSpan={5}
            onSaved={vi.fn()}
            onCancel={vi.fn()}
          />
        </tbody>
      </table>,
    );

    fireEvent.change(screen.getByPlaceholderText('ex. TN'), { target: { value: '' } });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer' }));

    await waitFor(() => expect(updateBrand).toHaveBeenCalled());
    expect(updateBrand).toHaveBeenCalledWith(brand.id, expect.objectContaining({
      country: null,
    }));
  });
});
