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
  getOrderStatus,
  getPickupSession,
} from "@/lib/services";
import { formatSlotRange } from "@/lib/format";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import type {
  CustomerOrderPickupSessionStatus,
  CustomerPickupSessionConfirmation,
  Order,
  PickupSession,
} from "@/types";

const PICKUP_STATUS_POLL_MS = 4000;

function confirmErrorKey(err: unknown): string {
  const status = (err as { response?: { status?: number } }).response?.status;
  if (status === 409) return "client.pickup.confirm409";
  if (status === 404) return "client.pickup.confirm404";
  return "client.pickup.confirmGeneric";
}

async function getPersistedPickupSessionStatus(
  orderId: string,
  orderStatus: Order["status"],
): Promise<CustomerOrderPickupSessionStatus | null> {
  if (orderStatus !== "pickup_pending") return null;

  const statusSnapshot = await getOrderStatus(orderId);
  return statusSnapshot?.pickupSession ?? null;
}

export default function PickupQrPage({
  params,
}: {
  params: { orderId: string };
}) {
  const { orderId } = params;
  const router = useRouter();
  const { user, isLoading: authLoading } = useClientAuth();
  const { t } = useClientLocale();
  const [order, setOrder] = useState<Order | null>(null);
  const [pickupSession, setPickupSession] = useState<PickupSession | null>(null);
  const [pickupSessionStatus, setPickupSessionStatus] =
    useState<CustomerOrderPickupSessionStatus | null>(null);
  const [fetchDone, setFetchDone] = useState(false);
  const [fetchError, setFetchError] = useState(false);
  const [reloadKey, setReloadKey] = useState(0);
  const [isConfirming, setIsConfirming] = useState(false);
  const [confirmError, setConfirmError] = useState<string | null>(null);
  const [confirmationResult, setConfirmationResult] =
    useState<CustomerPickupSessionConfirmation | null>(null);
  const pollingPickupSessionId = pickupSession?.id;
  const pollingPickupSessionExpired = pickupSession?.isExpired ?? false;
  const pollingOrderStatus = order?.status;

  useEffect(() => {
    if (authLoading || !user) return;
    let cancelled = false;

    setFetchError(false);
    setFetchDone(false);
    setOrder(null);
    setPickupSession(null);
    setPickupSessionStatus(null);
    setConfirmError(null);
    setConfirmationResult(null);

    getOrder(orderId)
      .then(async (data) => {
        if (cancelled) return;
        setOrder(data);
        if (data?.status === "ready" || data?.status === "pickup_pending") {
          const [session, sessionStatus] = await Promise.all([
            getPickupSession(orderId),
            getPersistedPickupSessionStatus(orderId, data.status),
          ]);
          if (!cancelled) {
            setPickupSession(session);
            setPickupSessionStatus(sessionStatus);
          }
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
      (pollingOrderStatus !== "ready" && pollingOrderStatus !== "pickup_pending") ||
      !pollingPickupSessionId ||
      (pollingOrderStatus === "ready" && pollingPickupSessionExpired)
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
          const [freshSession, freshSessionStatus] = await Promise.all([
            getPickupSession(orderId),
            getPersistedPickupSessionStatus(orderId, freshOrder.status),
          ]);
          if (!cancelled) {
            setPickupSession(freshSession);
            setPickupSessionStatus(freshSessionStatus);
          }
        } else if (!cancelled) {
          setPickupSession(null);
          setPickupSessionStatus(null);
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
    orderId,
    pollingOrderStatus,
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
      setConfirmError(t(confirmErrorKey(err)));
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
        <TopBar title={t("client.pickup.title")} backHref="/orders" />
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">
            <Link
              href={`/login?redirect=/orders/${orderId}/pickup`}
              className="font-extrabold text-primary"
            >
              {t("client.orders.loginPrompt")}
            </Link>{" "}
            {t("client.pickup.loginToAccess")}
          </p>
        </Card>
      </>
    );
  }

  if (!fetchDone) return null;

  if (fetchError) {
    return (
      <>
        <TopBar title={t("client.pickup.title")} backHref="/orders" />
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">
            {t("client.pickup.loadFailed")}
          </p>
          <Button
            onClick={retryLoad}
          >
            {t("client.pickup.retry")}
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
  const storeName = order.shopName ?? t("client.orders.defaultShop");
  const storeAddress = [order.shopAddress, order.shopCity].filter(Boolean).join(", ") || "—";
  const pickupSlotLabel = order.pickupSlot
    ? formatSlotRange(order.pickupSlot.startsAt, order.pickupSlot.endsAt)
    : "—";

  if (order.status !== "ready" && order.status !== "pickup_pending") return null;

  if (!pickupSession) {
    return (
      <>
        <TopBar title={t("client.pickup.title")} backHref={`/orders/${order.id}`} />
        <Card className="text-center">
          <Badge tone={badge.tone}>{t(badge.labelKey)}</Badge>
          <h2 className="mt-4 text-h2 font-black">
            {t("client.pickup.qrUnavailableTitle")}
          </h2>
          <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
            {t("client.pickup.qrUnavailableBody")}
          </p>
          <Button className="mt-4" onClick={retryLoad}>
            {t("client.pickup.retry")}
          </Button>
        </Card>
      </>
    );
  }

  const displayedStatus = confirmationResult?.orderStatus ?? order.status;
  const displayedBadge = orderStatusBadge(displayedStatus);
  const customerConfirmed = Boolean(
    confirmationResult?.customerConfirmedAt || pickupSessionStatus?.customerConfirmed,
  );
  const waitingForMerchantConfirmation = customerConfirmed && !confirmationResult?.isCompleted;

  return (
    <>
      <TopBar
        title={t("client.pickup.title")}
        subtitle={t("client.pickup.subtitle")}
        backHref={`/orders/${order.id}`}
      />

      <Card className="text-center">
        <Badge tone={displayedBadge.tone}>{t(displayedBadge.labelKey)}</Badge>
        {confirmationResult?.isCompleted ? (
          <>
            <h2 className="mt-5 text-h2 font-black">
              {t("client.pickup.completedTitle")}
            </h2>
            <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
              {t("client.pickup.completedBody")}
            </p>
          </>
        ) : order.status === "pickup_pending" ? (
          <>
            <h2 className="mt-5 text-h2 font-black">
              {t("client.pickup.scannedTitle")}
            </h2>
            <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
              {t("client.pickup.scannedBody")}
            </p>
            <Button
              className="mt-5"
              full
              onClick={handleCustomerConfirm}
              disabled={isConfirming || customerConfirmed}
            >
              {customerConfirmed
                ? t("client.pickup.confirmed")
                : isConfirming
                  ? t("client.pickup.validating")
                  : t("client.pickup.confirmReceived")}
            </Button>
            {waitingForMerchantConfirmation && (
              <p className="mx-auto mt-3 max-w-xs text-xs font-bold text-primary leading-relaxed">
                {t("client.pickup.waitingMerchant")}
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
              {t("client.pickup.expiredTitle")}
            </h2>
            <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
              {t("client.pickup.expiredBody")}
            </p>
          </>
        ) : (
          <>
            <QrPlaceholder code={pickupSession.qrPayload} className="my-5" />
            <h2 className="m-0 text-h2 font-black">
              {t("client.pickup.presentTitle")}
            </h2>
            <p className="mx-auto mt-2 max-w-xs text-xs text-muted leading-relaxed">
              {t("client.pickup.presentBody")}
            </p>
            <div className="mx-auto mt-4 max-w-full rounded-md bg-[#f4f4f0] px-3 py-2 text-left">
              <p className="m-0 text-[11px] font-bold uppercase text-muted">
                {t("client.pickup.qrToken")}
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
          <SummaryRow label={t("client.pickup.rowOrder")} value={order.code} />
          <SummaryRow label={t("client.pickup.rowShop")} value={storeName} />
          <SummaryRow label={t("client.pickup.rowAddress")} value={storeAddress} />
          <SummaryRow label={t("client.pickup.rowSlot")} value={pickupSlotLabel} />
        </Summary>
      </Card>
    </>
  );
}
