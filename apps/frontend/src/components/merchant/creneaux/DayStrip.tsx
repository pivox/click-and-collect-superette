'use client';

import { cn } from '@/lib/cn';
import type { MerchantPickupSlot, MerchantExceptionalClosure } from '@/lib/types/merchant-slots.types';

export interface DayStripProps {
  days: Date[];
  selectedDate: Date;
  slots: MerchantPickupSlot[];
  closures: MerchantExceptionalClosure[];
  onSelectDate: (date: Date) => void;
}

function isSameDay(a: Date, b: Date): boolean {
  return (
    a.getFullYear() === b.getFullYear() &&
    a.getMonth() === b.getMonth() &&
    a.getDate() === b.getDate()
  );
}

function hasClosure(date: Date, closures: MerchantExceptionalClosure[]): boolean {
  return closures.some((c) => {
    const start = new Date(c.starts_at);
    const end = new Date(c.ends_at);
    return c.is_active && date >= start && date <= end;
  });
}

function slotCountForDay(date: Date, slots: MerchantPickupSlot[]): number {
  return slots.filter(
    (s) => s.is_active && isSameDay(new Date(s.starts_at), date),
  ).length;
}

function formatDayLabel(date: Date): string {
  const weekday = date
    .toLocaleDateString('fr-FR', { weekday: 'short' })
    .replace('.', '');
  const day = date.getDate();
  return `${weekday.charAt(0).toUpperCase()}${weekday.slice(1)} ${day}`;
}

export function DayStrip({
  days,
  selectedDate,
  slots,
  closures,
  onSelectDate,
}: DayStripProps) {
  return (
    <div className="flex gap-2 overflow-x-auto pb-2" role="list" aria-label="Jours">
      {days.map((date) => {
        const isSelected = isSameDay(date, selectedDate);
        const count = slotCountForDay(date, slots);
        const closed = hasClosure(date, closures);

        return (
          <button
            key={date.toISOString()}
            role="listitem"
            type="button"
            onClick={() => onSelectDate(date)}
            aria-pressed={isSelected}
            className={cn(
              'relative flex min-w-[64px] flex-col items-center rounded-lg border px-2 py-2.5 text-sm transition-colors',
              isSelected
                ? 'border-primary bg-[#eff8f1] font-bold text-primary'
                : 'border-line bg-card text-ink hover:bg-soft',
            )}
          >
            <span className="text-xs">{formatDayLabel(date)}</span>
            {count > 0 && (
              <span
                aria-label={`${count} créneau${count > 1 ? 'x' : ''}`}
                className="mt-1 inline-flex h-5 w-5 items-center justify-center rounded-full bg-primary text-[11px] font-black text-white"
              >
                {count}
              </span>
            )}
            {closed && (
              <span
                aria-label="Fermeture exceptionnelle"
                className="absolute right-1 top-1 h-2 w-2 rounded-full bg-danger"
              />
            )}
          </button>
        );
      })}
    </div>
  );
}
