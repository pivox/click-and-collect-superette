import { beforeEach, describe, expect, it, vi } from 'vitest';
import { apiClient } from '@/lib/api';
import {
  completeMerchantInvitation,
  verifyMerchantInvitation,
} from '@/lib/services/merchant-invitation.service';

vi.mock('@/lib/api', () => ({
  apiClient: { post: vi.fn() },
}));

describe('merchant invitation service', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('vérifie une invitation marchand sans redirection auth globale', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({
      data: { status: 'valid', expires_at: '2026-06-26T08:00:00+00:00' },
    });

    await expect(verifyMerchantInvitation('raw-token')).resolves.toEqual({
      status: 'valid',
      expiresAt: '2026-06-26T08:00:00+00:00',
    });
    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/auth/merchant-invitations/verify',
      { token: 'raw-token' },
      { skipAuthRedirect: true },
    );
  });

  it('finalise une invitation marchand avec le payload backend attendu', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({ data: {} });

    await completeMerchantInvitation({
      token: 'raw-token',
      newPassword: 'definitiveSecret456',
      newPasswordConfirmation: 'definitiveSecret456',
    });

    expect(apiClient.post).toHaveBeenCalledWith(
      '/api/auth/merchant-invitations/complete',
      {
        token: 'raw-token',
        new_password: 'definitiveSecret456',
        new_password_confirmation: 'definitiveSecret456',
      },
      { skipAuthRedirect: true },
    );
  });
});
