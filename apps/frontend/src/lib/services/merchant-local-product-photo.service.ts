import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';
import type { MerchantCatalogProductImage } from '@/lib/types/merchant-catalog.types';

// PRODUCT-IMAGE-003 (#583): merchant photo of a local/vrac/reconditioned product.
// The photo is shop-scoped (status candidate) — it illustrates the product in the
// shop catalogue only and never becomes the shared referential image by itself.

interface UploadMerchantLocalProductPhotoResponse {
  image: MerchantCatalogProductImage;
}

/** Stable backend error codes surfaced to the UI (422 responses). */
export type MerchantLocalProductPhotoErrorCode =
  | 'MERCHANT_PHOTO_TOO_SMALL'
  | 'PRODUCT_IMAGE_TOO_LARGE'
  | 'PRODUCT_IMAGE_UNSUPPORTED_MIME'
  | 'PRODUCT_IMAGE_UNREADABLE';

const KNOWN_ERROR_CODES: MerchantLocalProductPhotoErrorCode[] = [
  'MERCHANT_PHOTO_TOO_SMALL',
  'PRODUCT_IMAGE_TOO_LARGE',
  'PRODUCT_IMAGE_UNSUPPORTED_MIME',
  'PRODUCT_IMAGE_UNREADABLE',
];

function mockImage(): MerchantCatalogProductImage {
  return {
    original_url: '/uploads/products/mock/original.jpg',
    thumbnail_url: '/uploads/products/mock/200.webp',
    card_url: '/uploads/products/mock/400.webp',
    detail_url: '/uploads/products/mock/800.webp',
    zoom_url: '/uploads/products/mock/1200.webp',
    fallback_jpeg_url: '/uploads/products/mock/fallback.jpg',
    alt: 'Photo produit',
    status: 'candidate',
    license_code: 'merchant_authorized',
  };
}

export async function uploadMerchantLocalProductPhoto(
  storeId: string,
  localProductId: string,
  photo: File,
): Promise<MerchantCatalogProductImage> {
  if (USE_MOCKS) {
    return mockDelay(mockImage());
  }

  const formData = new FormData();
  formData.append('photo', photo);

  const { data } = await apiClient.post<UploadMerchantLocalProductPhotoResponse>(
    `/api/merchant/stores/${storeId}/local-products/${localProductId}/photo`,
    formData,
    { headers: { Accept: 'application/json', 'Content-Type': undefined } },
  );

  return data.image;
}

export async function deleteMerchantLocalProductPhoto(
  storeId: string,
  localProductId: string,
): Promise<void> {
  if (USE_MOCKS) {
    await mockDelay(undefined);
    return;
  }

  await apiClient.delete(
    `/api/merchant/stores/${storeId}/local-products/${localProductId}/photo`,
  );
}

/**
 * Extracts the stable backend error code from an upload failure, so the UI can
 * show a precise message (photo too small / too large / wrong format).
 */
export function extractMerchantPhotoErrorCode(
  error: unknown,
): MerchantLocalProductPhotoErrorCode | null {
  const detail = (
    error as { response?: { data?: { detail?: unknown } } } | null
  )?.response?.data?.detail;

  if (typeof detail !== 'string') {
    return null;
  }

  return KNOWN_ERROR_CODES.find((code) => detail.startsWith(code)) ?? null;
}
