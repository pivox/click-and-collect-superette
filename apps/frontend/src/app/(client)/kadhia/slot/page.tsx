"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { SlotTile } from "@/components/ui/SlotTile";
import { Button } from "@/components/ui/Button";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { discardKadhia, listSlotsForShop, submitKadhia, readLocalKadhia } from "@/lib/services";
import { formatTime } from "@/lib/format";
import type { PickupSlot } from "@/types";

interface SlotGroup { key: string; slots: PickupSlot[] }

function groupByPeriod(slots: PickupSlot[]): SlotGroup[] {
  const periods = [
    { key: "morning", minH: 0, maxH: 12 },
    { key: "noon", minH: 12, maxH: 14 },
    { key: "afternoon", minH: 14, maxH: 18 },
    { key: "evening", minH: 18, maxH: 24 },
  ];
  return periods
    .map(({ key, minH, maxH }) => ({
      key,
      slots: slots.filter((s) => {
        const h = parseInt(
          new Intl.DateTimeFormat("fr-FR", {
            hour: "2-digit",
            hourCycle: "h23",
            timeZone: "Africa/Tunis",
          }).format(new Date(s.startsAt)),
          10,
        );
        return h >= minH && h < maxH;
      }),
    }))
    .filter((g) => g.slots.length > 0);
}

/** Maps backend error codes to a translation key under client.slot.errors. */
function resolveSubmitErrorKey(err: unknown): string {
  const detail =
    (err as { response?: { data?: { detail?: string } } })?.response?.data?.detail ?? "";

  switch (detail) {
    case "PICKUP_SLOT_FULL":
      return "client.slot.errors.full";
    case "PICKUP_SLOT_EXPIRED":
      return "client.slot.errors.expired";
    case "PICKUP_SLOT_CLOSED":
      return "client.slot.errors.closed";
    case "PICKUP_SLOT_NOT_FOUND":
      return "client.slot.errors.notFound";
    case "KADHIA_EMPTY":
      return "client.slot.errors.kadhiaEmpty";
    case "PRODUCT_UNAVAILABLE":
      return "client.slot.errors.productUnavailable";
    case "KADHIA_NOT_FOUND":
      return "client.slot.errors.kadhiaNotFound";
    case "STORE_SUSPENDED_FOR_SUBSCRIPTION":
      return "client.slot.errors.storeSuspended";
    case "PARTIAL_ACCEPTANCE_EXPIRED":
      return "client.slot.errors.partialAcceptanceExpired";
    default:
      return "client.slot.errors.generic";
  }
}

function isPartialAcceptanceExpired(err: unknown): boolean {
  return (err as { response?: { data?: { detail?: string } } })?.response?.data?.detail === "PARTIAL_ACCEPTANCE_EXPIRED";
}

function afterTomorrowLabel(locale: string): string {
  const d = new Date();
  d.setDate(d.getDate() + 2);
  return d.toLocaleDateString(locale, { weekday: "long" }).replace(/^\w/, (c) => c.toUpperCase());
}
import { useClientAuth } from '@/lib/auth/ClientAuthContext';
import { useClientLocale } from '@/lib/i18n/ClientLocaleContext';

