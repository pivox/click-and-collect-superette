import { describe, it, expect, vi, beforeEach } from 'vitest';
import { requestPasswordReset, confirmPasswordReset } from '@/lib/services/auth.service';
import { apiClient } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  apiClient: { post: vi.fn() },
}));

describe('requestPasswordReset', () => {
  beforeEach(() => vi.clearAllMocks());

  it('POSTs to /api/auth/forgot-password with email', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: {} });
    await requestPasswordReset('client@kadhia.tn');
    expect(apiClient.post).toHaveBeenCalledWith('/api/auth/forgot-password', {
      email: 'client@kadhia.tn',
    });
  });

  it('resolves even if the email does not exist (API always 200)', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: {} });
    await expect(requestPasswordReset('unknown@kadhia.tn')).resolves.toBeUndefined();
  });

  it('propagates network errors', async () => {
    vi.mocked(apiClient.post).mockRejectedValue(new Error('Network Error'));
    await expect(requestPasswordReset('x@y.tn')).rejects.toThrow('Network Error');
  });
});

describe('confirmPasswordReset', () => {
  beforeEach(() => vi.clearAllMocks());

  it('POSTs to /api/auth/reset-password with token and new_password', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: {} });
    await confirmPasswordReset('MY_TOKEN', 'newPassword123');
    expect(apiClient.post).toHaveBeenCalledWith('/api/auth/reset-password', {
      token: 'MY_TOKEN',
      new_password: 'newPassword123',
    });
  });

  it('throws on 422 (invalid/expired token)', async () => {
    const err = Object.assign(new Error('Unprocessable'), { response: { status: 422 } });
    vi.mocked(apiClient.post).mockRejectedValue(err);
    await expect(confirmPasswordReset('BAD_TOKEN', 'pass')).rejects.toMatchObject({
      response: { status: 422 },
    });
  });
});
