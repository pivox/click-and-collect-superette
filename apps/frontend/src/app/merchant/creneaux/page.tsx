'use client';

import { useCallback, useEffect, useState } from 'react';
import { Plus } from 'lucide-react';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { DayStrip } from '@/components/merchant/creneaux/DayStrip';
import { SlotCard } from '@/components/merchant/creneaux/SlotCard';
import { SlotCreateModal } from '@/components/merchant/creneaux/SlotCreateModal';
import { RuleAccordion } from '@/components/merchant/creneaux/RuleAccordion';
import { GenerateBanner } from '@/components/merchant/creneaux/GenerateBanner';
import { ClosureAccordion } from '@/components/merchant/creneaux/ClosureAccordion';
import {
  listMerchantSlotRules,
  createMerchantSlotRule,
  deleteMerchantSlotRule,
  generateMerchantSlots,
} from '@/lib/services/merchant-slot-rules.service';
import {
  listMerchantSlots,
  createMerchantSlot,
  patchMerchantSlot,
  deleteMerchantSlot,
} from '@/lib/services/merchant-slots.service';
import {
  listMerchantClosures,
  createMerchantClosure,
  deleteMerchantClosure,
} from '@/lib/services/merchant-closures.service';
import type {
  CreateClosurePayload,
  CreateSlotPayload,
  CreateSlotRulePayload,
  MerchantExceptionalClosure,
  MerchantPickupSlot,
  MerchantPickupSlotRule,
  PatchSlotPayload,
} from '@/lib/types/merchant-slots.types';

function buildDays(count = 14): Date[] {
  const days: Date[] = [];
  const base = new Date();
  base.setHours(0, 0, 0, 0);
  for (let i = 0; i < count; i++) {
    const d = new Date(base);
    d.setDate(d.getDate() + i);
    days.push(d);
  }
  return days;
}

function isSameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

export default function MerchantCreneauxPage() {
  const { merchant } = useMerchantAuth();
  const storeId = merchant?.store.id ?? '';

  const [days] = useState(() => buildDays(14));
  const [selectedDate, setSelectedDate] = useState<Date>(days[0]!);
  const [rules, setRules] = useState<MerchantPickupSlotRule[]>([]);
  const [slots, setSlots] = useState<MerchantPickupSlot[]>([]);
  const [closures, setClosures] = useState<MerchantExceptionalClosure[]>([]);
  const [showBanner, setShowBanner] = useState(false);
  const [showCreateModal, setShowCreateModal] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);

  const loadAll = useCallback(async () => {
    if (!storeId) return;
    setLoadError(null);
    try {
      const [rulesData, slotsData, closuresData] = await Promise.all([
        listMerchantSlotRules(storeId),
        listMerchantSlots(storeId),
        listMerchantClosures(storeId),
      ]);
      setRules(rulesData.items);
      setSlots(slotsData);
      setClosures(closuresData.items);
    } catch {
      setLoadError('Impossible de charger les données. Vérifiez votre connexion et réessayez.');
    }
  }, [storeId]);

  useEffect(() => {
    void loadAll();
  }, [loadAll]);

  const slotsForDay = slots.filter((s) =>
    isSameDay(new Date(s.starts_at), selectedDate),
  );

  async function handleCreateRule(payload: CreateSlotRulePayload) {
    await createMerchantSlotRule(storeId, payload);
    setShowBanner(true);
    void loadAll();
  }

  async function handleDeleteRule(ruleId: string) {
    await deleteMerchantSlotRule(storeId, ruleId);
    void loadAll();
  }

  async function handleGenerate() {
    const result = await generateMerchantSlots(storeId);
    void loadAll();
    return result;
  }

  async function handleCreateSlot(payload: CreateSlotPayload) {
    await createMerchantSlot(storeId, payload);
    void loadAll();
  }

  async function handlePatchSlot(slotId: string, payload: PatchSlotPayload) {
    await patchMerchantSlot(storeId, slotId, payload);
    void loadAll();
  }

  async function handleDeleteSlot(slotId: string) {
    await deleteMerchantSlot(storeId, slotId);
    void loadAll();
  }

  async function handleCreateClosure(payload: CreateClosurePayload) {
    await createMerchantClosure(storeId, payload);
    void loadAll();
  }

  async function handleDeleteClosure(closureId: string) {
    await deleteMerchantClosure(storeId, closureId);
    void loadAll();
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center justify-between">
        <h1 className="text-h2 font-black">Créneaux</h1>
        <button
          type="button"
          onClick={() => setShowCreateModal(true)}
          className="flex items-center gap-1.5 rounded-md bg-primary px-3 py-2 text-sm font-bold text-white hover:brightness-95"
        >
          <Plus className="h-4 w-4" aria-hidden="true" />
          Créneau ponctuel
        </button>
      </div>

      {loadError && (
        <div role="alert" className="rounded-lg border border-danger/30 bg-danger/10 px-4 py-3 text-sm text-danger">
          {loadError}{' '}
          <button
            type="button"
            onClick={() => void loadAll()}
            className="font-bold underline"
          >
            Réessayer
          </button>
        </div>
      )}

      {showBanner && (
        <GenerateBanner
          onGenerate={handleGenerate}
          onDismiss={() => setShowBanner(false)}
        />
      )}

      <DayStrip
        days={days}
        selectedDate={selectedDate}
        slots={slots}
        closures={closures}
        onSelectDate={setSelectedDate}
      />

      <section>
        {slotsForDay.length === 0 ? (
          <p className="text-sm text-muted">
            Aucun créneau ce jour. Ajoutez une règle récurrente ou un créneau ponctuel.
          </p>
        ) : (
          <ul className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {slotsForDay.map((slot) => (
              <li key={slot.id}>
                <SlotCard
                  slot={slot}
                  onPatch={handlePatchSlot}
                  onDelete={handleDeleteSlot}
                />
              </li>
            ))}
          </ul>
        )}
      </section>

      <RuleAccordion
        rules={rules}
        onCreateRule={handleCreateRule}
        onDeleteRule={handleDeleteRule}
      />

      <ClosureAccordion
        closures={closures}
        onCreateClosure={handleCreateClosure}
        onDeleteClosure={handleDeleteClosure}
      />

      {showCreateModal && (
        <SlotCreateModal
          initialDate={selectedDate}
          onSubmit={handleCreateSlot}
          onClose={() => setShowCreateModal(false)}
        />
      )}
    </div>
  );
}
