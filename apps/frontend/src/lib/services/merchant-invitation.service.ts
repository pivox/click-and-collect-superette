import { apiClient } from '@/lib/api';

export interface MerchantInvitationVerification {
  status: 'valid';
  expiresAt: string;
}

interface ApiMerchantInvitationVerification {
  status: 'valid';
  expires_at: string;
}

export interface MerchantInvitationCompleteInput {
  token: string;
  newPassword: string;
  newPasswordConfirmation: string;
}

export async function verifyMerchantInvitation(
  token: string,
): Promise<MerchantInvitationVerification> {
  const { data } = await apiClient.post<ApiMerchantInvitationVerification>(
    '/api/auth/merchant-invitations/verify',
    { token },
    { skipAuthRedirect: true },
  );

  return {
    status: data.status,
    expiresAt: data.expires_at,
  };
}

export async function completeMerchantInvitation(
  input: MerchantInvitationCompleteInput,
): Promise<void> {
  await apiClient.post(
    '/api/auth/merchant-invitations/complete',
    {
      token: input.token,
      new_password: input.newPassword,
      new_password_confirmation: input.newPasswordConfirmation,
    },
    { skipAuthRedirect: true },
  );
}
