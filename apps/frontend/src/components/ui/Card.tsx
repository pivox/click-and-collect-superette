import { forwardRef } from "react";
import { cn } from "@/lib/cn";

export interface CardProps extends React.HTMLAttributes<HTMLDivElement> {
  compact?: boolean;
  as?: "div" | "article" | "a" | "section";
}

export const Card = forwardRef<HTMLDivElement, CardProps>(function Card(
  { compact, className, children, as = "div", ...rest },
  ref,
) {
  const Comp = as as React.ElementType;
  return (
    <Comp
      ref={ref}
      className={cn(
        "bg-card border border-line shadow-soft",
        compact ? "rounded-md p-3" : "rounded-lg p-4",
        className,
      )}
      {...rest}
    >
      {children}
    </Comp>
  );
});
