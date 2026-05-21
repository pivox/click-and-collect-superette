import { cn } from "@/lib/cn";
import type { OrderStatus } from "@/types";

type BadgeTone =
  | "default"
  | "wait"
  | "prep"
  | "ready"
  | "cancel"
  | "info"
  | "onPrimary";

export interface BadgeProps extends React.HTMLAttributes<HTMLSpanElement> {
  tone?: BadgeTone;
}

const TONE: Record<BadgeTone, string> = {
  default: "bg-soft text-primary-dark",
  wait: "bg-[var(--status-wait-bg)] text-[var(--status-wait)]",
  prep: "bg-[var(--status-prep-bg)] text-[var(--status-prep)]",
  ready: "bg-[var(--status-ready-bg)] text-[var(--status-ready)]",
  cancel: "bg-[var(--status-cancel-bg)] text-[var(--status-cancel)]",
  info: "bg-[#eef6ff] text-[#1e40af] border border-[#bfdbfe]",
  onPrimary: "bg-white/15 text-white",
};

export function Badge({ tone = "default", className, ...rest }: BadgeProps) {
  return (
    <span
      className={cn(
        "inline-flex items-center gap-2 rounded-full px-3 py-1.5 text-xs font-extrabold",
        TONE[tone],
        className,
      )}
      {...rest}
    />
  );
}

/** Map an OrderStatus to its visual tone + label. */
export function orderStatusBadge(status: OrderStatus): {
  tone: BadgeTone;
  label: string;
} {
  switch (status) {
    case "draft":
      return { tone: "default", label: "Brouillon" };
    case "submitted":
      return { tone: "prep", label: "Soumise" };
    case "accepted":
      return { tone: "prep", label: "Acceptée" };
    case "rejected":
      return { tone: "cancel", label: "Refusée" };
    case "preparing":
      return { tone: "wait", label: "En préparation" };
    case "ready":
    case "pickup_pending":
      return { tone: "ready", label: "Commande prête" };
    case "completed":
      return { tone: "ready", label: "Récupérée" };
    case "cancelled":
      return { tone: "cancel", label: "Annulée" };
  }
}
