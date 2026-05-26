import { apiClient } from '@/lib/api';
import type {
  CreateClosurePayload,
  MerchantExceptionalClosure,
  MerchantExceptionalClosureCollection,
  PatchClosurePayload,
} from '@/lib/types/merchant-slots.types';

/**
 * Fetch all exceptional closures for a merchant store.
 *
 * @param storeId - The store identifier (UUID).
 * @returns A collection containing the total count and items array.
 */
export async function listMerchantClosures(
  storeId: string,
): Promise<MerchantExceptionalClosureCollection> {
  const { data } = await apiClient.get<MerchantExceptionalClosureCollection>(
    `/api/merchant/stores/${storeId}/exceptional-closures`,
  );
  return data;
}

/**
 * Create a new exceptional closure for a merchant store.
 *
 * @param storeId - The store identifier (UUID).
 * @param payload - The closure creation payload.
 * @returns The created closure object.
 */
export async function createMerchantClosure(
  storeId: string,
  payload: CreateClosurePayload,
): Promise<MerchantExceptionalClosure> {
  const { data } = await apiClient.post<MerchantExceptionalClosure>(
    `/api/merchant/stores/${storeId}/exceptional-closures`,
    payload,
  );
  return data;
}

/**
 * Update an exceptional closure for a merchant store.
 *
 * @param storeId - The store identifier (UUID).
 * @param closureId - The closure identifier (UUID).
 * @param payload - The closure patch payload (partial update).
 * @returns The updated closure object.
 */
export async function patchMerchantClosure(
  storeId: string,
  closureId: string,
  payload: PatchClosurePayload,
): Promise<MerchantExceptionalClosure> {
  const { data } = await apiClient.patch<MerchantExceptionalClosure>(
    `/api/merchant/stores/${storeId}/exceptional-closures/${closureId}`,
    payload,
  );
  return data;
}

/**
 * Delete an exceptional closure for a merchant store.
 *
 * @param storeId - The store identifier (UUID).
 * @param closureId - The closure identifier (UUID).
 */
export async function deleteMerchantClosure(
  storeId: string,
  closureId: string,
): Promise<void> {
  await apiClient.delete(
    `/api/merchant/stores/${storeId}/exceptional-closures/${closureId}`,
  );
}
