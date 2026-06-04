"use client";

import { useCallback, useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { Pagination } from "@/components/ui/Pagination";
import { getButtonClassName } from "@/components/ui/Button";
import { listMyKadhias } from "@/lib/services";
import type { KadhiaListItem } from "@/lib/services/kadhia.service";
import { formatTnd, formatRelativeDate } from "@/lib/format";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import type { OrderStatus } from "@/types";

type TabKey = "draft" | "submitted";

function EmptyState({ tab }: { tab: TabKey }) {
  const { t } = useClientLocale();
  if (tab === "draft") {
    return (
      <div className="mt-8 text-center">
        <p className="text-sm text-muted">{t("client.kadhia.emptyDraft")}</p>
        <p className="mt-1 text-sm text-muted">{t("client.kadhia.emptyDraftHint")}</p>
        <Link href="/stores" className={getButtonClassName({ className: "mt-4" })}>
          {t("client.kadhia.findStore")}
        </Link>
      </div>
    );
  }
  return (
    <p className="mt-8 text-center text-sm text-muted">
      {t("client.kadhia.emptySubmitted")}
    </p>
  );
}

function KadhiaCard({ item }: { item: KadhiaListItem }) {
  const { t } = useClientLocale();
  const { tone, labelKey } = orderStatusBadge(item.status as OrderStatus);
  const isDraft = item.status === "draft";
  const title = item.notes?.trim() || null;
  const itemsWord = item.linesCount > 1 ? t("client.itemsPlural") : t("client.itemsSingular");

  return (
    <Card as="article" className="flex flex-col gap-3">
      <div className="flex items-start justify-between gap-2">
        <div className="min-w-0">
          {title ? (
            <>
              <p className="truncate font-extrabold text-sm">{title}</p>
              <p className="mt-0.5 text-xs text-muted">{item.storeName}</p>
            </>
          ) : (
            <p className="truncate font-extrabold text-sm">{item.storeName}</p>
          )}
          <p className="mt-0.5 text-xs text-muted">
            {item.linesCount} {itemsWord} · {formatTnd(item.totalTnd)}
          </p>
        </div>
        <Badge tone={tone} className="shrink-0">
          {t(labelKey)}
        </Badge>
      </div>

      <p className="text-xs text-muted">{formatRelativeDate(item.updatedAt)}</p>

      <div className="flex gap-2">
        {isDraft ? (
          <Link href={`/kadhia/${item.id}`} className={getButtonClassName()}>
            {t("client.kadhia.continue")}
          </Link>
        ) : (
          <Link href={`/kadhia/${item.id}`} className={getButtonClassName()}>
            {t("client.kadhia.view")}
          </Link>
        )}
      </div>
    </Card>
  );
}

export default function MesKadhiasPage() {
  const router = useRouter();
  const { user, isLoading } = useClientAuth();
  const { t } = useClientLocale();
  const [tab, setTab] = useState<TabKey>("draft");
  const [page, setPage] = useState(1);
  const [kadhias, setKadhias] = useState<KadhiaListItem[]>([]);
  const [pages, setPages] = useState(1);
  const [fetching, setFetching] = useState(true);
  const [error, setError] = useState(false);

  useEffect(() => {
    if (!isLoading && !user) {
      router.push("/login?redirect=/kadhia");
    }
  }, [isLoading, user, router]);

  const load = useCallback(async () => {
    if (isLoading || !user) return;
    setFetching(true);
    setError(false);
    try {
      const r = await listMyKadhias(tab, page);
      setKadhias(r.items);
      setPages(r.pages);
      setFetching(false);
    } catch {
      setError(true);
      setFetching(false);
    }
  }, [tab, page, isLoading, user]);

  useEffect(() => { void load(); }, [load]);

  const handleTabChange = (newTab: TabKey) => {
    setTab(newTab);
    setPage(1);
  };

  if (isLoading || !user) return null;

  return (
    <>
      <TopBar title={t("client.kadhia.listTitle")} subtitle={t("client.kadhia.listSubtitle")} />

      <PillRow className="mb-4">
        <Pill active={tab === "draft"} onClick={() => handleTabChange("draft")}>
          {t("client.kadhia.tabDraft")}
        </Pill>
        <Pill active={tab === "submitted"} onClick={() => handleTabChange("submitted")}>
          {t("client.kadhia.tabSubmitted")}
        </Pill>
      </PillRow>

      {fetching ? (
        <div className="grid gap-3">
          {[1, 2].map((i) => (
            <div
              key={i}
              className="animate-pulse rounded-lg border border-line bg-card p-4 shadow-soft"
            >
              <div className="mb-2 h-4 w-2/3 rounded bg-gray-200" />
              <div className="mb-3 h-3 w-1/3 rounded bg-gray-200" />
              <div className="h-8 w-24 rounded-lg bg-gray-200" />
            </div>
          ))}
        </div>
      ) : error ? (
        <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">{t("client.kadhia.listError")}</p>
      ) : kadhias.length === 0 ? (
        <EmptyState tab={tab} />
      ) : (
        <>
          <section className="grid gap-3">
            {kadhias.map((k) => (
              <KadhiaCard key={k.id} item={k} />
            ))}
          </section>
          <Pagination page={page} pages={pages} onPageChange={setPage} />
        </>
      )}
    </>
  );
}
