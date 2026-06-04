"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Card } from "@/components/ui/Card";
import { Button, getButtonClassName } from "@/components/ui/Button";
import { Summary, SummaryRow } from "@/components/ui/Summary";
import { StickyBottom } from "@/components/layout/StickyBottom";
import { KadhiaLineRow } from "@/components/product/KadhiaLineRow";
import { fetchKadhia, updateLineQuantity, discardKadhia, patchKadhiaNotes } from "@/lib/services";
import { formatTnd } from "@/lib/format";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import type { Kadhia } from "@/types";

const MAX_NOTE_LENGTH = 500;

function KadhiaNote({
  kadhia,
  onSaved,
}: {
  kadhia: Kadhia;
  onSaved: (updated: Kadhia) => void;
}) {
  const { t } = useClientLocale();
  const isDraft = kadhia.status === "draft";
  const [editing, setEditing] = useState(false);
  const [draft, setDraft] = useState(kadhia.notes ?? "");
  const [saving, setSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const handleSave = async () => {
    setSaving(true);
    setError(null);
    try {
      const updated = await patchKadhiaNotes(kadhia.id, draft.trim() || null);
      onSaved(updated);
      setEditing(false);
    } catch (err) {
      console.error("[kadhia-note] patchKadhiaNotes failed", { kadhiaId: kadhia.id, err });
      const status = (err as { response?: { status?: number } }).response?.status;
      if (status === 422) {
        setError(t("client.kadhia.noteSentError"));
        setEditing(false);
      } else {
        setError(t("client.kadhia.noteSaveError"));
      }
    } finally {
      setSaving(false);
    }
  };

  const handleCancel = () => {
    setDraft(kadhia.notes ?? "");
    setError(null);
    setEditing(false);
  };

  if (!isDraft) {
    if (!kadhia.notes) return null;
    return (
      <Card className="mt-4">
        <p className="text-xs font-bold text-muted uppercase tracking-wide mb-1">{t("client.kadhia.noteTitle")}</p>
        <p className="text-sm">{kadhia.notes}</p>
      </Card>
    );
  }

  if (!editing) {
    return (
      <Card className="mt-4">
        <div className="flex items-start justify-between gap-2">
          <div className="min-w-0">
            <p className="text-xs font-bold text-muted uppercase tracking-wide mb-1">
              {t("client.kadhia.noteTitle")}
            </p>
            {kadhia.notes ? (
              <p className="text-sm">{kadhia.notes}</p>
            ) : (
              <p className="text-sm text-muted italic">
                {t("client.kadhia.notePlaceholder")}
              </p>
            )}
          </div>
          <button
            type="button"
            aria-label={kadhia.notes ? t("client.kadhia.noteEditAria") : t("client.kadhia.noteAddAria")}
            onClick={() => {
              setDraft(kadhia.notes ?? "");
              setEditing(true);
            }}
            className="shrink-0 text-xs font-bold text-primary underline"
          >
            {kadhia.notes ? t("client.kadhia.noteEdit") : t("client.kadhia.noteAdd")}
          </button>
        </div>
      </Card>
    );
  }

  return (
    <Card className="mt-4">
      <label
        htmlFor="kadhia-note"
        className="text-xs font-bold text-muted uppercase tracking-wide mb-2 block"
      >
        {t("client.kadhia.noteTitle")}
      </label>
      <textarea
        id="kadhia-note"
        autoFocus
        value={draft}
        onChange={(e) => setDraft(e.target.value)}
        maxLength={MAX_NOTE_LENGTH}
        rows={3}
        placeholder={t("client.kadhia.noteTextareaPlaceholder")}
        className="w-full resize-none rounded-md border border-line bg-soft px-3 py-2 text-sm focus:border-primary focus:outline-none focus:ring-1 focus:ring-primary"
      />
      <div className="mt-1 flex items-center justify-between gap-2">
        <span className="text-xs text-muted">
          {draft.length}/{MAX_NOTE_LENGTH}
        </span>
        <div className="flex gap-2">
          <Button variant="ghost" size="md" onClick={handleCancel} disabled={saving}>
            {t("client.kadhia.cancel")}
          </Button>
          <Button size="md" onClick={() => void handleSave()} disabled={saving}>
            {saving ? t("client.kadhia.saving") : t("client.kadhia.save")}
          </Button>
        </div>
      </div>
      {error && (
        <p className="mt-2 text-sm text-red-600">{error}</p>
      )}
    </Card>
  );
}

export default function KadhiaDetailPage({
  params,
}: {
  params: { kadhiaId: string };
}) {
  const { kadhiaId } = params;
  const router = useRouter();
  const searchParams = useSearchParams();
  const isPartialContext = searchParams.get("context") === "partially_accepted";
  const { user, isLoading } = useClientAuth();
  const { t } = useClientLocale();
  const [kadhia, setKadhia] = useState<Kadhia | null>(null);
  const [loadError, setLoadError] = useState(false);
  const [quantityError, setQuantityError] = useState(false);
  const [discarding, setDiscarding] = useState(false);
  const [discardError, setDiscardError] = useState(false);

  useEffect(() => {
    if (!isLoading && !user) {
      router.push(`/login?redirect=/kadhia/${kadhiaId}`);
    }
  }, [isLoading, user, router, kadhiaId]);

  useEffect(() => {
    if (isLoading || !user) return;
    void fetchKadhia(kadhiaId)
      .then(setKadhia)
      .catch(() => setLoadError(true));
  }, [kadhiaId, isLoading, user]);

  const onDiscard = async () => {
    if (!kadhia?.shopId) return;
    if (!window.confirm(t("client.kadhia.deleteConfirm"))) return;
    setDiscarding(true);
    setDiscardError(false);
    try {
      await discardKadhia(kadhia.shopId);
      router.push("/kadhia");
    } catch (err) {
      console.error("[kadhia-detail] discardKadhia failed", { shopId: kadhia.shopId, err });
      setDiscarding(false);
      setDiscardError(true);
    }
  };

  const onQuantity = async (lineId: string, q: number) => {
    if (!kadhia?.shopId || !kadhia.id) return;
    setQuantityError(false);
    try {
      const next = await updateLineQuantity(kadhia.shopId, kadhia.id, lineId, q);
      setKadhia(next);
    } catch {
      setQuantityError(true);
    }
  };

  if (isLoading || !user) return null;

  if (loadError) {
    return (
      <>
        <TopBar title="Kadhia" backHref="/kadhia" />
        <Card className="text-center">
          <p className="text-sm text-muted">{t("client.kadhia.notFound")}</p>
          <Link href="/kadhia" className={getButtonClassName({ className: "mt-4" })}>
            {t("client.kadhia.backToList")}
          </Link>
        </Card>
      </>
    );
  }

  if (!kadhia) {
    return (
      <>
        <TopBar title="Kadhia" backHref="/kadhia" />
        <div className="animate-pulse grid gap-2.5">
          {[1, 2, 3].map((i) => (
            <div key={i} className="h-20 rounded-lg bg-gray-100" />
          ))}
        </div>
      </>
    );
  }

  const isDraft = kadhia.status === "draft";
  const articleCount = kadhia.lines.reduce((a, l) => a + l.quantity, 0);
  const catalogHref = `/stores/${kadhia.shopId}/catalog`;

  return (
    <>
      <TopBar
        title={kadhia.notes ?? t("client.kadhia.defaultTitle")}
        subtitle={
          kadhia.lines.length === 0
            ? t("client.kadhia.noItems")
            : `${articleCount} ${articleCount > 1 ? t("client.itemsPlural") : t("client.itemsSingular")}`
        }
        backHref="/kadhia"
      />

      {isPartialContext && (
        <div className="mb-4 rounded-lg border px-4 py-3" style={{ background: "var(--status-wait-bg)", borderColor: "var(--status-wait)" }}>
          <p className="text-sm font-bold" style={{ color: "var(--status-wait)" }}>
            {t("client.kadhia.partialTitle")}
          </p>
          <p className="mt-1 text-sm" style={{ color: "var(--status-wait)" }}>
            {t("client.kadhia.partialBody")}
          </p>
        </div>
      )}

      {kadhia.lines.length === 0 ? (
        <>
          <KadhiaNote kadhia={kadhia} onSaved={setKadhia} />
          <Card className="text-center mt-4">
            <h3 className="mt-2 text-h3 font-extrabold">{t("client.kadhia.emptyTitle")}</h3>
            <p className="mt-2 text-sm text-muted">
              {t("client.kadhia.emptyBody")}
            </p>
            <Link href={catalogHref} className={getButtonClassName({ className: "mt-4" })}>
              {t("client.kadhia.goToCatalog")}
            </Link>
          </Card>
        </>
      ) : (
        <>
          <KadhiaNote kadhia={kadhia} onSaved={setKadhia} />

          <section className="mt-4 grid gap-2.5">
            {kadhia.lines.map((l) => (
              <KadhiaLineRow
                key={l.id}
                line={l}
                onQuantity={isDraft ? onQuantity : undefined}
              />
            ))}
          </section>

          {quantityError && (
            <p className="mt-2 rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">
              {t("client.kadhia.quantityError")}
            </p>
          )}

          <Card className="mt-4">
            <Summary>
              <SummaryRow label={t("client.kadhia.subtotal")} value={formatTnd(kadhia.totalTnd)} />
              <SummaryRow label={t("client.kadhia.service")} value={formatTnd("0.000")} />
              <SummaryRow total label={t("client.kadhia.totalEstimated")} value={formatTnd(kadhia.totalTnd)} />
            </Summary>
          </Card>

          <p className="mt-3 text-xs text-muted leading-relaxed">
            {t("client.kadhia.priceNote")}
          </p>

          {isDraft && (
            <div className="mt-4 text-center">
              <button
                onClick={onDiscard}
                disabled={discarding}
                className="text-sm text-red-500 underline disabled:opacity-50"
              >
                {discarding ? t("client.kadhia.deleting") : t("client.kadhia.delete")}
              </button>
              {discardError && (
                <p className="mt-2 text-sm text-red-600">{t("client.kadhia.deleteError")}</p>
              )}
            </div>
          )}

          <StickyBottom>
            {isDraft ? (
              <Link href="/kadhia/slot" className={getButtonClassName({ full: true })}>
                {t("client.kadhia.chooseSlot")}
              </Link>
            ) : kadhia.orderId ? (
              <Link
                href={`/orders/${kadhia.orderId}`}
                className={getButtonClassName({ full: true })}
              >
                {t("client.kadhia.trackOrder")}
              </Link>
            ) : null}
          </StickyBottom>
        </>
      )}
    </>
  );
}
