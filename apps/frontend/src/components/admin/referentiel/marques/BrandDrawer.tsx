'use client';
import { useState, useEffect } from 'react';
import axios from 'axios';
import { AdminDrawer } from '@/components/admin/ui/AdminDrawer';
import { createBrand, updateBrand } from '@/lib/services/admin/brands.service';
import type { Brand } from '@/lib/types/admin/referentiel.types';

function TagInput({ tags, onChange }: { tags: string[]; onChange: (t: string[]) => void }) {
  const [input, setInput] = useState('');
  const add = () => {
    const v = input.trim();
    if (v && !tags.includes(v)) onChange([...tags, v]);
    setInput('');
  };
  return (
    <div className="flex min-h-[42px] w-full flex-wrap gap-1 rounded-md border border-line px-2 py-1 focus-within:border-primary focus-within:ring-2 focus-within:ring-primary/20">
      {tags.map((t) => (
        <span key={t} className="flex items-center gap-1 rounded bg-soft px-2 py-0.5 text-xs">
          {t}
          <button
            type="button"
            onClick={() => onChange(tags.filter((x) => x !== t))}
            aria-label={`Supprimer ${t}`}
            className="text-muted hover:text-danger"
          >
            ✕
          </button>
        </span>
      ))}
      <input
        type="text"
        value={input}
        onChange={(e) => setInput(e.target.value)}
        onKeyDown={(e) => {
          if (e.key === 'Enter' || e.key === ',') { e.preventDefault(); add(); }
        }}
        onBlur={add}
        placeholder="Ajouter alias…"
        className="min-w-[120px] flex-1 bg-transparent text-sm outline-none"
      />
    </div>
  );
}

interface BrandDrawerProps {
  open: boolean;
  onClose: () => void;
  brand: Brand | null;
  onSaved: () => void;
}

export function BrandDrawer({ open, onClose, brand, onSaved }: BrandDrawerProps) {
  const [canonicalName, setCanonicalName] = useState('');
  const [slug, setSlug] = useState('');
  const [aliases, setAliases] = useState<string[]>([]);
  const [country, setCountry] = useState('');
  const [isActive, setIsActive] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);

  useEffect(() => {
    if (brand) {
      setCanonicalName(brand.canonical_name);
      setSlug(brand.slug);
      setAliases(brand.aliases);
      setCountry(brand.country ?? '');
      setIsActive(brand.is_active);
    } else {
      setCanonicalName('');
      setSlug('');
      setAliases([]);
      setCountry('');
      setIsActive(true);
    }
    setError(null);
  }, [brand, open]);

  const handleSubmit = async () => {
    if (!canonicalName.trim()) { setError('Le nom canonique est obligatoire.'); return; }
    setIsSubmitting(true);
    setError(null);
    try {
      if (brand) {
        await updateBrand(brand.id, {
          canonicalName: canonicalName.trim(),
          slug: slug.trim() || undefined,
          aliases,
          country: country.trim() || undefined,
          isActive,
        });
      } else {
        await createBrand({
          canonicalName: canonicalName.trim(),
          slug: slug.trim() || undefined,
          aliases: aliases.length ? aliases : undefined,
          country: country.trim() || undefined,
        });
      }
      onSaved();
    } catch (e) {
      setError(axios.isAxiosError(e) && e.response?.status === 409
        ? 'Un nom ou slug identique existe déjà.'
        : 'Une erreur est survenue. Réessayez.');
    } finally {
      setIsSubmitting(false);
    }
  };

  return (
    <AdminDrawer
      open={open}
      onClose={onClose}
      title={brand ? 'Modifier la marque' : 'Nouvelle marque'}
      onSubmit={handleSubmit}
      isSubmitting={isSubmitting}
    >
      <div className="space-y-4">
        {error && (
          <div className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
            {error}
          </div>
        )}
        <div>
          <label className="mb-1 block text-sm font-semibold">Nom canonique *</label>
          <input
            type="text"
            value={canonicalName}
            onChange={(e) => setCanonicalName(e.target.value)}
            maxLength={160}
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">
            Slug <span className="font-normal text-muted">(auto-généré si vide)</span>
          </label>
          <input
            type="text"
            value={slug}
            onChange={(e) => setSlug(e.target.value)}
            maxLength={180}
            placeholder="ben-yedder"
            className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Pays (ISO 2 lettres)</label>
          <input
            type="text"
            value={country}
            onChange={(e) => setCountry(e.target.value.toUpperCase())}
            maxLength={2}
            placeholder="TN"
            className="w-20 rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
          />
        </div>
        <div>
          <label className="mb-1 block text-sm font-semibold">Aliases</label>
          <p className="mb-1 text-xs text-muted">Appuyez sur Entrée ou virgule pour ajouter</p>
          <TagInput tags={aliases} onChange={setAliases} />
        </div>
        {brand && (
          <div className="flex items-center gap-3">
            <label className="text-sm font-semibold">Actif</label>
            <button
              type="button"
              onClick={() => setIsActive((v) => !v)}
              className={`relative h-6 w-11 rounded-full transition-colors ${isActive ? 'bg-primary' : 'bg-line'}`}
            >
              <span
                className={`absolute top-0.5 h-5 w-5 rounded-full bg-white shadow transition-transform ${isActive ? 'translate-x-5' : 'translate-x-0.5'}`}
              />
            </button>
            <span className="text-sm text-muted">{isActive ? 'Oui' : 'Non'}</span>
          </div>
        )}
      </div>
    </AdminDrawer>
  );
}
