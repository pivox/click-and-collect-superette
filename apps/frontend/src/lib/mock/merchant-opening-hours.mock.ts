import type { OpeningHours } from "@/lib/types/merchant-slots.types";

export const MOCK_OPENING_HOURS: OpeningHours = {
  monday:    { open: "08:00", close: "20:00", closed: false },
  tuesday:   { open: "08:00", close: "20:00", closed: false },
  wednesday: { open: "08:00", close: "20:00", closed: false },
  thursday:  { open: "08:00", close: "20:00", closed: false },
  friday:    { open: "08:00", close: "20:00", closed: false },
  saturday:  { open: "09:00", close: "18:00", closed: false },
  sunday:    { open: null,    close: null,    closed: true  },
};
