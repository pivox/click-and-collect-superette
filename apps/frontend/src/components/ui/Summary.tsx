import { cn } from "@/lib/cn";

export function Summary({ children }: { children: React.ReactNode }) {
  return <dl className="grid gap-2.5">{children}</dl>;
}

export interface SummaryRowProps {
  label: React.ReactNode;
  value: React.ReactNode;
  total?: boolean;
  className?: string;
}

export function SummaryRow({ label, value, total, className }: SummaryRowProps) {
  return (
    <div
      className={cn(
        "flex items-baseline justify-between gap-3 text-sm",
        total
          ? "border-t border-dashed border-line pt-3 text-base font-black"
          : "text-muted",
        className,
      )}
    >
      <dt>{label}</dt>
      <dd className={total ? "text-ink" : "text-ink font-bold"}>{value}</dd>
    </div>
  );
}
