import Link from "next/link";

/**
 * Root landing — entry point for the mobile-first client journey.
 */
export default function RootPage() {
  return (
    <main className="mx-auto flex min-h-screen max-w-4xl flex-col items-center justify-center px-6 py-12 text-center">
      <span className="rounded-full bg-soft px-3 py-1.5 text-xs font-extrabold text-primary-dark">
        Click &amp; Collect Supérette
      </span>
      <h1 className="mt-4 text-display font-black tracking-tight">
        Bienvenue sur Kadhia
      </h1>
      <p className="mx-auto mt-3 max-w-xl text-base text-muted">
        Explore le prototype du parcours client mobile.
      </p>

      <div className="mt-8 grid w-full gap-4">
        <Link
          href="/stores"
          className="rounded-xl border border-line bg-white p-6 text-left shadow-card transition hover:shadow-floating"
        >
          <strong className="text-h2 font-black block">📱 Parcours mobile</strong>
          <p className="mt-2 text-sm text-muted">
            Phone shell, parcours scan → store → catalogue → Kadhia → créneau →
            suivi → QR retrait.
          </p>
        </Link>
      </div>
    </main>
  );
}
