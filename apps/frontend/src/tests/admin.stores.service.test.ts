import { describe, it, expect, vi, beforeEach } from 'vitest';
import {
  activateStore,
  deactivateStore,
  getStoreQrCode,
  regenerateStoreQrCode,
} from '@/lib/services/admin/stores.service';
import { apiClient } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  apiClient: {
    patch: vi.fn(),
    get: vi.fn(),
    post: vi.fn(),
  },
}));

const mockPatch = vi.mocked(apiClient.patch);
const mockGet = vi.mocked(apiClient.get);
const mockPost = vi.mocked(apiClient.post);

const STORE_ID = 'store-uuid-1234';

const QR_RESPONSE = {
  store_id: STORE_ID,
  store_name: 'Ma Supérette',
  slug: 'ma-superette',
  qr_code_token: 'tok_abc123',
  target_url: '/api/stores/by-qr/tok_abc123',
};

beforeEach(() => {
  vi.clearAllMocks();
});

describe('activateStore', () => {
  it('sends PATCH to activate endpoint and returns void', async () => {
    mockPatch.mockResolvedValue({ data: undefined });
    await activateStore(STORE_ID);
    expect(mockPatch).toHaveBeenCalledWith(
      `/api/admin/stores/${STORE_ID}/activate`,
      {},
    );
  });
});

describe('deactivateStore', () => {
  it('sends PATCH to deactivate endpoint and returns void', async () => {
    mockPatch.mockResolvedValue({ data: undefined });
    await deactivateStore(STORE_ID);
    expect(mockPatch).toHaveBeenCalledWith(
      `/api/admin/stores/${STORE_ID}/deactivate`,
      {},
    );
  });
});

describe('getStoreQrCode', () => {
  it('sends GET to qr-code endpoint and returns StoreQrCode', async () => {
    mockGet.mockResolvedValue({ data: QR_RESPONSE });
    const result = await getStoreQrCode(STORE_ID);
    expect(mockGet).toHaveBeenCalledWith(`/api/admin/stores/${STORE_ID}/qr-code`);
    expect(result).toEqual(QR_RESPONSE);
  });
});

describe('regenerateStoreQrCode', () => {
  it('sends POST to regenerate-qr endpoint and returns updated StoreQrCode', async () => {
    const newQr = { ...QR_RESPONSE, qr_code_token: 'tok_new456' };
    mockPost.mockResolvedValue({ data: newQr });
    const result = await regenerateStoreQrCode(STORE_ID);
    expect(mockPost).toHaveBeenCalledWith(
      `/api/admin/stores/${STORE_ID}/regenerate-qr`,
      {},
    );
    expect(result.qr_code_token).toBe('tok_new456');
  });
});
