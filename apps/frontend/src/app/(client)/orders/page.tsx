import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { formatTnd } from "@/lib/format";
import { MOCK_ORDER } from "@/lib/mock/orders.mock";

/**
 * Orders list — placeholder for now (one demo order). When the API is
 * wired up, this becomes `await listOrders()` instead.
 */
export default function OrdersListPage() {
  const orders = [MOCK_ORDER];
  return (
    <>
      <TopBar title="Mes commandes" subtitle="Historique et en cours" backHref="/" />
      <div className="grid gap-2.5">
        {orders.map((o) => {
          const badge = orderStatusBadge(o.status);
          return (
            <Link key={o.id} href={`/orders/${o.code}`}>
              <Card compact className="hover:bg-soft transition-colors">
                <div className="flex items-baseline justify-between">
                  <strong className="text-sm">{o.code}</strong>
                  <Badge tone={badge.tone}>{badge.label}</Badge>
                </div>
                <div className="mt-2 flex items-baseline justify-between text-xs text-muted">
                  <span>Superette El Amel</span>
                  <span className="font-black text-ink">
                    {formatTnd(o.totalAmountTnd)}
                  </span>
                </div>
              </Card>
            </Link>
          );
        })}
      </div>
    </>
  );
}
