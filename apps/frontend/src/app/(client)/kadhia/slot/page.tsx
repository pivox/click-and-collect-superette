"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { SlotTile } from "@/components/ui/SlotTile";
import { Button } from "@/components/ui/Button";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { listSlotsForShop, submitKadhia } from "@/lib/services";
import { formatTime } from "@/lib/format";
import type { PickupSlot } from "@/types";
import { useClientAuth } from '@/lib/auth/ClientAuthContext';

const DEMO_SHOP_ID = "shop-el-amel";

export default function SlotPage() {
  const router = useRouter();
  const { user, isLoading } = useClientAuth();
  const [slots, setSlots] = useState<PickupSlot[]>([]);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [day, setDay] = useState<"today" | "tomorrow" | "after">("today");
  const [note, setNote] = useState(
    "Si un produit est absent, remplacer par une marque proche.",
  );
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [submitError, setSubmitError] = useState<string | null>(null);

  useEffect(() => {
    if (!isLoading && !user) {
      router.push('/login?redirect=/kadhia/slot');
    }
  }, [isLoading, user, router]);

  useEffect(() => {
    if (isLoading || !user) return;
    void listSlotsForShop(DEMO_SHOP_ID, day).then((s) => {
      setSlots(s);
      const firstAvail = s.find((x) => x.available);
      setActiveId(firstAvail?.id ?? null);
    });
  }, [day, isLoading, user]);

  const handleSubmit = async () => {
    if (!activeId) return;
    setIsSubmitting(true);
    setSubmitError(null);
    try {
      const result = await submitKadhia({
        shopId: DEMO_SHOP_ID,
        pickupSlotId: activeId,
        customerNote: note.trim() || undefined,
      });
      router.push(`/orders/${result.orderCode}`);
    } catch (err) {
      setSubmitError(
        err instanceof Error ? err.message : 'Erreur lors de la soumission',
      );
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
        backHref="/kadhia"
      />

      <PillRow className="mb-4">
        <Pill active={day === "today"} onClick={() => setDay("today")}>
          Aujourd&apos;hui
        </Pill>
        <Pill active={day === "tomorrow"} onClick={() => setDay("tomorrow")}>
          Demain
        </Pill>
        <Pill active={day === "after"} onClick={() => setDay("after")}>
          Vendredi
        </Pill>
      </PillRow>

      <section className="mt-2">
        <h3 className="mb-2.5 text-h3 font-extrabold">Créneaux disponibles</h3>
        {slots.length === 0 ? (
          <p className="text-sm text-muted">Aucun créneau disponible pour ce jour.</p>
        ) : (
          <div className="grid grid-cols-2 gap-2.5">
            {slots.map((s) => (
              <SlotTile
                key={s.id}
                time={formatTime(s.startsAt)}
                label={s.label}
                disabled={!s.available}
                active={activeId === s.id}
                onClick={() => setActiveId(s.id)}
              />
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
          disabled={!activeId || isSubmitting}
          onClick={handleSubmit}
        >
          {isSubmitting ? 'Envoi en cours…' : 'Envoyer la commande'}
        </Button>
      </StickyBottom>
    </>
  );
}
