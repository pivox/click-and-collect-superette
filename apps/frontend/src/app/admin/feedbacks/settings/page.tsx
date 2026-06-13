'use client';

import Link from 'next/link';
import { useCallback, useEffect, useState } from 'react';
import { ArrowLeft, Save } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import {
  getAdminFeedbackSettings,
  updateAdminFeedbackSettings,
} from '@/lib/services/admin/feedbacks.service';
import type { AdminFeedbackSettings } from '@/lib/types/admin/feedbacks.types';

const EMPTY_SETTINGS: AdminFeedbackSettings = {
  id: 'feedback-settings',
  globalEnabled: false,
  clientEnabled: false,
  merchantEnabled: false,
  adminEnabled: false,
  clientAreaEnabled: false,
  merchantAreaEnabled: false,
  adminAreaEnabled: false,
  requireAuthenticatedUser: true,
  updatedAt: '',
};

export default function AdminFeedbackSettingsPage() {
  const [settings, setSettings] = useState<AdminFeedbackSettings>(EMPTY_SETTINGS);
  const [isLoading, setIsLoading] = useState(true);
  const [isSaving, setIsSaving] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [saved, setSaved] = useState(false);

  const load = useCallback(() => {
    setIsLoading(true);
    setError(null);
    void getAdminFeedbackSettings()
      .then((data) => setSettings({ ...data, requireAuthenticatedUser: true }))
      .catch((err: unknown) => {
        console.error('[admin-feedback-settings] load failed', err);
        setError('Impossible de charger les paramètres Feedback.');
      })
      .finally(() => setIsLoading(false));
  }, []);

  useEffect(() => {
    load();
  }, [load]);

  const toggle = (key: keyof AdminFeedbackSettings) => {
    setSaved(false);
    setSettings((current) => ({
      ...current,
      [key]: !current[key],
    }));
  };

  const save = async () => {
    setIsSaving(true);
    setError(null);
    setSaved(false);
    try {
      const updated = await updateAdminFeedbackSettings({
        globalEnabled: settings.globalEnabled,
        clientEnabled: settings.clientEnabled,
        merchantEnabled: settings.merchantEnabled,
        adminEnabled: settings.adminEnabled,
        clientAreaEnabled: settings.clientAreaEnabled,
        merchantAreaEnabled: settings.merchantAreaEnabled,
        adminAreaEnabled: settings.adminAreaEnabled,
        requireAuthenticatedUser: true,
      });
      setSettings({ ...updated, requireAuthenticatedUser: true });
      setSaved(true);
    } catch (err) {
      console.error('[admin-feedback-settings] save failed', err);
      setError('Impossible d’enregistrer les paramètres Feedback.');
    } finally {
      setIsSaving(false);
    }
  };

  return (
    <div className="max-w-4xl">
      <div className="mb-5 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <Link href="/admin/feedbacks" className="mb-2 inline-flex items-center gap-2 text-sm font-bold text-primary hover:underline">
            <ArrowLeft className="h-4 w-4" aria-hidden="true" />
            Feedbacks
          </Link>
          <h1 className="text-h1 font-black">Paramètres Feedback</h1>
          <p className="mt-1 text-sm text-muted">Activation du bouton Retour par rôle et zone applicative simple.</p>
        </div>
        <Button variant="primary" size="md" disabled={isSaving || isLoading} onClick={() => void save()}>
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
          Chargement des paramètres Feedback...
        </div>
      )}
      {saved && (
        <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-800">
          Paramètres Feedback enregistrés.
        </div>
      )}

      <section className="rounded-md border border-line bg-card p-4">
        <SettingToggle
          label="Activation globale"
          description="Affiche le module Retour uniquement si le rôle et la zone sont aussi activés."
          checked={settings.globalEnabled}
          onChange={() => toggle('globalEnabled')}
        />
        <div className="mt-4 border-t border-line pt-4">
          <h2 className="text-sm font-black text-ink">Rôles</h2>
          <div className="mt-3 grid gap-3 md:grid-cols-3">
            <SettingToggle label="Client" checked={settings.clientEnabled} onChange={() => toggle('clientEnabled')} />
            <SettingToggle label="Marchand" checked={settings.merchantEnabled} onChange={() => toggle('merchantEnabled')} />
            <SettingToggle label="Admin" checked={settings.adminEnabled} onChange={() => toggle('adminEnabled')} />
          </div>
        </div>
        <div className="mt-4 border-t border-line pt-4">
          <h2 className="text-sm font-black text-ink">Zones</h2>
          <div className="mt-3 grid gap-3 md:grid-cols-3">
            <SettingToggle label="Zone client" checked={settings.clientAreaEnabled} onChange={() => toggle('clientAreaEnabled')} />
            <SettingToggle label="Zone marchand" checked={settings.merchantAreaEnabled} onChange={() => toggle('merchantAreaEnabled')} />
            <SettingToggle label="Zone admin" checked={settings.adminAreaEnabled} onChange={() => toggle('adminAreaEnabled')} />
          </div>
        </div>
        <div className="mt-4 border-t border-line pt-4">
          <SettingToggle
            label="Utilisateurs connectés uniquement"
            description="Verrouillé à oui pour le MVP."
            checked
            disabled
            onChange={() => undefined}
          />
        </div>
      </section>
    </div>
  );
}

function SettingToggle({
  label,
  description,
  checked,
  disabled = false,
  onChange,
}: {
  label: string;
  description?: string;
  checked: boolean;
  disabled?: boolean;
  onChange: () => void;
}) {
  return (
    <label className="flex min-h-16 items-start justify-between gap-3 rounded-md border border-line bg-soft px-3 py-3">
      <span>
        <span className="block text-sm font-black text-ink">{label}</span>
        {description && <span className="mt-1 block text-xs text-muted">{description}</span>}
      </span>
      <input
        aria-label={label}
        type="checkbox"
        checked={checked}
        disabled={disabled}
        onChange={onChange}
        className="mt-1 h-5 w-5 rounded border-line text-primary"
      />
    </label>
  );
}
