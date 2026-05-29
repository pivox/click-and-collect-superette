"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { SlotTile } from "@/components/ui/SlotTile";
import { Button } from "@/components/ui/Button";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { listSlotsForShop, submitKadhia, readLocalKadhia } from "@/lib/services";
import { formatTime } from "@/lib/format";
import type { PickupSlot } from "@/types";

interface SlotGroup { label: string; slots: PickupSlot[] }

function groupByPeriod(slots: PickupSlot[]): SlotGroup[] {
  const periods = [
    { label: "Matin", minH: 0, maxH: 12 },
    { label: "Midi", minH: 12, maxH: 14 },
    { label: "Après-midi", minH: 14, maxH: 18 },
    { label: "Soir", minH: 18, maxH: 24 },
  ];
  return periods
    .map(({ label, minH, maxH }) => ({
      label,
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

/** Maps backend error codes to user-friendly French messages. */
function resolveSubmitError(err: unknown): string {
  const detail =
    (err as { response?: { data?: { detail?: string } } })?.response?.data?.detail ?? "";

  switch (detail) {
    case "PICKUP_SLOT_FULL":
      return "Ce créneau est complet. Choisis un autre créneau ci-dessous.";
    case "PICKUP_SLOT_EXPIRED":
      return "Ce créneau est passé. Choisis un créneau disponible.";
    case "PICKUP_SLOT_CLOSED":
      return "La supérette est fermée à ce créneau. Choisis un autre.";
    case "PICKUP_SLOT_NOT_FOUND":
      return "Ce créneau n'est plus disponible. Choisis-en un autre.";
    case "KADHIA_EMPTY":
      return "Ta Kadhia est vide. Ajoute des produits avant d'envoyer.";
    case "PRODUCT_UNAVAILABLE":
      return "Un produit de ta Kadhia n'est plus disponible. Reviens à ta Kadhia pour le retirer.";
    case "KADHIA_NOT_FOUND":
      return "Aucune Kadhia active trouvée. Retourne au catalogue pour en créer une.";
    default:
      return "La commande n'a pas pu être envoyée. Ta Kadhia est conservée, tu peux réessayer.";
  }
}

function afterTomorrowLabel(): string {
  const d = new Date();
  d.setDate(d.getDate() + 2);
  return d.toLocaleDateString("fr-FR", { weekday: "long" }).replace(/^\w/, (c) => c.toUpperCase());
}
import { useClientAuth } from '@/lib/auth/ClientAuthContext';

export default function SlotPage() {
  const router = useRouter();
  const { user, isLoading } = useClientAuth();
  const [shopId, setShopId] = useState<string | null>(null);
  const [kadhiaId, setKadhiaId] = useState<string | null>(null);
  const [slots, setSlots] = useState<PickupSlot[]>([]);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [day, setDay] = useState<"today" | "tomorrow" | "after">("today");
  const [note, setNote] = useState(
    "Si un produit est absent, remplacer par une marque proche.",
  );
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);
  const [slotsError, setSlotsError] = useState<string | null>(null);

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
    setSlotsError(null);
    listSlotsForShop(shopId, day)
      .then((s) => {
        setSlots(s);
        const firstAvail = s.find((x) => x.available);
        setActiveId(firstAvail?.id ?? null);
      })
      .catch(() => {
        setSlotsError("Impossible de charger les créneaux. Vérifie ta connexion et réessaie.");
      });
  }, [day, isLoading, user, shopId]);

  const handleSubmit = async () => {
    if (!activeId || !shopId) return;
    setIsSubmitting(true);
    setSubmitError(null);
    try {
      const result = await submitKadhia({
        shopId,
        pickupSlotId: activeId,
        customerNote: note.trim() || undefined,
      });
      router.push(`/orders/${result.orderId}`);
    } catch (err: unknown) {
      setSubmitError(resolveSubmitError(err));
    } finally {
      setIsSubmitting(false);
    }
  };

  if (isLoading || !user) return null;

  return (
    <>
      <TopBar
        title="Créneau de retrait"
        subtitle="Choisis quand récupérer ta commande"
        backHref={kadhiaId ? `/kadhia/${kadhiaId}` : "/kadhia"}
      />

      <PillRow className="mb-4">
        <Pill active={day === "today"} onClick={() => setDay("today")}>
          Aujourd&apos;hui
        </Pill>
        <Pill active={day === "tomorrow"} onClick={() => setDay("tomorrow")}>
          Demain
        </Pill>
        <Pill active={day === "after"} onClick={() => setDay("after")}>
          {afterTomorrowLabel()}
        </Pill>
      </PillRow>

      <section className="mt-2">
        <h3 className="mb-2.5 text-h3 font-extrabold">Créneaux disponibles</h3>
        {slotsError ? (
          <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">{slotsError}</p>
        ) : slots.length === 0 ? (
          <div className="rounded-md bg-soft px-4 py-5 text-center">
            <p className="text-sm font-bold">Aucun créneau ce jour.</p>
            <p className="mt-1 text-xs text-muted">
              Essaie un autre jour ou reviens plus tard.
            </p>
          </div>
        ) : (
          <div className="flex flex-col gap-5">
            {groupByPeriod(slots).map(({ label, slots: group }) => (
              <div key={label}>
                <p className="mb-2 text-xs font-extrabold uppercase tracking-wider text-muted">
                  {label}
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
        <h3 className="mb-2.5 text-h3 font-extrabold">Note au marchand</h3>
        <textarea
          className="min-h-[80px] w-full resize-y rounded-lg border border-line bg-white p-3 text-sm outline-none placeholder:text-muted"
          value={note}
          onChange={(e) => setNote(e.currentTarget.value)}
        />
      </section>

      <StickyBottom>
        {submitError && (
          <p className="mb-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
            {submitError}
          </p>
        )}
        <Button
          full
          disabled={!activeId || !shopId || isSubmitting}
          onClick={handleSubmit}
        >
          {isSubmitting ? 'Envoi en cours…' : 'Envoyer la commande'}
        </Button>
      </StickyBottom>
    </>
  );
}
