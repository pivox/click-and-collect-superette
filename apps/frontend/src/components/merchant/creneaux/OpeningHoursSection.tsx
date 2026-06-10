'use client';

import { useEffect, useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import {
  getMerchantOpeningHours,
  patchMerchantOpeningHours,
} from '@/lib/services/merchant-opening-hours.service';
import type { OpeningHours, OpeningHoursDay } from '@/lib/types/merchant-slots.types';

const DAYS: { key: keyof OpeningHours; label: string }[] = [
  { key: 'monday',    label: 'Lundi' },
  { key: 'tuesday',   label: 'Mardi' },
  { key: 'wednesday', label: 'Mercredi' },
  { key: 'thursday',  label: 'Jeudi' },
  { key: 'friday',    label: 'Vendredi' },
  { key: 'saturday',  label: 'Samedi' },
  { key: 'sunday',    label: 'Dimanche' },
];

const DEFAULT_DAY: OpeningHoursDay = { open: '08:00', close: '20:00', closed: false };

function emptyHours(): OpeningHours {
  return {
    monday:    { ...DEFAULT_DAY },
    tuesday:   { ...DEFAULT_DAY },
    wednesday: { ...DEFAULT_DAY },
    thursday:  { ...DEFAULT_DAY },
    friday:    { ...DEFAULT_DAY },
    saturday:  { open: '09:00', close: '18:00', closed: false },
    sunday:    { open: null, close: null, closed: true },
  };
}

export function OpeningHoursSection({ storeId }: { storeId: string }) {
  const [open, setOpen] = useState(false);
  const [hours, setHours] = useState<OpeningHours>(emptyHours);
  const [saving, setSaving] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [savedOk, setSavedOk] = useState(false);

  useEffect(() => {
    if (!open || !storeId) return;
    setLoadError(null);
    getMerchantOpeningHours(storeId)
      .then(setHours)
      .catch(() => setLoadError('Impossible de charger les horaires.'));
  }, [open, storeId]);

  const updateDay = (key: keyof OpeningHours, field: keyof OpeningHoursDay, value: string | boolean | null) => {
    setHours((prev) => ({
      ...prev,
      [key]: { ...prev[key], [field]: value },
    }));
    setSavedOk(false);
  };

  const handleSave = async () => {
    setSaving(true);
    setSaveError(null);
    setSavedOk(false);
    try {
      const updated = await patchMerchantOpeningHours(storeId, hours);
      setHours(updated);
      setSavedOk(true);
    } catch {
      setSaveError("Impossible d'enregistrer les horaires. Réessaie.");
    } finally {
      setSaving(false);
    }
  };

  return (
    <section>
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center justify-between rounded-lg border border-line bg-card px-4 py-3 text-left font-extrabold hover:bg-soft"
        aria-expanded={open}
      >
        <span>Horaires d&apos;ouverture</span>
        {open ? <ChevronUp className="h-4 w-4" /> : <ChevronDown className="h-4 w-4" />}
      </button>

      {open && (
        <div className="mt-3 space-y-3">
          {loadError && (
            <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">{loadError}</p>
          )}

          <div className="overflow-x-auto rounded-lg border border-line">
            <table className="w-full text-sm">
              <thead>
                <tr className="border-b border-line bg-soft text-xs font-extrabold text-muted uppercase tracking-wide">
                  <th className="px-3 py-2 text-left">Jour</th>
                  <th className="px-3 py-2 text-left">Ouverture</th>
                  <th className="px-3 py-2 text-left">Fermeture</th>
                  <th className="px-3 py-2 text-center">Fermé</th>
                </tr>
              </thead>
              <tbody>
                {DAYS.map(({ key, label }) => {
                  const day = hours[key];
                  return (
                    <tr key={key} className="border-b border-line last:border-0">
                      <td className="px-3 py-2 font-extrabold">{label}</td>
                      <td className="px-3 py-2">
                        <input
                          type="time"
                          value={day.open ?? ''}
                          disabled={day.closed}
                          onChange={(e) => updateDay(key, 'open', e.target.value || null)}
                          aria-label={`${label} - heure d'ouverture`}
                          className="rounded border border-line px-2 py-1 text-sm disabled:opacity-40 focus:border-primary focus:outline-none"
                        />
                      </td>
                      <td className="px-3 py-2">
                        <input
                          type="time"
                          value={day.close ?? ''}
                          disabled={day.closed}
                          onChange={(e) => updateDay(key, 'close', e.target.value || null)}
                          aria-label={`${label} - heure de fermeture`}
                          className="rounded border border-line px-2 py-1 text-sm disabled:opacity-40 focus:border-primary focus:outline-none"
                        />
                      </td>
                      <td className="px-3 py-2 text-center">
                        <input
                          type="checkbox"
                          checked={day.closed}
                          onChange={(e) => updateDay(key, 'closed', e.target.checked)}
                          aria-label={`${label} - fermé`}
                          className="h-4 w-4 accent-primary"
                        />
                      </td>
                    </tr>
                  );
                })}
              </tbody>
            </table>
          </div>

          {saveError && (
            <p className="rounded-md bg-red-50 px-3 py-2 text-sm text-red-600">{saveError}</p>
          )}
          {savedOk && (
            <p className="rounded-md bg-green-50 px-3 py-2 text-sm font-bold text-green-700">
              Horaires enregistrés.
            </p>
          )}

          <button
            type="button"
            onClick={() => void handleSave()}
            disabled={saving}
            className="rounded-md bg-primary px-4 py-2 text-sm font-extrabold text-white hover:brightness-95 disabled:opacity-50"
          >
            {saving ? 'Enregistrement…' : 'Enregistrer'}
          </button>
        </div>
      )}
    </section>
  );
}
