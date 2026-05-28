"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { notFound } from "next/navigation";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
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
  const router = useRouter();
  const { user, isLoading: authLoading } = useClientAuth();
  const [order, setOrder] = useState<Order | null>(null);
  const [fetchDone, setFetchDone] = useState(false);
  const [fetchError, setFetchError] = useState(false);

  useEffect(() => {
    if (authLoading || !user) return;
    setFetchError(false);
    getOrder(orderId)
      .then((data) => { setOrder(data); setFetchDone(true); })
      .catch((err) => {
        console.error("[pickup] getOrder failed", { orderId, err });
        setFetchError(true);
        setFetchDone(true);
      });
  }, [orderId, user, authLoading]);

  useEffect(() => {
    if (!fetchDone || !order) return;
    if (order.status !== "ready" && order.status !== "pickup_pending") {
      router.replace(`/orders/${orderId}`);
    }
  }, [fetchDone, order, orderId, router]);

  if (authLoading) return null;

  if (!user) {
    return (
      <>
        <TopBar title="QR code retrait" backHref="/orders" />
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">
            <Link
              href={`/login?redirect=/orders/${orderId}/pickup`}
              className="font-extrabold text-primary"
            >
              Connecte-toi
            </Link>{" "}
            pour accéder à ce QR code.
          </p>
        </Card>
      </>
    );
  }

  if (!fetchDone) return null;

  if (fetchError) {
    return (
      <>
        <TopBar title="QR code retrait" backHref="/orders" />
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">
            Le chargement a échoué. Vérifie ta connexion et réessaie.
          </p>
          <Button
            onClick={() => { setFetchError(false); setFetchDone(false); }}
          >
            Réessayer
          </Button>
        </Card>
      </>
    );
  }

  if (!order) {
    notFound();
    return null;
  }

  const badge = orderStatusBadge(order.status);

  if (order.status !== "ready" && order.status !== "pickup_pending") return null;

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
