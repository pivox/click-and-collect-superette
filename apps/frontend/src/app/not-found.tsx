import Link from "next/link";

export default function NotFound() {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-6 bg-soft px-6 text-center">
      <div className="grid h-16 w-16 place-items-center rounded-2xl bg-primary text-white text-2xl font-black shadow-cta">
        K
      </div>
      <div>
        <p className="text-xs font-extrabold uppercase tracking-widest text-primary">
          Kadhia
        </p>
        <h1 className="mt-2 text-4xl font-black text-ink">404</h1>
        <p className="mt-2 text-base text-muted">
          Cette page n&apos;existe pas ou a été déplacée.
        </p>
      </div>
      <Link
        href="/"
        className="inline-flex min-h-[48px] items-center justify-center rounded-md bg-primary px-6 text-sm font-black text-white shadow-cta transition-colors hover:bg-primary-dark active:translate-y-px"
      >
        Retour à l&apos;accueil
      </Link>
    </div>
  );
}
