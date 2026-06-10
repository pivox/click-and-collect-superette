import { apiClient } from '@/lib/api'
import type { MerchantStatistics } from '@/lib/types/merchant-stats.types'

export async function getMerchantStatistics(
  storeId: string,
  dateFrom?: string,
  dateTo?: string,
): Promise<MerchantStatistics> {
  const params = new URLSearchParams()
  if (dateFrom) params.append('date_from', dateFrom)
  if (dateTo) params.append('date_to', dateTo)

  const url = `/api/merchant/stores/${storeId}/statistics${params.toString() ? `?${params}` : ''}`
  const { data } = await apiClient.get<MerchantStatistics>(url)

  return data
}
