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

/**
 * Map an OrderStatus to its visual tone + i18n label key.
 * The caller resolves `labelKey` with the locale-aware `t()` (S14-004).
 */
export function orderStatusBadge(status: OrderStatus): {
  tone: BadgeTone;
  labelKey: string;
} {
  switch (status) {
    case "draft":
      return { tone: "default", labelKey: "client.orderStatus.draft" };
    case "submitted":
      return { tone: "prep", labelKey: "client.orderStatus.submitted" };
    case "accepted":
      return { tone: "prep", labelKey: "client.orderStatus.accepted" };
    case "partially_accepted":
      return { tone: "wait", labelKey: "client.orderStatus.partiallyAccepted" };
    case "rejected":
      return { tone: "cancel", labelKey: "client.orderStatus.rejected" };
    case "preparing":
      return { tone: "wait", labelKey: "client.orderStatus.preparing" };
    case "ready":
    case "pickup_pending":
      return { tone: "ready", labelKey: "client.orderStatus.ready" };
    case "completed":
      return { tone: "ready", labelKey: "client.orderStatus.completed" };
    case "cancelled":
      return { tone: "cancel", labelKey: "client.orderStatus.cancelled" };
  }
}
