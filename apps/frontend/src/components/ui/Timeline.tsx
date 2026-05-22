import { cn } from "@/lib/cn";
import { Check } from "lucide-react";
import type { TimelineStep } from "@/types";

export function Timeline({ steps }: { steps: TimelineStep[] }) {
  return (
    <ol className="grid gap-0">
      {steps.map((s, i) => (
        <li
          key={s.key}
          className="relative grid grid-cols-[28px_1fr] gap-3 min-h-[58px]"
        >
          {i < steps.length - 1 && (
            <span
              className="absolute left-[13px] top-7 h-[calc(100%-20px)] w-0.5 bg-line"
              aria-hidden
            />
          )}
          <span
            className={cn(
              "z-[1] grid h-7 w-7 place-items-center rounded-full text-xs text-white",
              s.state === "done" || s.state === "current"
                ? "bg-primary"
                : "bg-line",
            )}
          >
            {s.state === "done" ? (
              <Check size={14} strokeWidth={3} />
            ) : s.state === "current" ? (
              <span className="h-2 w-2 rounded-full bg-white" />
            ) : null}
          </span>
          <div>
            <strong
              className={cn(
                "block",
                s.state === "current" && "text-primary-dark",
              )}
            >
              {s.label}
            </strong>
            {s.hint && <span className="mt-1 block text-xs text-muted">{s.hint}</span>}
          </div>
        </li>
      ))}
    </ol>
  );
}
