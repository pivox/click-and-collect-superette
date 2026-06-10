import { apiClient } from '@/lib/api';
import type {
  BrandListResponse,
  Brand,
  CreateBrandPayload,
  UpdateBrandPayload,
} from '@/lib/types/admin/referentiel.types';

export async function listBrands(page = 1, limit = 20): Promise<BrandListResponse> {
  const { data } = await apiClient.get<BrandListResponse>('/api/admin/brands', {
    params: { page, limit },
  });
  return data;
}

export async function createBrand(payload: CreateBrandPayload): Promise<Brand> {
  const { data } = await apiClient.post<Brand>('/api/admin/brands', payload);
  return data;
}

export async function updateBrand(id: string, payload: UpdateBrandPayload): Promise<Brand> {
  const { data } = await apiClient.patch<Brand>(`/api/admin/brands/${id}`, payload);
  return data;
}

export async function deleteBrand(id: string): Promise<void> {
  await apiClient.delete(`/api/admin/brands/${id}`);
}
