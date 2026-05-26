"use client";

import { useState, useEffect } from "react";
import { useQuery } from "@tanstack/react-query";
import { useRouter } from "next/navigation";
import { SearchInput } from "@/components/ui/SearchInput";
import { searchStores } from "@/lib/services/store-search.service";
import type { StoreSearchItem } from "@/types";

export function StoreSearchCombobox() {
  const [inputValue, setInputValue] = useState("");
  const [debouncedQuery, setDebouncedQuery] = useState("");
  const [isOpen, setIsOpen] = useState(false);
  const router = useRouter();

  useEffect(() => {
    const timer = setTimeout(() => setDebouncedQuery(inputValue), 400);
    return () => clearTimeout(timer);
  }, [inputValue]);

  const { data, isLoading } = useQuery({
    queryKey: ["store-search", debouncedQuery],
    queryFn: () => searchStores(debouncedQuery),
    enabled: debouncedQuery.trim().length >= 2,
  });

  const showDropdown = isOpen && inputValue.trim().length >= 2;

  function handleSelect(item: StoreSearchItem) {
    router.push(`/stores/${item.store_id}`);
    setInputValue("");
    setIsOpen(false);
  }

  return (
    <div className="relative mb-4 md:max-w-lg">
      <SearchInput
        placeholder="Nom de la supérette, quartier…"
        value={inputValue}
        onChange={(e) => {
          setInputValue(e.target.value);
          setIsOpen(true);
        }}
        onFocus={() => setIsOpen(true)}
        onBlur={() => setTimeout(() => setIsOpen(false), 200)}
      />
      {showDropdown && (
        <div className="absolute left-0 right-0 top-full z-50 mt-1 overflow-hidden rounded-md border border-line bg-white shadow-card">
          {isLoading && (
            <div className="space-y-2 p-3">
              {[1, 2, 3].map((i) => (
                <div
                  key={i}
                  className="h-10 animate-pulse rounded bg-product-tile"
                />
              ))}
            </div>
          )}
          {!isLoading && data?.items.length === 0 && (
            <p className="px-4 py-3 text-sm text-muted">
              Aucune supérette trouvée pour «&nbsp;{debouncedQuery}&nbsp;»
            </p>
          )}
          {!isLoading && data && data.items.length > 0 && (
            <ul role="list">
              {data.items.slice(0, 8).map((item) => (
                <li key={item.store_id}>
                  <button
                    type="button"
                    className="flex w-full items-center gap-3 px-4 py-3 text-left hover:bg-product-tile"
                    onMouseDown={() => handleSelect(item)}
                  >
                    <span aria-hidden="true" className="text-lg">
                      🏪
                    </span>
                    <div className="min-w-0 flex-1">
                      <strong className="block truncate text-sm">
                        {item.name}
                      </strong>
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
