"use client";

import { useEffect, useState } from "react";
import { notFound, redirect } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { QrPlaceholder } from "@/components/ui/QrPlaceholder";
import { getOrder } from "@/lib/services";
import { formatTime } from "@/lib/format";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import type { Order } from "@/types";

export default function PickupQrPage({
  params,
}: {
  params: { orderId: string };
}) {
  const { orderId } = params;
  const { user, isLoading: authLoading } = useClientAuth();
  const [order, setOrder] = useState<Order | null>(null);
  const [fetchDone, setFetchDone] = useState(false);

  useEffect(() => {
    if (authLoading || !user) return;
    void getOrder(orderId)
      .then((data) => { setOrder(data); setFetchDone(true); })
      .catch(() => setFetchDone(true));
  }, [orderId, user, authLoading]);

  if (authLoading) return null;

  if (!user) {
    redirect(`/login?redirect=/orders/${orderId}/pickup`);
  }

  if (!fetchDone) return null;
  if (!order) notFound();

  if (order.status !== "ready" && order.status !== "pickup_pending") {
    redirect(`/orders/${orderId}`);
  }

  const badge = orderStatusBadge(order.status);

  return (
    <>
      <TopBar
        title="QR code retrait"
        subtitle="À présenter au marchand"
        backHref={`/orders/${order.id}`}
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
