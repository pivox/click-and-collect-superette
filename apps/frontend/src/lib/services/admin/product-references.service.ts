import { apiClient } from '@/lib/api';
import type {
  ProductReferenceListResponse,
  ProductReference,
  ProductReferenceFilters,
  ProductReferenceImage,
  CreateProductReferencePayload,
  UpdateProductReferencePayload,
} from '@/lib/types/admin/referentiel.types';
import type { ProductImage } from '@/types';

/**
 * Adapt the admin (snake_case) image payload to the camelCase `ProductImage`
 * shape consumed by the shared `ProductThumbnail` component.
 */
export function toResponsiveProductImage(
  image: ProductReferenceImage | null | undefined,
): ProductImage | null {
  if (!image) {
    return null;
  }
  const status =
    image.status === 'verified' ||
    image.status === 'candidate' ||
    image.status === 'needs_review'
      ? image.status
      : null;
  return {
    originalUrl: image.original_url,
    thumbnailUrl: image.thumbnail_url,
    cardUrl: image.card_url,
    detailUrl: image.detail_url,
    zoomUrl: image.zoom_url,
    fallbackJpegUrl: image.fallback_jpeg_url,
    alt: image.alt,
    status,
  };
}

export async function listProductReferences(
  filters: ProductReferenceFilters = {},
): Promise<ProductReferenceListResponse> {
  const { data } = await apiClient.get<ProductReferenceListResponse>(
    '/api/admin/product-references',
    {
      params: {
        page: filters.page ?? 1,
        limit: filters.limit ?? 20,
        ...(filters.q ? { q: filters.q } : {}),
        ...(filters.brand ? { brand: filters.brand } : {}),
        ...(filters.category ? { category: filters.category } : {}),
        ...(filters.status ? { status: filters.status } : {}),
      },
    },
  );
  return data;
}

export async function createProductReference(
  payload: CreateProductReferencePayload,
): Promise<ProductReference> {
  const { data } = await apiClient.post<ProductReference>(
    '/api/admin/product-references',
    payload,
  );
  return data;
}

export async function updateProductReference(
  id: string,
  payload: UpdateProductReferencePayload,
): Promise<ProductReference> {
  const { data } = await apiClient.patch<ProductReference>(
    `/api/admin/product-references/${id}`,
    payload,
  );
  return data;
}

export async function archiveProductReference(id: string): Promise<ProductReference> {
  const { data } = await apiClient.patch<ProductReference>(
    `/api/admin/product-references/${id}/archive`,
    {},
  );
  return data;
}

/**
 * Upload the official image of a product reference (multipart/form-data).
 * The backend validates the file (JPEG/PNG/WebP, max 2 MB, min 400×400),
 * generates the responsive WebP variants + JPEG fallback, and returns the
 * resulting image payload.
 */
export async function uploadProductReferenceImage(
  id: string,
  file: File,
  alt?: string,
): Promise<{ image: ProductReferenceImage }> {
  const formData = new FormData();
  formData.append('image', file);
  if (alt) {
    formData.append('alt', alt);
  }
  const { data } = await apiClient.post<{ image: ProductReferenceImage }>(
    `/api/admin/product-references/${id}/image`,
    formData,
    { headers: { 'Content-Type': 'multipart/form-data' } },
  );
  return data;
}

export async function deleteProductReferenceImage(id: string): Promise<void> {
  await apiClient.delete(`/api/admin/product-references/${id}/image`);
}
