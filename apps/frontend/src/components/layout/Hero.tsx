import { Badge } from "@/components/ui/Badge";
import { cn } from "@/lib/cn";

export interface HeroProps {
  badge?: string;
  title: string;
  subtitle?: string;
  actions?: React.ReactNode;
  className?: string;
}

/**
 * Emerald gradient hero — used at the top of the home + store screens.
 * Matches `.hero` in user-client-flow.css and `.hero-panel` in user-web-flow.css.
 */
export function Hero({ badge, title, subtitle, actions, className }: HeroProps) {
  return (
    <section
      className={cn(
        "relative overflow-hidden rounded-xl bg-hero-emerald p-5 text-white shadow-floating",
        "after:absolute after:-right-12 after:-top-9 after:h-36 after:w-36",
        "after:rounded-full after:bg-white/10 after:content-['']",
        className,
      )}
    >
      <div className="relative z-[1]">
        {badge && <Badge tone="onPrimary" className="mb-4">{badge}</Badge>}
        <h1 className="m-0 text-h1 font-black">{title}</h1>
        {subtitle && (
          <p className="mt-2.5 mb-0 text-white/80 leading-relaxed">
            {subtitle}
          </p>
        )}
        {actions && <div className="mt-4 grid gap-2">{actions}</div>}
      </div>
    </section>
  );
}
