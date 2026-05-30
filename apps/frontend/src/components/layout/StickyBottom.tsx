import { cn } from "@/lib/cn";

/**
 * Sticks an action bar to the bottom of the MobileShell. The shell already
 * reserves bottom padding for it.
 */
export function StickyBottom({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "fixed inset-x-0 bottom-[calc(60px+env(safe-area-inset-bottom))] z-30 border-t border-line bg-white/95 backdrop-blur-md",
        "px-4 pt-3 pb-3 md:absolute md:bottom-0",
        className,
      )}
    >
      {children}
    </div>
  );
}
