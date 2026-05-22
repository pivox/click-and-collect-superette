import { cn } from "@/lib/cn";

export interface PillProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  active?: boolean;
}

export function Pill({ active, className, ...rest }: PillProps) {
  return (
    <button
      type="button"
      className={cn(
        "flex-shrink-0 rounded-full border px-3 py-2 text-xs font-extrabold transition-colors",
        active
          ? "bg-primary border-primary text-white"
          : "bg-white border-line text-muted hover:text-ink",
        className,
      )}
      {...rest}
    />
  );
}

export function PillRow({
  children,
  className,
}: {
  children: React.ReactNode;
  className?: string;
}) {
  return (
    <div
      className={cn(
        "flex gap-2 overflow-x-auto pb-1 no-scrollbar",
        className,
      )}
    >
      {children}
    </div>
  );
}
