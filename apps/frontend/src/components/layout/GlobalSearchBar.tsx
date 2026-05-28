"use client";

import { useState, useEffect, useRef } from "react";
import { useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { Search } from "lucide-react";
import { searchStores } from "@/lib/services/store-search.service";
import type { StoreSearchItem } from "@/types";

const MAX_RESULTS = 6;

/**
 * Desktop-only global search bar — sits in the top header above page content.
 * Searches stores by name and navigates directly to their catalog on selection.
 */
export function GlobalSearchBar() {
  const [inputValue, setInputValue] = useState("");
  const [debouncedQuery, setDebouncedQuery] = useState("");
  const [isOpen, setIsOpen] = useState(false);
  const blurTimerRef = useRef<ReturnType<typeof setTimeout> | null>(null);
  const router = useRouter();

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(inputValue), 400);
    return () => clearTimeout(timer);
  }, [inputValue]);

  useEffect(() => {
    return () => {
      if (blurTimerRef.current) clearTimeout(blurTimerRef.current);
    };
  }, []);

  const { data, isLoading } = useQuery({
    queryKey: ["global-search", debouncedQuery],
    queryFn: () => searchStores(debouncedQuery),
    enabled: debouncedQuery.trim().length >= 2,
  });

  const isPending =
    inputValue.trim().length >= 2 &&
    inputValue.trim() !== debouncedQuery.trim();

  const showDropdown = isOpen && inputValue.trim().length >= 2;

  function handleSelect(item: StoreSearchItem) {
    router.push(`/stores/${item.store_id}/catalog`);
    setInputValue("");
    setIsOpen(false);
  }

  return (
    <div className="relative flex-1 max-w-xl">
      <div className="flex items-center gap-2 rounded-xl border border-line bg-white px-4 py-2.5 shadow-[0_4px_12px_rgba(18,30,20,.05)]">
        <Search size={16} className="shrink-0 text-muted" />
        <input
          type="search"
          placeholder="Rechercher une supérette…"
          value={inputValue}
          role="combobox"
          aria-expanded={showDropdown}
          aria-haspopup="listbox"
          aria-controls="global-search-listbox"
          aria-label="Rechercher une supérette"
          className="flex-1 bg-transparent text-sm outline-none placeholder:text-muted"
          onChange={(e) => {
            setInputValue(e.target.value);
            setIsOpen(true);
          }}
          onFocus={() => setIsOpen(true)}
          onBlur={() => {
            blurTimerRef.current = setTimeout(() => setIsOpen(false), 200);
          }}
        />
      </div>

      {showDropdown && (
        <div className="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-xl border border-line bg-white shadow-[0_14px_34px_rgba(18,30,20,.10)]">
          {(isLoading || isPending) && (
            <div className="space-y-2 p-3">
              {[1, 2, 3].map((i) => (
                <div key={i} className="h-10 animate-pulse rounded-lg bg-soft" />
              ))}
            </div>
          )}
          {!isLoading && !isPending && data?.items.length === 0 && (
            <p className="px-4 py-3 text-sm text-muted">
              Aucune supérette pour «&nbsp;{debouncedQuery}&nbsp;»
            </p>
          )}
          {!isLoading && !isPending && data && data.items.length > 0 && (
            <ul role="listbox" id="global-search-listbox">
              {data.items.slice(0, MAX_RESULTS).map((item) => (
                <li key={item.store_id}>
                  <button
                    type="button"
                    className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-soft"
                    onMouseDown={() => handleSelect(item)}
                  >
                    <span aria-hidden="true" className="text-lg">🏪</span>
                    <div className="min-w-0 flex-1">
                      <strong className="block truncate text-sm">{item.name}</strong>
                      {item.city && (
                        <span className="text-xs text-muted">{item.city}</span>
                      )}
                    </div>
                    {item.is_active && (
                      <span className="shrink-0 rounded-full bg-green-100 px-2 py-0.5 text-xs font-medium text-green-700">
                        Ouverte
                      </span>
                    )}
                  </button>
                </li>
              ))}
            </ul>
          )}
        </div>
      )}
    </div>
  );
}
