import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';

export interface MerchantAccount {
  userId: string;
  email: string;
  name: string;
  roles: string[];
}

export interface MerchantAccountUpdate {
  name: string;
  email: string;
}

export interface MerchantPasswordChange {
  currentPassword: string;
  newPassword: string;
}

interface ApiMerchantAccount {
  user_id: string;
  email: string;
  name: string;
  roles: string[];
  // Re-issued JWT, returned only when the email changed (the JWT identity claim).
  token?: string;
}

function mapApi(data: ApiMerchantAccount): MerchantAccount {
  return {
    userId: data.user_id,
    email: data.email,
    name: data.name,
    roles: data.roles ?? [],
  };
}

let mockAccount: MerchantAccount = {
  userId: 'user-1',
  email: 'marchand@kadhia.tn',
  name: 'Marchand Test',
  roles: ['ROLE_MERCHANT'],
};

export async function updateMerchantAccount(
  input: MerchantAccountUpdate,
): Promise<MerchantAccount> {
  if (USE_MOCKS) {
    mockAccount = { ...mockAccount, name: input.name, email: input.email };
    return mockDelay({ ...mockAccount });
  }
  const { data } = await apiClient.patch<ApiMerchantAccount>('/api/merchant/me', {
    name: input.name,
    email: input.email,
  });
  // When the email changed the backend re-issues a JWT (identity claim = email).
  // Swap the stored token before the caller refreshes the context, otherwise the
  // next request would 401 with the now-stale token.
  if (data.token && typeof window !== 'undefined') {
    localStorage.setItem('merchant_token', data.token);
    document.cookie = `merchant_token=${data.token}; path=/merchant; SameSite=Lax; Max-Age=${60 * 60 * 8}`;
  }
  return mapApi(data);
}

export async function changeMerchantPassword(input: MerchantPasswordChange): Promise<void> {
  if (USE_MOCKS) {
    await mockDelay(null);
    return;
  }
  await apiClient.patch('/api/merchant/me/password', {
    current_password: input.currentPassword,
    new_password: input.newPassword,
  });
}
