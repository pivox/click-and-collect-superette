export interface MerchantStatisticsTopProduct {
  name_fr: string
  name_ar?: string
  total_quantity: number
  total_revenue_tnd: string
}

export interface MerchantStatisticsTopSlot {
  starts_at: string
  ends_at: string
  order_count: number
}

export interface MerchantStatistics {
  id: string
  store_id: string
  date_from: string
  date_to: string
  total_orders: number
  total_revenue_tnd: string
  acceptance_rate: number
  cancellation_rate: number
  rejection_rate: number
  orders_by_status: Record<string, number>
  top_products: MerchantStatisticsTopProduct[]
  top_slots: MerchantStatisticsTopSlot[]
}
