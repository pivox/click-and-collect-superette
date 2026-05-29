"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Pill, PillRow } from "@/components/ui/Pill";
import { Card } from "@/components/ui/Card";
import { Badge, orderStatusBadge } from "@/components/ui/Badge";
import { Button } from "@/components/ui/Button";
import { listMyKadhias } from "@/lib/services";
import type { KadhiaListItem } from "@/lib/services/kadhia.service";
import { formatTnd, formatRelativeDate } from "@/lib/format";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import type { OrderStatus } from "@/types";

type TabKey = "draft" | "submitted";

function EmptyState({ tab }: { tab: TabKey }) {
  if (tab === "draft") {
    return (
      <div className="mt-8 text-center">
        <p className="text-sm text-muted">Aucune Kadhia en cours.</p>
        <p className="mt-1 text-sm text-muted">
          Scanne le QR code d&apos;une supérette pour commencer.
        </p>
        <Link href="/stores" className="mt-4 inline-block">
          <Button>Trouver une supérette</Button>
        </Link>
      </div>
    );
  }
  return (
    <p className="mt-8 text-center text-sm text-muted">
      Aucune Kadhia envoyée pour le moment.
    </p>
  );
}

function KadhiaCard({ item }: { item: KadhiaListItem }) {
  const { tone, label } = orderStatusBadge(item.status as OrderStatus);
  const isDraft = item.status === "draft";
  const title = item.notes?.trim() || null;

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
            {item.linesCount} article{item.linesCount > 1 ? "s" : ""} · {formatTnd(item.totalTnd)}
          </p>
        </div>
        <Badge tone={tone} className="shrink-0">
          {label}
        </Badge>
      </div>

      <p className="text-xs text-muted">{formatRelativeDate(item.updatedAt)}</p>

      <div className="flex gap-2">
        {isDraft ? (
          <Link href={`/kadhia/${item.id}`}>
            <Button>Continuer</Button>
          </Link>
        ) : (
          <Link href={`/kadhia/${item.id}`}>
            <Button>Voir</Button>
          </Link>
        )}
      </div>
    </Card>
  );
}

export default function MesKadhiasPage() {
  const router = useRouter();
  const { user, isLoading } = useClientAuth();
  const [tab, setTab] = useState<TabKey>("draft");
  const [kadhias, setKadhias] = useState<KadhiaListItem[]>([]);
  const [fetching, setFetching] = useState(true);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (!isLoading && !user) {
      router.push("/login?redirect=/kadhia");
    }
  }, [isLoading, user, router]);

  useEffect(() => {
    if (isLoading || !user) return;
    setFetching(true);
    setError(null);
    void listMyKadhias(tab)
      .then((r) => {
        setKadhias(r.items);
        setFetching(false);
      })
      .catch(() => {
        setError("Impossible de charger les Kadhia. Réessaie.");
        setFetching(false);
      });
  }, [tab, isLoading, user]);

  if (isLoading || !user) return null;

  return (
    <>
      <TopBar title="Mes Kadhia" subtitle="Brouillons et commandes envoyées" />

      <PillRow className="mb-4">
        <Pill active={tab === "draft"} onClick={() => setTab("draft")}>
          En cours
        </Pill>
        <Pill active={tab === "submitted"} onClick={() => setTab("submitted")}>
          Envoyées
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
        <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">{error}</p>
      ) : kadhias.length === 0 ? (
        <EmptyState tab={tab} />
      ) : (
        <section className="grid gap-3">
          {kadhias.map((k) => (
            <KadhiaCard key={k.id} item={k} />
          ))}
        </section>
      )}
    </>
  );
}
