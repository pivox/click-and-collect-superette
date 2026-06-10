'use client'

import { useCallback, useEffect, useState } from 'react'
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext'
import { formatTnd } from '@/lib/format'
import { getMerchantStatistics } from '@/lib/services/merchant-stats.service'
import { Card } from '@/components/ui/Card'
import { Badge } from '@/components/ui/Badge'
import type { MerchantStatistics } from '@/lib/types/merchant-stats.types'

export default function MerchantStatisticsPage() {
  const { merchant } = useMerchantAuth()
  const storeId = merchant?.store?.id

  const [stats, setStats] = useState<MerchantStatistics | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState<string | null>(null)
  const [period, setPeriod] = useState<'7d' | '30d' | '90d'>('30d')

  const daysOffset = period === '7d' ? 7 : period === '30d' ? 30 : 90

  // Format dates in Tunis timezone instead of UTC to avoid off-by-one at midnight
  const formatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Africa/Tunis',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  })

  const today = new Date()
  const dateFromDate = new Date(today)
  dateFromDate.setDate(dateFromDate.getDate() - daysOffset)

  const dateFromStr = formatter.format(dateFromDate)
  const dateTo = formatter.format(today)

  const load = useCallback(async () => {
    if (!storeId) return

    try {
      setLoading(true)
      setError(null)
      const data = await getMerchantStatistics(storeId, dateFromStr, dateTo)
      setStats(data)
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Erreur lors du chargement')
    } finally {
      setLoading(false)
    }
  }, [storeId, dateFromStr, dateTo])

  useEffect(() => {
    load()
  }, [load])

  if (!merchant?.store) return null

  return (
    <div className="space-y-6">
      <div>
        <h1 className="text-h1 font-extrabold">Statistiques</h1>
        <p className="text-sm text-muted">Suivi de performance</p>
      </div>

      {/* Period selector */}
      <div className="flex gap-2">
        {(['7d', '30d', '90d'] as const).map((p) => (
          <button
            key={p}
            onClick={() => setPeriod(p)}
            className={`px-3 py-1.5 rounded text-sm font-semibold ${
              period === p
                ? 'bg-primary text-white'
                : 'border border-line bg-white hover:bg-soft'
            }`}
          >
            {p === '7d' ? '7 jours' : p === '30d' ? '30 jours' : '90 jours'}
          </button>
        ))}
      </div>

        {error && <div className="text-red-600 text-sm">{error}</div>}

        {loading ? (
          <div className="text-center text-muted text-sm">Chargement...</div>
        ) : stats ? (
          <>
            {/* Main metrics cards */}
            <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <Card>
                <div className="text-sm text-muted mb-1">Commandes</div>
                <div className="text-2xl font-extrabold">{stats.total_orders}</div>
              </Card>

              <Card>
                <div className="text-sm text-muted mb-1">CA estimé</div>
                <div className="text-2xl font-extrabold">{formatTnd(stats.total_revenue_tnd)}</div>
              </Card>

              <Card>
                <div className="text-sm text-muted mb-1">Taux d&apos;acceptation</div>
                <div className="text-2xl font-extrabold">{(stats.acceptance_rate * 100).toFixed(1)}%</div>
              </Card>

              <Card>
                <div className="text-sm text-muted mb-1">Taux d&apos;annulation</div>
                <div className="text-2xl font-extrabold">{(stats.cancellation_rate * 100).toFixed(1)}%</div>
              </Card>
            </div>

            {/* Top products */}
            {stats.top_products && stats.top_products.length > 0 && (
              <Card className="p-5">
                <h3 className="text-sm font-extrabold mb-3">Produits les plus demandés</h3>
                <div className="space-y-2.5">
                  {stats.top_products.map((product, idx) => (
                    <div key={idx} className="flex items-center justify-between text-sm">
                      <div className="flex-1 min-w-0">
                        <div className="font-semibold truncate">{product.name_fr}</div>
                        <div className="text-xs text-muted">{product.total_quantity} unité(s)</div>
                      </div>
                      <div className="flex-shrink-0 ml-2 text-right">
                        <div className="font-semibold">{formatTnd(product.total_revenue_tnd)}</div>
                        <div
                          className="h-1.5 bg-soft rounded mt-1"
                          style={{
                            width: `${
                              (parseInt(product.total_revenue_tnd) /
                                parseInt(stats.top_products[0]?.total_revenue_tnd || '1')) *
                              60
                            }px`,
                          }}
                        />
                      </div>
                    </div>
                  ))}
                </div>
              </Card>
            )}

            {/* Top slots */}
            {stats.top_slots && stats.top_slots.length > 0 && (
              <Card className="p-5">
                <h3 className="text-sm font-extrabold mb-3">Créneaux les plus utilisés</h3>
                <div className="space-y-2">
                  {stats.top_slots.map((slot, idx) => (
                    <div key={idx} className="flex items-center justify-between text-sm p-2 bg-soft rounded">
                      <div>
                        <div className="font-semibold">
                          {new Date(slot.starts_at).toLocaleTimeString('fr-FR', {
                            hour: '2-digit',
                            minute: '2-digit',
                          })}{' '}
                          -{' '}
                          {new Date(slot.ends_at).toLocaleTimeString('fr-FR', {
                            hour: '2-digit',
                            minute: '2-digit',
                          })}
                        </div>
                      </div>
                      <Badge>{slot.order_count} commandes</Badge>
                    </div>
                  ))}
                </div>
              </Card>
            )}
          </>
        ) : (
          <div className="text-center text-muted text-sm">Aucune donnée</div>
        )}
    </div>
  )
}
