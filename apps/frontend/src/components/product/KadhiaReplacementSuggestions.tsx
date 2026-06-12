"use client";

import { useState } from "react";
import type { ProductOffer } from "@/types";
import type { KadhiaReplacementLine } from "@/lib/services/kadhia.service";
import { Card } from "@/components/ui/Card";
import { Button } from "@/components/ui/Button";
import { formatTnd } from "@/lib/format";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import { ProductThumbnail } from "./ProductThumbnail";

export interface KadhiaReplacementSuggestionsProps {
  lines: KadhiaReplacementLine[];
  /** Replaces the unavailable line by the chosen alternative (add new, then remove old). */
  onReplace: (line: KadhiaReplacementLine, alternative: ProductOffer) => Promise<void>;
}

/**
 * Replacement suggestions shown on the Kadhia detail when some lines point to
 * a product that is no longer orderable. One card per unavailable line with up
 * to 3 same-category alternatives from the same supérette.
 */
export function KadhiaReplacementSuggestions({ lines, onReplace }: KadhiaReplacementSuggestionsProps) {
  const { t } = useClientLocale();
  const [busyId, setBusyId] = useState<string | null>(null);
  const [error, setError] = useState(false);

  if (lines.length === 0) return null;

  const handleReplace = async (line: KadhiaReplacementLine, alternative: ProductOffer) => {
    setBusyId(alternative.id);
    setError(false);
    try {
      await onReplace(line, alternative);
    } catch {
      setError(true);
    } finally {
      setBusyId(null);
    }
  };

  return (
    <section className="mt-4">
      <h3 className="mb-2 text-h3 font-extrabold">{t("client.replacements.title")}</h3>
      {error && (
        <p className="mb-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
          {t("client.replacements.error")}
        </p>
      )}
      <div className="grid gap-2.5">
        {lines.map((line) => (
          <Card key={line.lineId} compact>
            <p className="text-sm">
              <strong>{line.productName}</strong> {t("client.replacements.unavailable")}
            </p>
            {line.alternatives.length === 0 ? (
              <p className="mt-2 text-xs text-muted">{t("client.replacements.none")}</p>
            ) : (
              <ul className="mt-2 grid gap-2">
                {line.alternatives.map((alternative) => (
                  <li key={alternative.id} className="flex items-center gap-3">
                    <ProductThumbnail
                      image={alternative.image}
                      nameFr={alternative.nameFr}
                      emoji={alternative.emoji}
                      sizes="40px"
                      className="h-10 w-10 flex-shrink-0 rounded-md text-xl"
                    />
                    <div className="min-w-0 flex-1">
                      <span className="block truncate text-sm font-bold">{alternative.nameFr}</span>
                      <span className="block text-xs text-muted">
                        {formatTnd(alternative.effectivePriceTnd ?? alternative.priceTnd)}
                      </span>
                    </div>
                    <Button
                      size="md"
                      variant="ghost"
                      disabled={busyId !== null}
                      onClick={() => void handleReplace(line, alternative)}
                    >
                      {busyId === alternative.id ? "…" : t("client.replacements.replace")}
                    </Button>
                  </li>
                ))}
              </ul>
            )}
          </Card>
        ))}
      </div>
    </section>
  );
}
