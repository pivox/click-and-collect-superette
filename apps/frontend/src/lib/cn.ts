import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";

/**
 * Merge Tailwind classNames safely. Use this everywhere we have conditional
 * classes or accept a `className` prop on components.
 */
export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}
