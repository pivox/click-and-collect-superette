import { forwardRef } from "react";
import { cn } from "@/lib/cn";

type ButtonVariant = "primary" | "secondary" | "ghost" | "danger";
type ButtonSize = "md" | "lg";

interface ButtonClassNameOptions {
  variant?: ButtonVariant;
  size?: ButtonSize;
  full?: boolean;
  className?: string;
}

export interface ButtonProps
  extends React.ButtonHTMLAttributes<HTMLButtonElement> {
  variant?: ButtonVariant;
  size?: ButtonSize;
  full?: boolean;
}

const VARIANT: Record<ButtonVariant, string> = {
  primary:
    "bg-primary text-white shadow-cta hover:bg-primary-dark active:translate-y-px",
  secondary:
    "bg-secondary text-[#332500] hover:brightness-95 active:translate-y-px",
  ghost:
    "bg-white border border-line text-ink hover:bg-soft active:translate-y-px",
  danger:
    "bg-danger text-white hover:brightness-95 active:translate-y-px",
};

const SIZE: Record<ButtonSize, string> = {
  md: "min-h-[44px] px-4 text-sm",
  lg: "min-h-[48px] px-5 text-sm",
};

export function getButtonClassName({
  variant = "primary",
  size = "lg",
  full,
  className,
}: ButtonClassNameOptions = {}) {
  return cn(
    "inline-flex items-center justify-center gap-2 rounded-md font-black transition-colors",
    "disabled:opacity-50 disabled:cursor-not-allowed",
    VARIANT[variant],
    SIZE[size],
    full && "w-full",
    className,
  );
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(
  function Button(
    { variant = "primary", size = "lg", full, className, children, ...rest },
    ref,
  ) {
    return (
      <button
        ref={ref}
        className={getButtonClassName({ variant, size, full, className })}
        {...rest}
      >
        {children}
      </button>
    );
  },
);
