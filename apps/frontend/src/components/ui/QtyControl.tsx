"use client";

import { Minus, Plus } from "lucide-react";

export interface QtyControlProps {
  value: number;
  min?: number;
  max?: number;
  onChange: (next: number) => void;
}

export function QtyControl({ value, min = 0, max = 99, onChange }: QtyControlProps) {
  const dec = () => onChange(Math.max(min, value - 1));
  const inc = () => onChange(Math.min(max, value + 1));
  return (
    <div className="inline-flex items-center gap-2 rounded-md border border-line bg-white p-1">
      <button
        type="button"
        onClick={dec}
        disabled={value <= min}
        aria-label="Diminuer la quantité"
        className="grid h-7 w-7 place-items-center rounded bg-primary text-white font-black hover:bg-primary-dark disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <Minus size={14} />
      </button>
      <strong className="min-w-[1.25rem] text-center text-sm">{value}</strong>
      <button
        type="button"
        onClick={inc}
        disabled={value >= max}
        aria-label="Augmenter la quantité"
        className="grid h-7 w-7 place-items-center rounded bg-primary text-white font-black hover:bg-primary-dark disabled:opacity-50 disabled:cursor-not-allowed"
      >
        <Plus size={14} />
      </button>
    </div>
  );
}