export default function SlotPage() {
  const router = useRouter();
  const { user, isLoading } = useClientAuth();
  const { locale, t } = useClientLocale();
  const [shopId, setShopId] = useState<string | null>(null);
  const [kadhiaId, setKadhiaId] = useState<string | null>(null);
  const [slots, setSlots] = useState<PickupSlot[]>([]);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [day, setDay] = useState<"today" | "tomorrow" | "after">("today");
  const [note, setNote] = useState(() => t("client.slot.defaultNote"));
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [canRestartKadhia, setCanRestartKadhia] = useState(false);
  const [slotsError, setSlotsError] = useState(false);

  useEffect(() => {
    if (!isLoading && !user) {
      router.push('/login?redirect=/kadhia/slot');
    }
  }, [isLoading, user, router]);

  useEffect(() => {
    if (isLoading || !user) return;
    const kadhia = readLocalKadhia();
    if (!kadhia?.shopId) {
      router.push('/kadhia');
      return;
    }
    setShopId(kadhia.shopId);
    setKadhiaId(kadhia.id || null);
  }, [isLoading, user, router]);

  useEffect(() => {
    if (isLoading || !user || !shopId) return;
    setSlotsError(false);
    listSlotsForShop(shopId, day)
      .then((s) => {
        setSlots(s);
        const firstAvail = s.find((x) => x.available);
        setActiveId(firstAvail?.id ?? null);
      })
      .catch(() => {
        setSlotsError(true);
      });
  }, [day, isLoading, user, shopId]);

  const handleSubmit = async () => {
    if (!activeId || !shopId) return;
    setIsSubmitting(true);
    setSubmitError(null);
    setCanRestartKadhia(false);
    try {
      const result = await submitKadhia({
        shopId,
        pickupSlotId: activeId,
        customerNote: note.trim() || undefined,
      });
      router.push(`/orders/${result.orderId}`);
    } catch (err: unknown) {
      if (isPartialAcceptanceExpired(err)) {
        try {
          await discardKadhia(shopId);
        } catch (discardErr) {
          console.error("[slot] failed to discard expired partial Kadhia", { shopId, discardErr });
        }
        setKadhiaId(null);
        setCanRestartKadhia(true);
      }
      setSubmitError(resolveSubmitErrorKey(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isLoading || !user) return null;

  return (
    <>
      <TopBar
        title={t("client.slot.title")}
        subtitle={t("client.slot.subtitle")}
        backHref={kadhiaId ? `/kadhia/${kadhiaId}` : "/kadhia"}
      />

      <PillRow className="mb-4">
        <Pill active={day === "today"} onClick={() => setDay("today")}>
          {t("client.slot.today")}
        </Pill>
        <Pill active={day === "tomorrow"} onClick={() => setDay("tomorrow")}>
          {t("client.slot.tomorrow")}
        </Pill>
        <Pill active={day === "after"} onClick={() => setDay("after")}>
          {afterTomorrowLabel(locale)}
        </Pill>
      </PillRow>

      <section className="mt-2">
        <h3 className="mb-2.5 text-h3 font-extrabold">{t("client.slot.available")}</h3>
        {slotsError ? (
          <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">{t("client.slot.slotsError")}</p>
        ) : slots.length === 0 ? (
          <div className="rounded-md bg-soft px-4 py-5 text-center">
            <p className="text-sm font-bold">{t("client.slot.noSlots")}</p>
            <p className="mt-1 text-xs text-muted">
              {t("client.slot.noSlotsHint")}
            </p>
          </div>
        ) : (
          <div className="flex flex-col gap-5">
            {groupByPeriod(slots).map(({ key, slots: group }) => (
              <div key={key}>
                <p className="mb-2 text-xs font-extrabold uppercase tracking-wider text-muted">
                  {t(`client.slot.period.${key}`)}
                </p>
                <div className="grid grid-cols-2 gap-2.5">
                  {group.map((s) => (
                    <SlotTile
                      key={s.id}
                      time={formatTime(s.startsAt)}
                      endTime={formatTime(s.endsAt)}
                      label={s.label}
                      disabled={!s.available}
                      active={activeId === s.id}
                      onClick={() => setActiveId(s.id)}
                    />
                  ))}
                </div>
              </div>
            ))}
          </div>
        )}
      </section>

      <section className="mt-5">
        <h3 className="mb-2.5 text-h3 font-extrabold">{t("client.slot.merchantNote")}</h3>
        <textarea
          className="min-h-[80px] w-full resize-y rounded-lg border border-line bg-white p-3 text-sm outline-none placeholder:text-muted"
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
        />
      </section>

      <StickyBottom>
        {submitError && (
          <p className="mb-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
            {t(submitError)}
          </p>
        )}
        {canRestartKadhia && shopId && (
          <Button
            full
            type="button"
            variant="ghost"
            className="mb-2"
            onClick={() => router.push(`/stores/${shopId}/catalog`)}
          >
            {t("client.slot.restartFromCatalog")}
          </Button>
        )}
        <Button
          full
          disabled={!activeId || !shopId || isSubmitting}
          onClick={handleSubmit}
        >
          {isSubmitting ? t('client.slot.submitting') : t('client.slot.submit')}
        </Button>
      </StickyBottom>
    </>
  );
}
