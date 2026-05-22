import type { PickupSlot } from "@/types";

/**
 * Generate slots for today, starting at 18:00 in 30-minute increments.
 * The shape matches future GET /shops/{id}/slots response.
 */
function todayAt(h: number, m: number): string {
  const d = new Date();
  d.setHours(h, m, 0, 0);
  return d.toISOString();
}

export const MOCK_SLOTS_TODAY: PickupSlot[] = [
  {
    id: "slot-1",
    startsAt: todayAt(18, 0),
    endsAt: todayAt(18, 30),
    capacity: 4,
    available: true,
    label: "Complet bientôt",
  },
  {
    id: "slot-2",
    startsAt: todayAt(18, 30),
    endsAt: todayAt(19, 0),
    capacity: 6,
    available: true,
    label: "Disponible",
  },
  {
    id: "slot-3",
    startsAt: todayAt(19, 0),
    endsAt: todayAt(19, 30),
    capacity: 6,
    available: true,
    label: "Disponible",
  },
  {
    id: "slot-4",
    startsAt: todayAt(19, 30),
    endsAt: todayAt(20, 0),
    capacity: 0,
    available: false,
    label: "Complet",
  },
  {
    id: "slot-5",
    startsAt: todayAt(20, 0),
    endsAt: todayAt(20, 30),
    capacity: 6,
    available: true,
    label: "Disponible",
  },
  {
    id: "slot-6",
    startsAt: todayAt(20, 30),
    endsAt: todayAt(21, 0),
    capacity: 6,
    available: true,
    label: "Disponible",
  },
];
