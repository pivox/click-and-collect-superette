'use client';

import { useClientLocale } from '@/lib/i18n/ClientLocaleContext';

interface StoreSwitchWarningProps {
  currentStoreName: string;
  onConfirm: () => void;
  onCancel: () => void;
}

export function StoreSwitchWarning({ currentStoreName, onConfirm, onCancel }: StoreSwitchWarningProps) {
  const { t } = useClientLocale();

  const message = t('client.store.switchWarningMessage')
    .replace('{storeName}', currentStoreName);

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4">
      <div className="w-full max-w-sm rounded-xl bg-white p-6 shadow-xl">
        <h2 className="mb-3 text-base font-extrabold">{t('client.store.switchWarningTitle')}</h2>
        <p className="mb-5 text-sm text-muted">
          {message}
        </p>
        <div className="flex gap-3">
          <button
            type="button"
            onClick={onCancel}
            className="flex-1 rounded-lg border border-line py-2.5 text-sm font-extrabold text-muted hover:bg-soft"
          >
            {t('client.store.switchWarningCancel')}
          </button>
          <button
            type="button"
            onClick={onConfirm}
            className="flex-1 rounded-lg bg-primary py-2.5 text-sm font-extrabold text-white hover:bg-primary-dark"
          >
            {t('client.store.switchWarningConfirm')}
          </button>
        </div>
      </div>
    </div>
  );
}
