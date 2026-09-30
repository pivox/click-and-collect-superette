'use client';

import { useCallback, useEffect, useState } from 'react';
import { Save } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import {
  getAdminPlatformSettings,
  updateAdminPlatformSettings,
} from '@/lib/services/admin/platform-settings.service';
import type { AdminPlatformSettings } from '@/lib/types/admin/platform-settings.types';

const EMPTY_SETTINGS: AdminPlatformSettings = {
  id: 'platform-settings',
  frontendOrigin: '',
  updatedAt: '',
};

const inputClass =
  'w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20 disabled:bg-soft disabled:text-muted';

export default function AdminPlatformSettingsPage() {
  const [settings, setSettings] = useState<AdminPlatformSettings>(EMPTY_SETTINGS);
  const [frontendOrigin, setFrontendOrigin] = useState('');
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [hasLoadedSettings, setHasLoadedSettings] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const load = useCallback(() => {
    setIsLoading(true);
    setError(null);
    setSaved(false);
    setHasLoadedSettings(false);
    void getAdminPlatformSettings()
      .then((data) => {
        setSettings(data);
        setFrontendOrigin(data.frontendOrigin);
        setHasLoadedSettings(true);
      })
      .catch((err: unknown) => {
        console.error('[admin-platform-settings] load failed', err);
        setError('Impossible de charger les paramètres plateforme.');
        setHasLoadedSettings(false);
      })
      .finally(() => setIsLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const save = async () => {
    if (!hasLoadedSettings) return;

    setIsSaving(true);
    setError(null);
    setSaved(false);
    try {
      const updated = await updateAdminPlatformSettings({
        frontendOrigin: frontendOrigin.trim(),
      });
      setSettings(updated);
      setFrontendOrigin(updated.frontendOrigin);
      setSaved(true);
    } catch (err) {
      console.error('[admin-platform-settings] save failed', err);
      setError('Impossible d’enregistrer les paramètres plateforme. Vérifiez que l’URL est une origine absolue autorisée.');
    } finally {
      setIsSaving(false);
    }
  };

  const canEditSettings = hasLoadedSettings && !isLoading && !isSaving;

  return (
    <div className="max-w-3xl">
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-h1 font-black">Paramètres plateforme</h1>
          <p className="mt-1 text-sm text-muted">
            Origine publique utilisée par le backend pour générer les liens frontend.
          </p>
        </div>
        <Button variant="primary" size="md" disabled={!hasLoadedSettings || isSaving || isLoading} onClick={() => void save()}>
          <Save className="h-4 w-4" aria-hidden="true" />
          Enregistrer
        </Button>
      </div>

      {error && (
        <div role="alert" className="mb-4 rounded-md bg-status-cancel-bg px-4 py-2 text-sm text-status-cancel">
          {error}
        </div>
      )}
      {isLoading && (
        <div className="mb-4 rounded-md border border-line bg-soft px-4 py-2 text-sm font-semibold text-muted">
          Chargement des paramètres plateforme...
        </div>
      )}
      {saved && (
        <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-800">
          Paramètres plateforme enregistrés.
        </div>
      )}

      <section className="rounded-md border border-line bg-card p-4">
        <label htmlFor="frontend-origin" className="mb-1 block text-sm font-black text-ink">
          Origine frontend
        </label>
        <input
          id="frontend-origin"
          type="url"
          value={frontendOrigin}
          disabled={!canEditSettings}
          onChange={(event) => {
            setSaved(false);
            setFrontendOrigin(event.target.value);
          }}
          placeholder="https://app.kadhia.tn"
          className={inputClass}
        />
        <p className="mt-2 text-xs text-muted">
          Utilisée pour les QR magasin, liens de partage Kadhia et emails frontend.
        </p>
        {settings.updatedAt && (
          <p className="mt-3 text-xs text-muted">
            Dernière mise à jour : {settings.updatedAt}
          </p>
        )}
      </section>
    </div>
  );
}
