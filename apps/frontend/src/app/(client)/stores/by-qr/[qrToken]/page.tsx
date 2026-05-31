"use client";

import { useEffect, useState } from "react";
import { useRouter } from "next/navigation";
import { getShopBySlug, recordStoreVisit } from "@/lib/services";

export default function ByQrPage({
  params,
}: {
  params: { qrToken: string };
}) {
  const router = useRouter();
  const [error, setError] = useState(false);

  useEffect(() => {
    void getShopBySlug(params.qrToken)
      .then((shop) => {
        if (!shop) {
          setError(true);
          return;
        }

        void recordStoreVisit(shop.id, "qr_code").catch((err) => {
          const status = (err as { response?: { status?: number } }).response?.status;
          if (status !== 401 && status !== 403) {
            console.error("[store-qr] recordStoreVisit failed", { shopId: shop.id, err });
          }
        });

        router.replace(`/stores/${shop.id}/catalog`);
      })
      .catch(() => setError(true));
  }, [params.qrToken, router]);

  if (error) {
    return (
      <div className="flex min-h-screen items-center justify-center p-6 text-center">
        <div>
          <p className="text-sm text-muted">QR code non reconnu ou supérette indisponible.</p>
          <a href="/" className="mt-3 block text-sm font-semibold text-primary">
            Retour à l&apos;accueil
          </a>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center">
      <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
    </div>
  );
}
