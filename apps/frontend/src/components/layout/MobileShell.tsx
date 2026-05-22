import { cn } from "@/lib/cn";

/**
 * "Phone screen" mobile shell — used in dev so we can iterate on the mobile
 * UI from a desktop browser. In production builds for native (Capacitor/PWA),
 * pass `bareLayout` to drop the bezel chrome and use the full viewport.
 */
export interface MobileShellProps {
  children: React.ReactNode;
  /** Drop the device-bezel chrome (useful in PWA / native builds). */
  bareLayout?: boolean;
  className?: string;
}

export function MobileShell({ children, bareLayout, className }: MobileShellProps) {
  if (bareLayout) {
    return (
      <main className={cn("relative min-h-screen px-4 pt-4 pb-24", className)}>
        {children}
      </main>
    );
  }
  return (
    <div className="min-h-screen bg-soft-radial">
      <div className="mx-auto w-full max-w-[420px] px-3 py-6">
        <div className="rounded-2xl border border-line/90 bg-white/60 p-3 shadow-floating">
          <div
            className={cn(
              "relative min-h-[720px] overflow-hidden rounded-xl",
              "bg-gradient-to-b from-white to-[#f9faf6] p-4 pb-24",
              className,
            )}
          >
            <StatusBar />
            {children}
          </div>
        </div>
      </div>
    </div>
  );
}

function StatusBar() {
  if (process.env.NODE_ENV !== "development") return null;
  return (
    <div className="flex items-center justify-between px-1.5 pb-3 text-xs font-extrabold text-[#162418]">
      <span>22:14</span>
      <span>5G · 84%</span>
    </div>
  );
}
