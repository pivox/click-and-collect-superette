import { describe, expect, it } from 'vitest';
import { toResponsiveProductImage } from '@/lib/services/admin/product-references.service';
import type { ProductReferenceImage } from '@/lib/types/admin/referentiel.types';

const SNAKE: ProductReferenceImage = {
  original_url: '/uploads/products/x/original.jpg',
  thumbnail_url: '/uploads/products/x/200.webp',
  card_url: '/uploads/products/x/400.webp',
  detail_url: '/uploads/products/x/800.webp',
  zoom_url: '/uploads/products/x/1200.webp',
  fallback_jpeg_url: '/uploads/products/x/fallback.jpg',
  alt: 'Lait',
  status: 'verified',
};

describe('toResponsiveProductImage', () => {
  it('maps the snake_case admin payload to the camelCase ProductImage shape', () => {
    expect(toResponsiveProductImage(SNAKE)).toEqual({
      originalUrl: '/uploads/products/x/original.jpg',
      thumbnailUrl: '/uploads/products/x/200.webp',
      cardUrl: '/uploads/products/x/400.webp',
      detailUrl: '/uploads/products/x/800.webp',
      zoomUrl: '/uploads/products/x/1200.webp',
      fallbackJpegUrl: '/uploads/products/x/fallback.jpg',
      alt: 'Lait',
      status: 'verified',
    });
  });

  it('returns null when there is no image', () => {
    expect(toResponsiveProductImage(null)).toBeNull();
    expect(toResponsiveProductImage(undefined)).toBeNull();
  });

  it('normalizes an unknown status to null', () => {
    expect(toResponsiveProductImage({ ...SNAKE, status: 'weird' })?.status).toBeNull();
    expect(toResponsiveProductImage({ ...SNAKE, status: null })?.status).toBeNull();
  });
});
