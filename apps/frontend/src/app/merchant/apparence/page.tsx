'use client';

import { useCallback, useEffect, useState } from 'react';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import {
  getMerchantTheme,
  putMerchantTheme,
  type MerchantTheme,
} from '@/lib/services/merchant-theme.service';
import { Button } from '@/components/ui/Button';

const FONT_OPTIONS = [
  { value: 'inter', label: 'Inter' },
  { value: 'cairo', label: 'Cairo' },
  { value: 'roboto', label: 'Roboto' },
  { value: 'noto_sans_arabic', label: 'Noto Sans Arabic' },
  { value: 'system', label: 'Système' },
];

function relativeLuminance(hex: string): number {
  const chan = (s: string) => {
    const c = parseInt(s, 16) / 255;
    return c <= 0.04045 ? c / 12.92 : Math.pow((c + 0.055) / 1.055, 2.4);
  };
  return 0.2126 * chan(hex.slice(1, 3)) + 0.7152 * chan(hex.slice(3, 5)) + 0.0722 * chan(hex.slice(5, 7));
}

function wcagContrastRatio(hex1: string, hex2: string): number {
  const l1 = relativeLuminance(hex1);
  const l2 = relativeLuminance(hex2);
  return (Math.max(l1, l2) + 0.05) / (Math.min(l1, l2) + 0.05);
}

type Draft = Omit<MerchantTheme, 'warnings'>;

