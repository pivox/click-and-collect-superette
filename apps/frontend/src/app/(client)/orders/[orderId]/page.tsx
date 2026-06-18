"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { notFound } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { Timeline } from "@/components/ui/Timeline";
import { Button, getButtonClassName } from "@/components/ui/Button";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { getOrder, projectTimeline, cancelOrder } from "@/lib/services";
import { formatTnd, formatSlotRange } from "@/lib/format";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import type { Order } from "@/types";

function renderPickupAction(order: Order, t: (key: string) => string) {
  if (order.status === "partially_accepted") return null;

  if (order.status === "ready" || order.status === "pickup_pending") {
    return (
      <Link
        href={`/orders/${order.id}/pickup`}
        className={getButtonClassName({ full: true })}
      >
        {t("client.orders.showPickupQr")}
      </Link>
    );
  }

  if (order.status === "completed") {
    return (
      <Button full disabled>
        {t("client.orders.pickupDone")}
      </Button>
    );
  }

  return (
    <Button full disabled>
      {t("client.orders.pickupWhenReady")}
    </Button>
  );
}

export default function OrderTrackingPage({
  params,
}: {
  params: { orderId: string };
}) {
  const { orderId } = params;
  const { user, isLoading: authLoading } = useClientAuth();
  const { locale, t } = useClientLocale();
  const [order, setOrder] = useState<Order | null>(null);
  const [fetchDone, setFetchDone] = useState(false);
  const [refreshing, setRefreshing] = useState(false);
  const [refreshError, setRefreshError] = useState<string | null>(null);
  const [cancelling, setCancelling] = useState(false);
  const [cancelError, setCancelError] = useState<string | null>(null);

  useEffect(() => {
    if (authLoading || !user) return;
    void getOrder(orderId)
      .then((data) => { setOrder(data); setFetchDone(true); })
      .catch(() => setFetchDone(true));
  }, [orderId, user, authLoading]);

  const handleCancel = async () => {
    if (!order) return;
    if (!window.confirm(t("client.orders.cancelConfirm"))) return;
    setCancelling(true);
    setCancelError(null);
    try {
      const updated = await cancelOrder(order.id);
      setOrder(updated);
    } catch (err) {
      const status = (err as { response?: { status?: number } }).response?.status;
      if (status === 409) {
        setCancelError(t("client.orders.cancel409"));
      } else {
        setCancelError(t("client.orders.cancelError"));
      }
    } finally {
      setCancelling(false);
    }
  };

  const handleRefresh = async () => {
    setRefreshing(true);
    setRefreshError(null);
    try {
      const fresh = await getOrder(orderId);
      if (fresh) setOrder(fresh);
    } catch {
      setRefreshError(t("client.orders.refreshError"));
    } finally {
      setRefreshing(false);
    }
  };

  if (authLoading) return null;

  if (!user) {
    return (
      <>
        <TopBar title={t("client.orders.trackTitle")} backHref="/orders" />
        <Card className="text-center">
          <p className="py-4 text-sm text-muted">
            <Link
              href={`/login?redirect=/orders/${orderId}`}
              className="font-extrabold text-primary"
            >
              {t("client.orders.loginPrompt")}
            </Link>{" "}
            {t("client.orders.loginToConsult")}
          </p>
        </Card>
      </>
    );
  }

  if (!fetchDone) return null;
  if (!order) notFound();

  const badge = orderStatusBadge(order.status);
  const steps = projectTimeline(order).map((s) => ({
    ...s,
    label: t(`client.timeline.${s.key}.label`),
    hint: t(`client.timeline.${s.key}.hint`),
  }));
  const storeName = order.shopName ?? t("client.orders.defaultShop");
  const pickupSlotLabel = order.pickupSlot
    ? formatSlotRange(order.pickupSlot.startsAt, order.pickupSlot.endsAt)
    : "—";
  const kadhiaHref = order.kadhiaId
    ? `/kadhia/${order.kadhiaId}?context=partially_accepted`
    : "/kadhia";
  const rejectedLines = order.rejectedLines ?? [];
  const merchantNoteTitle = locale === "ar" ? "ملاحظة التاجر" : "Note du marchand";
  const showMerchantNote = Boolean(order.rejectionReason) && order.status !== "partially_accepted";

  return (
    <>
      <TopBar
        title={order.code}
        subtitle={storeName}
        backHref="/orders"
      />

      {order.status === "partially_accepted" && (
        <section className="mb-4 rounded-lg border px-4 py-3" style={{ background: "var(--status-wait-bg)", borderColor: "var(--status-wait)" }}>
          <h2 className="text-sm font-bold" style={{ color: "var(--status-wait)" }}>
            {t("client.orders.partialTitle")}
          </h2>
          <p className="mt-1 text-sm" style={{ color: "var(--status-wait)" }}>
            {t("client.orders.partialBody")}
          </p>
          {order.rejectionReason && (
            <p className="mt-3 rounded-md bg-white/70 px-3 py-2 text-sm text-ink">
              <span className="font-bold">{t("client.orders.partialReason")}</span>{" "}
              {order.rejectionReason}
            </p>
          )}
          {rejectedLines.length > 0 && (
            <div className="mt-3">
              <p className="text-xs font-extrabold uppercase text-muted">
                {t("client.orders.rejectedItems")}
              </p>
              <ul className="mt-2 grid gap-2">
                {rejectedLines.map((line) => (
                  <li
                    key={line.id}
                    className="flex items-center justify-between gap-3 rounded-md bg-white/70 px-3 py-2 text-sm"
                  >
                    <span className="font-bold text-ink">{line.productOffer.nameFr}</span>
                    <span className="shrink-0 text-muted">x{line.quantity}</span>
                  </li>
                ))}
              </ul>
            </div>
          )}
        </section>
      )}

      <div className="md:grid md:grid-cols-2 md:gap-5 md:items-start">
        {/* Colonne gauche : timeline */}
        <div>
          <Card>
            <Badge tone={badge.tone}>{t(badge.labelKey)}</Badge>
            <div className="mt-3">
              <Summary>
                <SummaryRow
                  label={t("client.orders.pickup")}
                  value={pickupSlotLabel}
                />
                <SummaryRow
                  label={t("client.orders.total")}
                  value={formatTnd(order.totalAmountTnd)}
                />
                <SummaryRow label={t("client.orders.code")} value={order.code} />
              </Summary>
            </div>
          </Card>

          {order.status === "submitted" && (
            <section className="mt-4">
              {cancelError && (
                <p className="mb-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
                  {cancelError}
                </p>
              )}
              <button
                type="button"
                onClick={() => void handleCancel()}
                disabled={cancelling}
                className="text-sm text-red-500 underline disabled:opacity-50"
              >
                {cancelling ? t("client.orders.cancelling") : t("client.orders.cancel")}
              </button>
            </section>
          )}

          <section className="mt-4">
            <div className="mb-2.5 flex items-center justify-between">
              <h3 className="text-h3 font-extrabold">{t("client.orders.tracking")}</h3>
              <button
                type="button"
                onClick={handleRefresh}
                disabled={refreshing}
                className="text-xs font-extrabold text-primary underline disabled:opacity-50"
              >
                {refreshing ? t("client.orders.refreshing") : t("client.orders.refresh")}
              </button>
            </div>
            {refreshError && (
              <p className="mb-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
                {refreshError}
              </p>
            )}
            <Card>
              <Timeline steps={steps} />
            </Card>
          </section>
        </div>

        {/* Colonne droite : notes + CTA */}
        <div>
          {order.customerNote && (
            <section className="mt-4 md:mt-0">
              <h3 className="mb-2.5 text-h3 font-extrabold">{t("client.orders.yourNote")}</h3>
              <Card className="text-sm text-muted">{order.customerNote}</Card>
            </section>
          )}

          {showMerchantNote && (
            <section className="mt-4">
              <h3 className="mb-2.5 text-h3 font-extrabold">{merchantNoteTitle}</h3>
              <Card className="text-sm text-muted">{order.rejectionReason}</Card>
            </section>
          )}

          {order.status === "ready" && order.pickupCode && (
            <section className="mt-4">
              <h3 className="mb-2.5 text-h3 font-extrabold">{t("client.orders.pickupCodeTitle")}</h3>
              <Card>
                <div className="flex flex-col items-center py-3 gap-3">
                  <div className="flex gap-2">
                    {order.pickupCode.split("").map((digit, i) => (
                      <span
                        key={i}
                        className="flex h-12 w-10 items-center justify-center rounded-md border-2 border-primary text-2xl font-black text-primary"
                      >
                        {digit}
                      </span>
                    ))}
                  </div>
                  <p className="text-center text-xs text-muted">
                    {t("client.orders.pickupCodeHint")}
                  </p>
                </div>
              </Card>
            </section>
          )}

          {/* CTA inline sur desktop */}
          <div className="hidden md:block mt-4">
            {order.status === "partially_accepted" ? (
              <Link href={kadhiaHref} className={getButtonClassName({ full: true })}>
                {t("client.orders.viewKadhia")}
              </Link>
            ) : (
              renderPickupAction(order, t)
            )}
          </div>
        </div>
      </div>

      {/* CTA sticky sur mobile */}
      <StickyBottom className="md:hidden">
        {order.status === "partially_accepted" ? (
          <Link href={kadhiaHref} className={getButtonClassName({ full: true })}>
            {t("client.orders.viewKadhia")}
          </Link>
        ) : (
          renderPickupAction(order, t)
        )}
      </StickyBottom>
    </>
  );
}
