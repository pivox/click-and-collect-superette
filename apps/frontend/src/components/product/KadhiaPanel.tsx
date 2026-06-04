"use client";

import Link from "next/link";
import { Button, getButtonClassName } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { KadhiaLineRow } from "@/components/product/KadhiaLineRow";
import { formatTnd } from "@/lib/format";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import type { Kadhia } from "@/types";

interface KadhiaPanelProps {
  kadhia: Kadhia | null;
}

export function KadhiaPanel({ kadhia }: KadhiaPanelProps) {
  const { t } = useClientLocale();
  const lines = kadhia?.lines ?? [];
  const total = kadhia?.totalTnd ?? "0.000";
  const isEmpty = lines.length === 0;
  const totalQty = lines.reduce((acc, l) => acc + l.quantity, 0);
  const itemsWord = totalQty > 1 ? t("client.itemsPlural") : t("client.itemsSingular");

  return (
    <Card className="sticky top-7 rounded-xl p-5">
      <div className="mb-3 flex items-baseline justify-between">
        <h2 className="m-0 text-h2 font-extrabold">{t("client.kadhiaPanel.title")}</h2>
        {!isEmpty && (
          <span className="text-xs font-extrabold text-primary">
            {totalQty} {itemsWord}
          </span>
        )}
      </div>

      {isEmpty ? (
        <p className="py-4 text-center text-sm text-muted">
          {t("client.kadhiaPanel.empty")}
        </p>
      ) : (
        <>
          <div className="grid gap-2 mb-4">
            {lines.map((l) => (
              <KadhiaLineRow key={l.id} line={l} />
            ))}
          </div>
          <Summary>
            <SummaryRow label={t("client.kadhiaPanel.totalEstimated")} value={formatTnd(total)} total />
          </Summary>
        </>
      )}

      <div className="mt-4">
        {isEmpty ? (
          <Button full disabled>
            {t("client.kadhiaPanel.chooseSlot")}
          </Button>
        ) : (
          <Link href="/kadhia/slot" className={getButtonClassName({ full: true })}>
            {t("client.kadhiaPanel.chooseSlot")}
          </Link>
        )}
      </div>

      <p className="mt-2 text-xs text-muted">
        {t("client.kadhiaPanel.priceFrozen")}
      </p>
    </Card>
  );
}
