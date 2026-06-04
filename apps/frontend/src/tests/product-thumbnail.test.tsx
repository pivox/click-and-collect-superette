import { fireEvent, render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';
import { ProductThumbnail } from '@/components/product/ProductThumbnail';
import { ProductCard } from '@/components/product/ProductCard';
import type { ProductImage, ProductOffer } from '@/types';

const IMAGE: ProductImage = {
  originalUrl: '/uploads/products/abc/original.jpg',
  thumbnailUrl: '/uploads/products/abc/200.webp',
  cardUrl: '/uploads/products/abc/400.webp',
  detailUrl: '/uploads/products/abc/800.webp',
  zoomUrl: '/uploads/products/abc/1200.webp',
  fallbackJpegUrl: '/uploads/products/abc/fallback.jpg',
  alt: 'Lait demi-écrémé',
  status: 'verified',
};

function makeProduct(overrides: Partial<ProductOffer> = {}): ProductOffer {
  return {
    id: 'po-1',
    productReferenceId: 'ref-1',
    nameFr: 'Lait demi-écrémé',
    nameAr: null,
    brand: 'Vitalait',
    volume: 1,
    unit: 'L',
    priceTnd: '3.000',
    isAvailable: true,
    photoUrl: null,
    category: 'dairy',
    emoji: '🥛',
    ...overrides,
  };
}

describe('ProductThumbnail', () => {
  it('renders the image with a WebP source and JPEG fallback when an image is present', () => {
    const { container } = render(
      <ProductThumbnail image={IMAGE} nameFr="Lait demi-écrémé" emoji="🥛" />,
    );

    const img = screen.getByRole('img', { name: 'Lait demi-écrémé' });
    // JPEG fallback is the <img> src so non-WebP browsers still get a picture.
    expect(img.getAttribute('src')).toContain('/uploads/products/abc/fallback.jpg');
    expect(img.getAttribute('loading')).toBe('lazy');

    const source = container.querySelector('source[type="image/webp"]');
    expect(source).not.toBeNull();
    expect(source?.getAttribute('srcset')).toContain('400.webp 400w');
  });

  it('shows the category placeholder (emoji) when no image is available', () => {
    render(<ProductThumbnail image={null} nameFr="Lait demi-écrémé" emoji="🥛" />);

    expect(screen.queryByRole('img', { name: 'Lait demi-écrémé' })?.tagName).not.toBe('IMG');
    expect(screen.getByText('🥛')).toBeTruthy();
  });

  it('falls back to the placeholder when the image fails to load', () => {
    render(<ProductThumbnail image={IMAGE} nameFr="Lait demi-écrémé" emoji="🥛" />);

    const img = screen.getByRole('img', { name: 'Lait demi-écrémé' });
    fireEvent.error(img);

    // The broken <img> is replaced by the emoji placeholder — no broken icon.
    expect(screen.queryByRole('img')?.tagName).not.toBe('IMG');
    expect(screen.getByText('🥛')).toBeTruthy();
  });
});

describe('ProductCard image integration', () => {
  it('renders the product image when present', () => {
    render(<ProductCard product={makeProduct({ image: IMAGE })} />);
    expect(screen.getByText('Lait demi-écrémé')).toBeTruthy();
    const img = screen.getByRole('img', { name: 'Lait demi-écrémé' });
    expect(img.tagName).toBe('IMG');
  });

  it('stays usable and stable when the product has no image', () => {
    render(<ProductCard product={makeProduct({ image: null })} />);
    // Name and price still render; placeholder replaces the image without breaking layout.
    expect(screen.getByText('Lait demi-écrémé')).toBeTruthy();
    expect(screen.getByText('🥛')).toBeTruthy();
  });
});
