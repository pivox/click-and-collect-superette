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
import {
  confirmCustomerPickupSession,
  getOrder,
  getPickupSession,
} from "@/lib/services";
import { formatSlotRange } from "@/lib/format";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import type {
  CustomerPickupSessionConfirmation,
  Order,
  PickupSession,
} from "@/types";

const PICKUP_STATUS_POLL_MS = 4000;

function confirmErrorMessage(err: unknown): string {
  const status = (err as { response?: { status?: number } }).response?.status;
  if (status === 409) {
    return "La validation n'est pas encore possible. Vérifie que le marchand a bien scanné le QR code.";
  }
  if (status === 404) {
    return "Cette session de retrait est introuvable. Recharge la page.";
  }
  return "La validation a échoué. Réessaie dans un instant.";
}

export default function PickupQrPage({
  params,
}: {
  params: { orderId: string };
}) {
  const { orderId } = params;
  const router = useRouter();
  const { user, isLoading: authLoading } = useClientAuth();
  const [order, setOrder] = useState<Order | null>(null);
  const [pickupSession, setPickupSession] = useState<PickupSession | null>(null);
  const [fetchDone, setFetchDone] = useState(false);
  const [fetchError, setFetchError] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);
  const [isConfirming, setIsConfirming] = useState(false);
  const [confirmError, setConfirmError] = useState<string | null>(null);
  const [confirmationResult, setConfirmationResult] =
    useState<CustomerPickupSessionConfirmation | null>(null);
  const pollingPickupSessionId = pickupSession?.id;
  const pollingPickupSessionExpired = pickupSession?.isExpired ?? false;

  useEffect(() => {
    if (authLoading || !user) return;
    let cancelled = false;

    setFetchError(false);
    setFetchDone(false);
    setOrder(null);
    setPickupSession(null);
    setConfirmError(null);
    setConfirmationResult(null);

    getOrder(orderId)
      .then(async (data) => {
        if (cancelled) return;
        setOrder(data);
        if (data?.status === "ready" || data?.status === "pickup_pending") {
          const session = await getPickupSession(orderId);
          if (!cancelled) setPickupSession(session);
        }
      })
      .catch((err) => {
        if (cancelled) return;
        console.error("[pickup] getOrder failed", { orderId, err });
        setFetchError(true);
      })
      .finally(() => {
        if (!cancelled) {
          setFetchDone(true);
        }
      });

    return () => {
      cancelled = true;
    };
  }, [orderId, user, authLoading, reloadKey]);

  useEffect(() => {
    if (
      authLoading ||
      !user ||
      !fetchDone ||
      fetchError ||
      order?.status !== "ready" ||
      !pollingPickupSessionId ||
      pollingPickupSessionExpired
    ) {
      return;
    }

    let cancelled = false;
    const refreshAfterScan = async () => {
      try {
        const freshOrder = await getOrder(orderId);
        if (cancelled) return;

        setOrder(freshOrder);
        if (freshOrder?.status === "ready" || freshOrder?.status === "pickup_pending") {
          const freshSession = await getPickupSession(orderId);
          if (!cancelled) setPickupSession(freshSession);
        } else if (!cancelled) {
          setPickupSession(null);
        }
      } catch (err) {
        console.error("[pickup] refresh after scan failed", { orderId, err });
      }
    };

    const intervalId = window.setInterval(refreshAfterScan, PICKUP_STATUS_POLL_MS);
    return () => {
      cancelled = true;
      window.clearInterval(intervalId);
    };
  }, [
    authLoading,
    fetchDone,
    fetchError,
    order?.status,
    orderId,
    pollingPickupSessionExpired,
    pollingPickupSessionId,
    user,
  ]);

  async function handleCustomerConfirm() {
    if (!pickupSession) return;
    setIsConfirming(true);
    setConfirmError(null);
    try {
      const result = await confirmCustomerPickupSession(pickupSession.id);
      setConfirmationResult(result);
    } catch (err) {
      setConfirmError(confirmErrorMessage(err));
    } finally {
      setIsConfirming(false);
    }
  }

  function retryLoad() {
    setReloadKey((key) => key + 1);
  }

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
            onClick={retryLoad}
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
  const storeName = order.shopName ?? "Supérette";
  const storeAddress = [order.shopAddress, order.shopCity].filter(Boolean).join(", ") || "—";
  const pickupSlotLabel = order.pickupSlot
    ? formatSlotRange(order.pickupSlot.startsAt, order.pickupSlot.endsAt)
    : "—";

  if (order.status !== "ready" && order.status !== "pickup_pending") return null;

  if (!pickupSession) {
    return (
      <>
        <TopBar title="QR code retrait" backHref={`/orders/${order.id}`} />
        <Card className="text-center">
          <Badge tone={badge.tone}>{badge.label}</Badge>
          <h2 className="mt-4 text-h2 font-black">
            QR code de retrait indisponible
          </h2>
          <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
            La session de retrait n&apos;a pas pu être chargée.
          </p>
          <Button className="mt-4" onClick={retryLoad}>
            Réessayer
          </Button>
        </Card>
      </>
    );
  }

  const displayedStatus = confirmationResult?.orderStatus ?? order.status;
  const displayedBadge = orderStatusBadge(displayedStatus);
  const customerConfirmed = Boolean(confirmationResult?.customerConfirmedAt);

  return (
    <>
      <TopBar
        title="QR code retrait"
        subtitle="À présenter au marchand"
        backHref={`/orders/${order.id}`}
      />

      <Card className="text-center">
        <Badge tone={displayedBadge.tone}>{displayedBadge.label}</Badge>
        {confirmationResult?.isCompleted ? (
          <>
            <h2 className="mt-5 text-h2 font-black">
              Retrait finalisé
            </h2>
            <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
              La remise de ta Kadhia est confirmée côté client et marchand.
            </p>
          </>
        ) : order.status === "pickup_pending" ? (
          <>
            <h2 className="mt-5 text-h2 font-black">
              Retrait scanné par le marchand
            </h2>
            <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
              Confirme la réception une fois la Kadhia remise au comptoir.
            </p>
            <Button
              className="mt-5"
              full
              onClick={handleCustomerConfirm}
              disabled={isConfirming || customerConfirmed}
            >
              {customerConfirmed
                ? "Réception confirmée"
                : isConfirming
                  ? "Validation..."
                  : "J'ai récupéré ma Kadhia"}
            </Button>
            {confirmationResult && !confirmationResult.isCompleted && (
              <p className="mx-auto mt-3 max-w-xs text-xs font-bold text-primary leading-relaxed">
                Confirmation client enregistrée. En attente de la validation marchand.
              </p>
            )}
            {confirmError && (
              <p className="mx-auto mt-3 max-w-xs text-xs font-bold text-danger leading-relaxed">
                {confirmError}
              </p>
            )}
          </>
        ) : pickupSession.isExpired ? (
          <>
            <h2 className="mt-5 text-h2 font-black">
              QR code expiré
            </h2>
            <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
              Ce QR code a expiré. Contacte la supérette.
            </p>
          </>
        ) : (
          <>
            <QrPlaceholder code={pickupSession.qrPayload} className="my-5" />
            <h2 className="m-0 text-h2 font-black">
              Présente ce QR code au comptoir
            </h2>
            <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
              Le marchand scanne ce QR code pour démarrer la validation du retrait.
            </p>
            <div className="mx-auto mt-4 max-w-full rounded-md bg-[#f4f4f0] px-3 py-2 text-left">
              <p className="m-0 text-[11px] font-bold uppercase text-muted">
                Token QR
              </p>
              <p className="m-0 mt-1 break-all font-mono text-xs font-black">
                {pickupSession.token}
              </p>
            </div>
          </>
        )}
      </Card>

      <Card className="mt-4">
        <Summary>
          <SummaryRow label="Commande" value={order.code} />
          <SummaryRow label="Supérette" value={storeName} />
          <SummaryRow label="Adresse" value={storeAddress} />
          <SummaryRow label="Créneau" value={pickupSlotLabel} />
        </Summary>
      </Card>
    </>
  );
}
