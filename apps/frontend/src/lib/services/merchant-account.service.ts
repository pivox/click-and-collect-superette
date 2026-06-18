import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';

export interface MerchantAccount {
  userId: string;
  email: string;
  name: string;
  firstName: string | null;
  lastName: string | null;
  phone: string | null;
}

export interface MerchantAccountUpdate {
  firstName: string;
  lastName: string;
  phone: string | null;
}

export interface MerchantPasswordChange {
  currentPassword: string;
  newPassword: string;
}

interface ApiMerchantAccount {
  user_id: string;
  email: string;
  name: string;
  first_name: string | null;
  last_name: string | null;
  phone: string | null;
}

function mapApi(data: ApiMerchantAccount): MerchantAccount {
  return {
    userId: data.user_id,
    email: data.email,
    name: data.name,
    firstName: data.first_name ?? null,
    lastName: data.last_name ?? null,
    phone: data.phone ?? null,
  };
}

let mockAccount: MerchantAccount = {
  userId: 'user-1',
  email: 'marchand@kadhia.tn',
  name: 'Marchand Test',
  firstName: 'Marchand',
  lastName: 'Test',
  phone: '+21620111222',
};

export async function updateMerchantAccount(
  input: MerchantAccountUpdate,
): Promise<MerchantAccount> {
  if (USE_MOCKS) {
    const name = [input.firstName, input.lastName].filter(Boolean).join(' ');
    mockAccount = { ...mockAccount, ...input, name };
    return mockDelay({ ...mockAccount });
  }
  const { data } = await apiClient.patch<ApiMerchantAccount>('/api/merchant/me', {
    first_name: input.firstName,
    last_name: input.lastName,
    phone: input.phone,
  });
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
