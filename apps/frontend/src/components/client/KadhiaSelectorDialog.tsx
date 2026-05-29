"use client";

import { Button } from "@/components/ui/Button";
import { formatTnd } from "@/lib/format";
import type { KadhiaListItem } from "@/lib/services/kadhia.service";

interface Props {
  drafts: KadhiaListItem[];
  onSelect: (kadhiaId: string) => void;
  onCreateNew: () => void;
}

function formatDate(iso: string): string {
  return new Date(iso).toLocaleDateString("fr-FR", { day: "numeric", month: "short" });
}

export function KadhiaSelectorDialog({ drafts, onSelect, onCreateNew }: Props) {
  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label="Choisir une Kadhia"
      className="fixed inset-0 z-50 flex items-end justify-center bg-black/40 sm:items-center"
    >
      <div className="w-full max-w-md rounded-t-2xl bg-white p-5 shadow-xl sm:rounded-2xl">
        <h2 className="mb-1 text-h2 font-extrabold">Plusieurs Kadhia en cours</h2>
        <p className="mb-4 text-sm text-muted">Laquelle veux-tu continuer ?</p>

        <ul className="mb-4 grid gap-2">
          {drafts.map((d) => (
            <li key={d.id}>
              <button
                type="button"
                className="w-full cursor-pointer rounded-lg border border-line bg-card p-3 text-left shadow-soft hover:border-primary hover:shadow-md"
                onClick={() => onSelect(d.id)}
              >
                <div className="flex items-center justify-between">
                  <span className="text-sm font-bold">
                    {d.linesCount} article{d.linesCount > 1 ? "s" : ""}
                  </span>
                  <span className="text-sm font-extrabold text-primary">{formatTnd(d.totalTnd)}</span>
                </div>
                <p className="mt-0.5 text-xs text-muted">Modifiée le {formatDate(d.updatedAt)}</p>
              </button>
            </li>
          ))}
        </ul>

        <Button full variant="secondary" onClick={onCreateNew}>
          Commencer une nouvelle Kadhia
        </Button>
      </div>
    </div>
  );
}
