"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { SlotTile } from "@/components/ui/SlotTile";
import { Button } from "@/components/ui/Button";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { listSlotsForShop } from "@/lib/services";
import { formatTime } from "@/lib/format";
import type { PickupSlot } from "@/types";

const DEMO_SHOP_ID = "shop-el-amel";

export default function SlotPage() {
  const [slots, setSlots] = useState<PickupSlot[]>([]);
  const [activeId, setActiveId] = useState<string | null>(null);
  const [day, setDay] = useState<"today" | "tomorrow" | "after">("today");
  const [note, setNote] = useState(
    "Si un produit est absent, remplacer par une marque proche.",
  );

  useEffect(() => {
    void listSlotsForShop(DEMO_SHOP_ID, day).then((s) => {
      setSlots(s);
      const firstAvail = s.find((x) => x.available);
      setActiveId(firstAvail?.id ?? null);
    });
  }, [day]);

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
        <Link href="/orders/CMD-4821">
          <Button full disabled={!activeId}>
            Envoyer la commande
          </Button>
        </Link>
      </StickyBottom>
    </>
  );
}
