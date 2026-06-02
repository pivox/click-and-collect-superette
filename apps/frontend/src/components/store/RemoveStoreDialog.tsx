interface RemoveStoreDialogProps {
  storeName: string;
  onConfirm: () => void;
  onCancel: () => void;
}

export function RemoveStoreDialog({ storeName, onConfirm, onCancel }: RemoveStoreDialogProps) {
  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
        <h2 className="mb-3 text-base font-extrabold">Retirer cette supérette ?</h2>
        <p className="mb-5 text-sm text-muted">
          <strong>{storeName}</strong> sera retirée de ta liste. Tu pourras la retrouver en
          scannant son QR code ou en la recherchant.
        </p>
        <div className="flex gap-3">
          <button
            type="button"
            onClick={onCancel}
            className="flex-1 rounded-lg border border-line py-2.5 text-sm font-extrabold text-muted hover:bg-soft"
          >
            Annuler
          </button>
          <button
            type="button"
            onClick={onConfirm}
            className="flex-1 rounded-lg bg-danger py-2.5 text-sm font-extrabold text-white hover:brightness-95"
          >
            Retirer
          </button>
        </div>
      </div>
    </div>
  );
}
