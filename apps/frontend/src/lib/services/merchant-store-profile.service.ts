import { apiClient } from '@/lib/api';
import { USE_MOCKS, mockDelay } from './index';

export interface MerchantStoreProfile {
  id: string;
  name: string;
  address: string | null;
  city: string | null;
  phone: string | null;
  logoUrl: string | null;
  coverUrl: string | null;
  active: boolean;
}

export interface MerchantStoreProfileUpdate {
  name: string;
  address: string | null;
  city: string | null;
  phone: string | null;
  logoUrl: string | null;
  coverUrl: string | null;
}

interface ApiMerchantStoreProfile {
  id: string;
  name: string;
  address?: string | null;
  city?: string | null;
  phone?: string | null;
  logo_url?: string | null;
  cover_url?: string | null;
  active: boolean;
}

function mapApi(data: ApiMerchantStoreProfile): MerchantStoreProfile {
  return {
    id: data.id,
    name: data.name,
    address: data.address ?? null,
    city: data.city ?? null,
    phone: data.phone ?? null,
    logoUrl: data.logo_url ?? null,
    coverUrl: data.cover_url ?? null,
    active: data.active,
  };
}

let mockProfile: MerchantStoreProfile = {
  id: 'store-1',
  name: 'Supérette Ezzahra',
  address: '12 Avenue Habib Bourguiba',
  city: 'Ezzahra',
  phone: '+216 71 000 000',
  logoUrl: null,
  coverUrl: null,
  active: true,
};

export async function getMerchantStoreProfile(storeId: string): Promise<MerchantStoreProfile> {
  if (USE_MOCKS) return mockDelay({ ...mockProfile });
  const { data } = await apiClient.get<ApiMerchantStoreProfile>(
    `/api/merchant/stores/${storeId}/profile`,
  );
  return mapApi(data);
}

export async function updateMerchantStoreProfile(
  storeId: string,
  input: MerchantStoreProfileUpdate,
): Promise<MerchantStoreProfile> {
  if (USE_MOCKS) {
    mockProfile = { ...mockProfile, ...input };
    return mockDelay({ ...mockProfile });
  }
  const { data } = await apiClient.patch<ApiMerchantStoreProfile>(
    `/api/merchant/stores/${storeId}/profile`,
    {
      name: input.name,
      address: input.address,
      city: input.city,
      phone: input.phone,
      logoUrl: input.logoUrl,
      coverUrl: input.coverUrl,
    },
  );
  return mapApi(data);
}
