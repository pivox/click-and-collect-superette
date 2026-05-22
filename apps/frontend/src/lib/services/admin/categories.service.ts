import { apiClient } from '@/lib/api';
import type {
  CategoryListResponse,
  Category,
  CreateCategoryPayload,
  UpdateCategoryPayload,
} from '@/lib/types/admin/referentiel.types';

export async function listCategories(page = 1, limit = 20): Promise<CategoryListResponse> {
  const { data } = await apiClient.get<CategoryListResponse>('/api/admin/categories', {
    params: { page, limit },
  });
  return data;
}

export async function createCategory(payload: CreateCategoryPayload): Promise<Category> {
  const { data } = await apiClient.post<Category>('/api/admin/categories', payload);
  return data;
}

export async function updateCategory(id: string, payload: UpdateCategoryPayload): Promise<Category> {
  const { data } = await apiClient.patch<Category>(`/api/admin/categories/${id}`, payload);
  return data;
}

export async function deleteCategory(id: string): Promise<void> {
  await apiClient.delete(`/api/admin/categories/${id}`);
}
