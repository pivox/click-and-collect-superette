import Link from "next/link";

/**
 * Root landing — picks between mobile and desktop journeys.
 * In production you'd serve the mobile flow on phones (via user-agent /
 * viewport breakpoints) and the desktop flow on larger screens.
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
        Choisis ton parcours pour explorer le prototype intégré. Les deux
        partagent les mêmes services / mocks et les mêmes composants UI.
      </p>

      <div className="mt-8 grid w-full gap-4 sm:grid-cols-2">
        <Link
          href="/(client)"
          className="rounded-xl border border-line bg-white p-6 text-left shadow-card transition hover:shadow-floating"
        >
          <strong className="text-h2 font-black block">📱 Parcours mobile</strong>
          <p className="mt-2 text-sm text-muted">
            Phone shell, parcours scan → store → catalogue → Kadhia → créneau →
            suivi → QR retrait.
          </p>
        </Link>
        <Link
          href="/desktop"
          className="rounded-xl border border-line bg-white p-6 text-left shadow-card transition hover:shadow-floating"
        >
          <strong className="text-h2 font-black block">💻 Parcours desktop</strong>
          <p className="mt-2 text-sm text-muted">
            Sidebar + topbar + grille catalogue + panier latéral + suivi de
            commande sur une page.
          </p>
        </Link>
      </div>
    </main>
  );
}
