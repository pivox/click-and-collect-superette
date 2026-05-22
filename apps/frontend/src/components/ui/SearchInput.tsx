"use client";

import { Search } from "lucide-react";
import { cn } from "@/lib/cn";

export interface SearchInputProps
  extends Omit<React.InputHTMLAttributes<HTMLInputElement>, "size"> {
  size?: "md" | "lg";
}

export function SearchInput({
  size = "md",
  className,
  ...rest
}: SearchInputProps) {
  return (
    <label
      className={cn(
        "flex items-center gap-3 rounded-md border border-line bg-white text-muted shadow-card",
        size === "md" ? "px-4 py-3 text-sm" : "px-5 py-4 text-base",
        className,
      )}
    >
      <Search size={18} aria-hidden="true" />
      <input
        type="search"
        className="w-full flex-1 bg-transparent outline-none placeholder:text-muted"
        {...rest}
      />
    </label>
  );
}
