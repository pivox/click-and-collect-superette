'use client';

export default function Error({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <div className="flex min-h-screen flex-col items-center justify-center gap-4 p-8 text-center">
      <h1 className="text-2xl font-black">Une erreur est survenue</h1>
      <p className="text-sm text-gray-500">Le chargement a échoué. Réessayez.</p>
      <button
        onClick={reset}
        className="rounded-lg bg-black px-4 py-2 text-sm font-semibold text-white"
      >
        Réessayer
      </button>
    </div>
  );
}
