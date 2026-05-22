import Link from "next/link";
import { ArrowLeft, ArrowRight } from "lucide-react";
import { cn } from "@/lib/cn";

export interface TopBarProps {
  title: string;
  subtitle?: string;
  /** Optional back URL — when present, renders a chevron-left button. */
  backHref?: string;
  /** Optional right-side action (e.g. cart icon). */
  action?: React.ReactNode;
  /** "rtl" reverses the chevron direction. */
  dir?: "ltr" | "rtl";
}

export function TopBar({
  title,
  subtitle,
  backHref,
  action,
  dir = "ltr",
}: TopBarProps) {
  const Chevron = dir === "rtl" ? ArrowRight : ArrowLeft;
  return (
    <div className="flex items-center gap-3 mb-4">
      {backHref && (
        <Link
          href={backHref}
          aria-label="Retour"
          className={cn(
            "grid h-10 w-10 place-items-center rounded-[15px] border border-line bg-card",
            "shadow-[0_8px_18px_rgba(18,30,20,.06)]",
          )}
        >
          <Chevron size={18} />
        </Link>
      )}
      <div className="flex-1 min-w-0">
        <strong className="block truncate text-base">{title}</strong>
        {subtitle && (
          <span className="block text-xs text-muted truncate">{subtitle}</span>
        )}
      </div>
      {action}
    </div>
  );
}
