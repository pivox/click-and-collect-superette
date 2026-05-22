"use client";

import { cn } from "@/lib/cn";

export interface SlotTileProps {
  time: string;
  label?: string;
  active?: boolean;
  disabled?: boolean;
  onClick?: () => void;
}

export function SlotTile({
  time,
  label,
  active,
  disabled,
  onClick,
}: SlotTileProps) {
  return (
    <button
      type="button"
      disabled={disabled}
      onClick={onClick}
      className={cn(
        "rounded-md border bg-white p-3 text-left transition-colors",
        active &&
          "border-primary bg-[#eff8f1] shadow-[inset_0_0_0_1px_var(--primary)]",
        disabled && "opacity-45 bg-[#f2f2f2] cursor-not-allowed",
        !active && !disabled && "border-line hover:bg-soft",
      )}
    >
      <strong className="block text-sm">{time}</strong>
      {label && (
        <span className="mt-1 block text-xs text-muted">{label}</span>
      )}
    </button>
  );
}
