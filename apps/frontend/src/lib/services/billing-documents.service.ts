import { apiClient } from '@/lib/api';
import type {
  AdminBillingDocumentWhatsappContact,
  BillingDocument,
  BillingDocumentListResponse,
} from '@/lib/types/billing-documents.types';

interface ListBillingDocumentsOptions {
  page?: number;
  limit?: number;
}

export async function listAdminBillingDocuments(
  options: ListBillingDocumentsOptions = {},
): Promise<BillingDocumentListResponse> {
  const { data } = await apiClient.get<BillingDocumentListResponse>('/api/admin/billing-documents', {
    params: {
      page: options.page ?? 1,
      limit: options.limit ?? 20,
    },
  });

  return data;
}

export async function getAdminBillingDocument(documentId: string): Promise<BillingDocument> {
  const { data } = await apiClient.get<BillingDocument>(
    `/api/admin/billing-documents/${documentId}`,
  );

  return data;
}

export async function openAdminBillingDocumentWhatsappContact(
  documentId: string,
): Promise<AdminBillingDocumentWhatsappContact> {
  const { data } = await apiClient.post<AdminBillingDocumentWhatsappContact>(
    `/api/admin/billing-documents/${documentId}/whatsapp-contact`,
  );

  return data;
}

export async function listMerchantBillingDocuments(
  options: ListBillingDocumentsOptions = {},
): Promise<BillingDocumentListResponse> {
  const { data } = await apiClient.get<BillingDocumentListResponse>('/api/merchant/billing-documents', {
    params: {
      page: options.page ?? 1,
      limit: options.limit ?? 20,
    },
  });

  return data;
}

export async function getMerchantBillingDocument(documentId: string): Promise<BillingDocument> {
  const { data } = await apiClient.get<BillingDocument>(
    `/api/merchant/billing-documents/${documentId}`,
  );

  return data;
}
