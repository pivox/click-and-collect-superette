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
        "absolute inset-x-0 bottom-0 z-10 border-t border-line bg-white/95 backdrop-blur-md",
        "px-4 pt-3 pb-4",
        className,
      )}
    >
      {children}
    </div>
  );
}
