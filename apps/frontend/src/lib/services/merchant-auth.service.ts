import { apiClient } from '@/lib/api';
import { decodeJwtPayload } from '@/lib/services/auth.service';
import type {
  MerchantLoginPayload,
  MerchantLoginUser,
  MerchantMe,
} from '@/lib/types/merchant.types';

export async function loginMerchant(payload: MerchantLoginPayload): Promise<MerchantLoginUser> {
  const { data } = await apiClient.post<{ token: string }>('/api/auth/login', payload);
  const jwtPayload = decodeJwtPayload(data.token);
  const roles = Array.isArray(jwtPayload.roles) ? (jwtPayload.roles as string[]) : [];

  if (!roles.includes('ROLE_MERCHANT')) {
    throw new Error('Accès réservé aux marchands');
  }

  return {
    token: data.token,
    email: typeof jwtPayload.email === 'string' ? jwtPayload.email : payload.email,
  };
}

export async function getMerchantMe(): Promise<MerchantMe> {
  const { data } = await apiClient.get<MerchantMe>('/api/merchant/me');
  return data;
}
