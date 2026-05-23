'use client';

export default function AdminError({
  reset,
}: {
  error: Error & { digest?: string };
  reset: () => void;
}) {
  return (
    <div className="flex min-h-[60vh] flex-col items-center justify-center gap-4 p-8 text-center">
      <h2 className="text-xl font-black">Erreur de chargement</h2>
      <p className="text-sm text-gray-500">Une erreur est survenue dans le backoffice.</p>
      <button
        onClick={reset}
        className="rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
      >
        Réessayer
      </button>
    </div>
  );
}
