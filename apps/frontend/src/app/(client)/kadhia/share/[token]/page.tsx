"use client";

import { useEffect, useState } from "react";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { Card } from "@/components/ui/Card";
import { getButtonClassName } from "@/components/ui/Button";
import { useClientAuth } from "@/lib/auth/ClientAuthContext";
import { useClientLocale } from "@/lib/i18n/ClientLocaleContext";
import { joinKadhiaShareLink } from "@/lib/services";

export default function KadhiaSharePage({
  params,
}: {
  params: { token: string };
}) {
  const token = params.token;
  const router = useRouter();
  const { user, isLoading } = useClientAuth();
  const { t } = useClientLocale();
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isLoading) return;
    if (!user) {
      router.push(`/login?redirect=/kadhia/share/${encodeURIComponent(token)}`);
      return;
    }

    let cancelled = false;
    void joinKadhiaShareLink(token)
      .then((result) => {
        if (!cancelled) router.push(`/kadhia/${result.kadhiaId}`);
      })
      .catch((err) => {
        if (cancelled) return;
        const status = (err as { response?: { status?: number } }).response?.status;
        setError(status === 404 ? t("client.kadhia.shareInvalid") : t("client.kadhia.shareJoinError"));
      });

    return () => {
      cancelled = true;
    };
  }, [isLoading, user, router, token, t]);

  if (isLoading || (!user && !error)) return null;

  if (error) {
    return (
      <Card className="text-center">
        <p className="text-sm text-muted">{error}</p>
        <Link href="/kadhia" className={getButtonClassName({ className: "mt-4" })}>
          {t("client.kadhia.backToList")}
        </Link>
      </Card>
    );
  }

  return (
    <Card className="text-center">
      <p className="text-sm text-muted">{t("client.kadhia.shareJoining")}</p>
    </Card>
  );
}
