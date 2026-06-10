'use client';

import Link from 'next/link';
import { ArrowLeft } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import { useMerchantAuth } from '@/lib/auth/MerchantAuthContext';
import { Button } from '@/components/ui/Button';
import {
  getMerchantStoreProfile,
  updateMerchantStoreProfile,
  type MerchantStoreProfile,
} from '@/lib/services/merchant-store-profile.service';

// MS-002 (MVP-2): merchant edits the shopfront identity of their store
// (name, address, city, phone, logo and cover URLs). PATCH is partial on the
// backend; here we submit the full editable set as a single save.

type Draft = {
  name: string;
  address: string;
  city: string;
  phone: string;
  logoUrl: string;
  coverUrl: string;
};

function toDraft(profile: MerchantStoreProfile): Draft {
  return {
    name: profile.name,
    address: profile.address ?? '',
    city: profile.city ?? '',
    phone: profile.phone ?? '',
    logoUrl: profile.logoUrl ?? '',
    coverUrl: profile.coverUrl ?? '',
  };
}

function trimToNull(value: string): string | null {
  const trimmed = value.trim();
  return trimmed === '' ? null : trimmed;
}

export default function MerchantStoreProfilePage() {
  const { merchant, refresh } = useMerchantAuth();
  const storeId = merchant?.store.id ?? '';

  const [draft, setDraft] = useState<Draft | null>(null);
  const [saving, setSaving] = useState(false);
  const [saved, setSaved] = useState(false);
  const [loadError, setLoadError] = useState<string | null>(null);
  const [saveError, setSaveError] = useState<string | null>(null);

  const load = useCallback(async () => {
    if (!storeId) return;
    try {
      const data = await getMerchantStoreProfile(storeId);
      setDraft(toDraft(data));
      setLoadError(null);
    } catch {
      setLoadError('Impossible de charger le profil de la supérette.');
    }
  }, [storeId]);

  useEffect(() => {
    void load();
  }, [load]);

  const set = useCallback(<K extends keyof Draft>(key: K, value: Draft[K]) => {
    setDraft((prev) => (prev ? { ...prev, [key]: value } : prev));
    setSaved(false);
  }, []);

  const handleSubmit = async (event: React.FormEvent) => {
    event.preventDefault();
    if (!draft || !storeId) return;
    if (draft.name.trim() === '') {
      setSaveError('Le nom de la supérette est obligatoire.');
      return;
    }
    setSaving(true);
    setSaveError(null);
    try {
      const updated = await updateMerchantStoreProfile(storeId, {
        name: draft.name.trim(),
        address: trimToNull(draft.address),
        city: trimToNull(draft.city),
        phone: trimToNull(draft.phone),
        logoUrl: trimToNull(draft.logoUrl),
        coverUrl: trimToNull(draft.coverUrl),
      });
      setDraft(toDraft(updated));
      setSaved(true);
      // Reflect the (possibly renamed) store in the merchant shell sidebar/header
      // right away. Best-effort: a refresh failure must not undo the save success.
      try {
        await refresh();
      } catch {
        // ignored — the shell will pick up the new name on the next reload.
      }
    } catch {
      setSaveError("Impossible d'enregistrer. Vérifiez les valeurs saisies (URL en https/http).");
    } finally {
      setSaving(false);
    }
  };

  if (!draft) {
    return (
      <div className="flex min-h-40 items-center justify-center">
        {loadError ? (
          <p role="alert" className="text-sm text-red-600">{loadError}</p>
        ) : (
          <p className="text-sm text-muted">Chargement…</p>
        )}
      </div>
    );
  }

  return (
    <div className="mx-auto max-w-2xl space-y-6">
      <div className="space-y-2">
        <Link
          href="/merchant/parametres"
          className="inline-flex items-center gap-1 text-sm font-bold text-primary hover:underline"
        >
          <ArrowLeft className="h-4 w-4" aria-hidden="true" />
          Paramètres
        </Link>
        <h1 className="text-2xl font-black text-ink">Profil de la supérette</h1>
        <p className="text-sm text-muted">
          Ces informations sont visibles par vos clients dans le catalogue.
        </p>
      </div>

      <form onSubmit={handleSubmit} className="space-y-4 rounded-lg border border-line bg-card p-4">
        <Field
          id="store-name"
          label="Nom de la supérette"
          required
          value={draft.name}
          onChange={(v) => set('name', v)}
          maxLength={160}
        />
        <Field
          id="store-address"
          label="Adresse"
          value={draft.address}
          onChange={(v) => set('address', v)}
          maxLength={255}
        />
        <div className="grid gap-4 sm:grid-cols-2">
          <Field
            id="store-city"
            label="Ville"
            value={draft.city}
            onChange={(v) => set('city', v)}
            maxLength={100}
          />
          <Field
            id="store-phone"
            label="Téléphone"
            value={draft.phone}
            onChange={(v) => set('phone', v)}
            maxLength={20}
          />
        </div>
        <Field
          id="store-logo"
          label="Logo (URL https)"
          type="url"
          value={draft.logoUrl}
          onChange={(v) => set('logoUrl', v)}
          maxLength={2048}
          placeholder="https://…"
        />
        <Field
          id="store-cover"
          label="Image de couverture (URL https)"
          type="url"
          value={draft.coverUrl}
          onChange={(v) => set('coverUrl', v)}
          maxLength={2048}
          placeholder="https://…"
        />

        {saveError && <p role="alert" className="text-sm text-red-600">{saveError}</p>}
        {saved && <p className="text-sm text-green-700">Profil enregistré.</p>}

        <div className="flex items-center gap-3">
          <Button type="submit" disabled={saving}>
            {saving ? 'Enregistrement…' : 'Enregistrer'}
          </Button>
        </div>
      </form>
    </div>
  );
}

function Field({
  id,
  label,
  value,
  onChange,
  type = 'text',
  required = false,
  maxLength,
  placeholder,
}: {
  id: string;
  label: string;
  value: string;
  onChange: (value: string) => void;
  type?: string;
  required?: boolean;
  maxLength?: number;
  placeholder?: string;
}) {
  return (
    <div>
      <label className="mb-1 block text-sm font-semibold" htmlFor={id}>
        {label}
        {required && <span className="text-red-600"> *</span>}
      </label>
      <input
        id={id}
        type={type}
        value={value}
        required={required}
        maxLength={maxLength}
        placeholder={placeholder}
        onChange={(e) => onChange(e.target.value)}
        className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
      />
    </div>
  );
}
