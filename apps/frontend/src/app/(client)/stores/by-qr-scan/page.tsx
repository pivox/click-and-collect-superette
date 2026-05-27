"use client";

import { useState } from "react";
import { useRouter } from "next/navigation";
import { TopBar } from "@/components/layout/TopBar";
import { Button } from "@/components/ui/Button";
import { Card } from "@/components/ui/Card";

export default function ByQrScanPage() {
  const router = useRouter();
  const [token, setToken] = useState("");

  const handleSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    const t = token.trim();
    if (!t) return;
    router.push(`/stores/by-qr/${t}`);
  };

  return (
    <>
      <TopBar
        title="Accéder à une supérette"
        subtitle="Saisis le code inscrit sur l'affiche QR"
        backHref="/"
      />
      <Card>
        <p className="mb-3 text-sm text-muted">
          Le code est visible sur l&apos;affiche à l&apos;entrée de la supérette.
        </p>
        <form onSubmit={handleSubmit} className="flex flex-col gap-3">
          <input
            className="w-full rounded-lg border border-line bg-white px-3 py-2.5 text-sm outline-none placeholder:text-muted focus:border-primary"
            placeholder="Ex : superette-el-amel"
            value={token}
            onChange={(e) => setToken(e.target.value)}
            autoFocus
          />
          <Button type="submit" full disabled={!token.trim()}>
            Accéder à la supérette
          </Button>
        </form>
      </Card>
    </>
  );
}
