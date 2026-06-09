'use client';
import { useState, useRef, useEffect, useCallback } from 'react';
import axios from 'axios';

interface InlineCreateComboboxProps<T> {
  items: T[];
  value: string;
  onChange: (id: string) => void;
  getId: (item: T) => string;
  getLabel: (item: T) => string;
  placeholder?: string;
  onCreate: (name: string) => Promise<T>;
  disabled?: boolean;
}

export function InlineCreateCombobox<T>({
  items,
  value,
  onChange,
  getId,
  getLabel,
  placeholder = 'Rechercher ou créer…',
  onCreate,
  disabled = false,
}: InlineCreateComboboxProps<T>) {
  const selectedLabel = items.find((i) => getId(i) === value);
  const [query, setQuery] = useState(selectedLabel ? getLabel(selectedLabel) : '');
  const [open, setOpen] = useState(false);
  const [creating, setCreating] = useState(false);
  const [createError, setCreateError] = useState<string | null>(null);
  const containerRef = useRef<HTMLDivElement>(null);

  // Sync display text when external value changes
  useEffect(() => {
    const found = items.find((i) => getId(i) === value);
    setQuery(found ? getLabel(found) : '');
  }, [value, items, getId, getLabel]);

  // Close on outside click
  useEffect(() => {
    const handler = (e: MouseEvent) => {
      if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
        setOpen(false);
        // Restore display text to selected item
        const found = items.find((i) => getId(i) === value);
        setQuery(found ? getLabel(found) : '');
      }
    };
    document.addEventListener('mousedown', handler);
    return () => document.removeEventListener('mousedown', handler);
  }, [items, value, getId, getLabel]);

  const filtered = query.trim()
    ? items.filter((i) => getLabel(i).toLowerCase().includes(query.toLowerCase()))
    : items;

  const hasExactMatch = items.some(
    (i) => getLabel(i).toLowerCase() === query.trim().toLowerCase(),
  );

  const handleSelect = (item: T) => {
    onChange(getId(item));
    setQuery(getLabel(item));
    setOpen(false);
    setCreateError(null);
  };

  const handleCreate = useCallback(async () => {
    const name = query.trim();
    if (!name) return;
    setCreating(true);
    setCreateError(null);
    try {
      const created = await onCreate(name);
      onChange(getId(created));
      setQuery(getLabel(created));
      setOpen(false);
    } catch (e) {
      if (axios.isAxiosError(e) && e.response?.status === 422) {
        setCreateError('Nom déjà existant (doublon détecté).');
      } else {
        setCreateError('Erreur lors de la création. Réessayez.');
      }
    } finally {
      setCreating(false);
    }
  }, [query, onCreate, onChange, getId, getLabel]);

  const handleKeyDown = (e: React.KeyboardEvent) => {
    if (e.key === 'Escape') {
      setOpen(false);
      const found = items.find((i) => getId(i) === value);
      setQuery(found ? getLabel(found) : '');
    }
  };

  return (
    <div ref={containerRef} className="relative">
      <input
        type="text"
        value={query}
        placeholder={placeholder}
        disabled={disabled || creating}
        onChange={(e) => {
          setQuery(e.target.value);
          setOpen(true);
          setCreateError(null);
        }}
        onFocus={() => setOpen(true)}
        onKeyDown={handleKeyDown}
        className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:opacity-50"
      />
      {creating && (
        <span className="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-muted">
          Création…
        </span>
      )}
      {createError && (
        <p className="mt-1 text-xs text-danger">{createError}</p>
      )}
      {open && !creating && (
        <ul className="absolute z-50 mt-1 max-h-48 w-full overflow-auto rounded-md border border-line bg-white shadow-lg">
          {!hasExactMatch && query.trim() && (
            <li>
              <button
                type="button"
                onMouseDown={(e) => { e.preventDefault(); void handleCreate(); }}
                className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-primary hover:bg-soft"
              >
                <span className="font-semibold">+</span>
                Créer «{query.trim()}»
              </button>
            </li>
          )}
          {filtered.length === 0 && hasExactMatch === false && query.trim() === '' && (
            <li className="px-3 py-2 text-sm text-muted">Aucun résultat.</li>
          )}
          {filtered.map((item) => (
            <li key={getId(item)}>
              <button
                type="button"
                onMouseDown={(e) => { e.preventDefault(); handleSelect(item); }}
                className={`w-full px-3 py-2 text-left text-sm hover:bg-soft ${
                  getId(item) === value ? 'font-semibold text-primary' : ''
                }`}
              >
                {getLabel(item)}
              </button>
            </li>
          ))}
        </ul>
      )}
    </div>
  );
}