export default function ApparencePage() {
  const { merchant } = useMerchantAuth();
  const storeId = merchant?.store.id ?? '';

  const [theme, setTheme] = useState<MerchantTheme | null>(null);
  const [draft, setDraft] = useState<Draft | null>(null);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);
  const [showAdvanced, setShowAdvanced] = useState(false);

  const load = useCallback(async () => {
    if (!storeId) return;
    try {
      const data = await getMerchantTheme(storeId);
      setTheme(data);
      setDraft({
        primaryColor: data.primaryColor,
        secondaryColor: data.secondaryColor,
        accentColor: data.accentColor,
        textColor: data.textColor,
        backgroundColor: data.backgroundColor,
        fontFamily: data.fontFamily,
        baseFontSize: data.baseFontSize,
      });
    } catch {
      setLoadError('Impossible de charger le thème.');
    }
  }, [storeId]);

  useEffect(() => { void load(); }, [load]);

  const set = useCallback(<K extends keyof Draft>(key: K, value: Draft[K]) => {
    setDraft((prev) => (prev ? { ...prev, [key]: value } : prev));
    setSaved(false);
  }, []);

  const handleSave = async () => {
    if (!draft || !storeId) return;
    setSaving(true);
    setSaveError(null);
    try {
      const updated = await putMerchantTheme(storeId, draft);
      setTheme(updated);
      setSaved(true);
    } catch {
      setSaveError("Impossible d'enregistrer le thème. Vérifie les valeurs saisies.");
    } finally {
      setSaving(false);
    }
  };

  if (!draft) {
    return (
      <div className="flex min-h-40 items-center justify-center">
        {loadError ? (
          <p className="text-sm text-red-600">{loadError}</p>
        ) : (
          <p className="text-sm text-muted">Chargement…</p>
        )}
      </div>
    );
  }

  const contrastRatio = wcagContrastRatio(draft.textColor, draft.backgroundColor);
  const contrastWarning = contrastRatio < 4.5;

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div>
        <p className="text-sm font-bold text-primary">Supérette</p>
        <h1 className="text-2xl font-black text-ink">Apparence</h1>
        <p className="mt-1 text-sm text-muted">
          Personnalisez les couleurs et la police de votre supérette. Les clients voient les
          changements dans les 5 minutes.
        </p>
      </div>

      {/* Aperçu live */}
      <div className="rounded-lg border border-line bg-card p-4">
        <p className="mb-3 text-xs font-extrabold uppercase tracking-widest text-muted">Aperçu</p>
        <div className="flex flex-wrap items-center gap-3">
          <button
            type="button"
            className="rounded-md px-4 py-2 text-sm font-extrabold text-white"
            style={{ backgroundColor: draft.primaryColor }}
          >
            Ajouter à la Kadhia
          </button>
          <span
            className="rounded-full px-3 py-1 text-xs font-extrabold"
            style={{ backgroundColor: draft.secondaryColor, color: '#332500' }}
          >
            Promo
          </span>
          <span
            className="rounded-md px-3 py-1 text-xs font-extrabold text-white"
            style={{ backgroundColor: draft.accentColor }}
          >
            Nouveau
          </span>
        </div>
      </div>

      {/* Champs principaux */}
      <div className="space-y-4 rounded-lg border border-line bg-card p-4">
        <div className="grid gap-4 sm:grid-cols-2">
          <ColorField
            id="primary_color"
            label="Couleur principale"
            value={draft.primaryColor}
            onChange={(v) => set('primaryColor', v)}
          />
          <ColorField
            id="secondary_color"
            label="Couleur secondaire"
            value={draft.secondaryColor}
            onChange={(v) => set('secondaryColor', v)}
          />
        </div>

        <div>
          <label className="mb-1 block text-sm font-semibold" htmlFor="font_family">
            Police
          </label>
          <select
            id="font_family"
            value={draft.fontFamily}
            onChange={(e) => set('fontFamily', e.target.value)}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          >
            {FONT_OPTIONS.map((f) => (
              <option key={f.value} value={f.value}>{f.label}</option>
            ))}
          </select>
        </div>
      </div>

      {/* Options avancées */}
      <div>
        <button
          type="button"
          onClick={() => setShowAdvanced((v) => !v)}
          className="text-sm font-extrabold text-primary hover:underline"
        >
          {showAdvanced ? '▴ Masquer les options avancées' : '▾ Options avancées'}
        </button>
      </div>

      {showAdvanced && (
        <div className="space-y-4 rounded-lg border border-line bg-card p-4">
          <div className="grid gap-4 sm:grid-cols-2">
            <ColorField
              id="accent_color"
              label="Couleur d'accentuation"
              value={draft.accentColor}
              onChange={(v) => set('accentColor', v)}
            />
            <ColorField
              id="background_color"
              label="Couleur de fond"
              value={draft.backgroundColor}
              onChange={(v) => set('backgroundColor', v)}
            />
            <div>
              <ColorField
                id="text_color"
                label="Couleur du texte"
                value={draft.textColor}
                onChange={(v) => set('textColor', v)}
              />
              {contrastWarning && (
                <p className="mt-1 text-xs text-amber-600">
                  ⚠ Contraste insuffisant ({contrastRatio.toFixed(1)}:1 — minimum WCAG AA : 4.5:1)
                </p>
              )}
            </div>
            <div>
              <label className="mb-1 block text-sm font-semibold" htmlFor="base_font_size">
                Taille de police ({draft.baseFontSize}px)
              </label>
              <input
                id="base_font_size"
                type="range"
                min={14}
                max={20}
                value={draft.baseFontSize}
                onChange={(e) => set('baseFontSize', parseInt(e.target.value, 10))}
                className="w-full accent-primary"
              />
              <div className="mt-1 flex justify-between text-xs text-muted">
                <span>14px</span><span>20px</span>
              </div>
            </div>
          </div>
        </div>
      )}

      {/* Warnings API */}
      {theme?.warnings && theme.warnings.length > 0 && (
        <div className="rounded-md bg-amber-50 px-4 py-3 text-sm text-amber-700">
          {theme.warnings.map((w) => (
            <p key={w.code}>⚠ {w.message}</p>
          ))}
        </div>
      )}

      {saveError && (
        <div className="rounded-md bg-red-50 px-4 py-3 text-sm text-red-600">{saveError}</div>
      )}

      <div className="flex items-center gap-3">
        <Button type="button" disabled={saving} onClick={() => void handleSave()}>
          {saving ? 'Enregistrement…' : 'Enregistrer'}
        </Button>
        {saved && (
          <span className="text-sm font-extrabold text-green-600">✓ Thème enregistré</span>
        )}
      </div>
    </div>
  );
}

function ColorField({
  id,
  label,
  value,
  onChange,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (v: string) => void;
}) {
  return (
    <div>
      <label className="mb-1 block text-sm font-semibold" htmlFor={id}>
        {label}
      </label>
      <div className="flex items-center gap-2">
        <input
          id={id}
          type="color"
          value={value}
          onChange={(e) => onChange(e.target.value)}
          className="h-9 w-14 cursor-pointer rounded border border-line"
        />
        <span className="font-mono text-sm text-muted">{value}</span>
      </div>
    </div>
  );
}
