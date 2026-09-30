import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  deleteMerchantLocalProductPhoto,
  extractMerchantPhotoErrorCode,
  uploadMerchantLocalProductPhoto,
} from '@/lib/services/merchant-local-product-photo.service';

vi.mock('@/lib/api', () => ({
  apiClient: {
    post: vi.fn(),
    delete: vi.fn(),
  },
}));

// The service must hit the real API in these tests (mocks disabled).
vi.mock('@/lib/services/index', () => ({
  USE_MOCKS: false,
  mockDelay: <T,>(value: T) => Promise.resolve(value),
}));

const image = {
  original_url: '/uploads/products/i-1/original.jpg',
  thumbnail_url: '/uploads/products/i-1/200.webp',
  card_url: '/uploads/products/i-1/400.webp',
  detail_url: '/uploads/products/i-1/800.webp',
  zoom_url: '/uploads/products/i-1/1200.webp',
  fallback_jpeg_url: '/uploads/products/i-1/fallback.jpg',
  alt: 'Harissa maison',
  status: 'candidate',
  license_code: 'merchant_authorized',
};

describe('merchant local product photo service (#583)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('uploads the photo as multipart form data and returns the stored image', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: { image } });
    const file = new File(['fake-bytes'], 'photo.jpg', { type: 'image/jpeg' });

    const result = await uploadMerchantLocalProductPhoto('store-1', 'lp-1', file);

    expect(apiClient.post).toHaveBeenCalledTimes(1);
    const [url, body, config] = vi.mocked(apiClient.post).mock.calls[0];
    expect(url).toBe('/api/merchant/stores/store-1/local-products/lp-1/photo');
    expect(body).toBeInstanceOf(FormData);
    expect((body as FormData).get('photo')).toBe(file);
    expect(config?.headers?.['Content-Type']).toBeUndefined();
    expect(result).toEqual(image);
  });

  it('deletes the photo of a local product', async () => {
    vi.mocked(apiClient.delete).mockResolvedValue({ data: null });

    await deleteMerchantLocalProductPhoto('store-1', 'lp-1');

    expect(apiClient.delete).toHaveBeenCalledWith(
      '/api/merchant/stores/store-1/local-products/lp-1/photo',
    );
  });

  it('extracts known backend error codes from a 422 response', () => {
    const error = { response: { status: 422, data: { detail: 'MERCHANT_PHOTO_TOO_SMALL' } } };
    expect(extractMerchantPhotoErrorCode(error)).toBe('MERCHANT_PHOTO_TOO_SMALL');

    const mimeError = {
      response: { status: 422, data: { detail: 'PRODUCT_IMAGE_UNSUPPORTED_MIME: image/gif' } },
    };
    expect(extractMerchantPhotoErrorCode(mimeError)).toBe('PRODUCT_IMAGE_UNSUPPORTED_MIME');
  });

  it('returns null for unknown or malformed errors', () => {
    expect(extractMerchantPhotoErrorCode(new Error('network down'))).toBeNull();
    expect(extractMerchantPhotoErrorCode({ response: { data: { detail: 'SOMETHING_ELSE' } } })).toBeNull();
    expect(extractMerchantPhotoErrorCode(null)).toBeNull();
  });
});
