import { notFound } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { QrPlaceholder } from "@/components/ui/QrPlaceholder";
import { getOrder } from "@/lib/services";
import { formatTime } from "@/lib/format";

export default async function PickupQrPage({
  params,
}: {
  params: { orderId: string };
}) {
  const order = await getOrder(params.orderId);
  if (!order) notFound();
  const badge = orderStatusBadge(order.status);

  return (
    <>
      <TopBar
        title="QR code retrait"
        subtitle="À présenter au marchand"
        backHref={`/orders/${order.code}`}
      />

      <Card className="text-center">
        <Badge tone={badge.tone}>{badge.label}</Badge>
        <QrPlaceholder code={order.code} className="my-5" />
        <h2 className="m-0 text-h2 font-black">
          Présente ce code au comptoir
        </h2>
        <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
          Le marchand scanne le QR code pour valider la récupération de la commande.
        </p>
        <span className="mt-4 inline-flex rounded-md bg-[#f4f4f0] px-4 py-2.5 font-black tracking-[2px]">
          {order.code}
        </span>
      </Card>

      <Card className="mt-4">
        <Summary>
          <SummaryRow label="Supérette" value="Superette El Amel" />
          <SummaryRow label="Adresse" value="Rue de Carthage, Tunis" />
          <SummaryRow
            label="Créneau"
            value={
              order.pickupSlot
                ? `Aujourd'hui · ${formatTime(order.pickupSlot.startsAt)}`
                : "—"
            }
          />
        </Summary>
      </Card>
    </>
  );
}
