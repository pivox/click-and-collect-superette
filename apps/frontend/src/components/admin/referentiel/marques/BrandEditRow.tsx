'use client';
import { useState } from 'react';
import axios from 'axios';
import {
  updateBrand,
  rejectBrand,
} from '@/lib/services/admin/brands.service';
import type { Brand } from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

interface BrandEditRowProps {
  brand: Brand;
  colSpan: number;
  onSaved: () => void;
  onCancel: () => void;
}

export function BrandEditRow({ brand, colSpan, onSaved, onCancel }: BrandEditRowProps) {
  const [canonicalName, setCanonicalName] = useState(brand.canonical_name);
  const [aliases, setAliases] = useState(brand.aliases.join(', '));
  const [country, setCountry] = useState(brand.country ?? '');
  const [isActive, setIsActive] = useState(brand.is_active);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showRejectPrompt, setShowRejectPrompt] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [isRejecting, setIsRejecting] = useState(false);

  const handleSave = async () => {
    setError(null);
    if (!canonicalName.trim()) {
      setError('Le nom canonique est obligatoire.');
      return;
    }
    setIsSubmitting(true);
    try {
      const aliasesArray = aliases
        .split(',')
        .map((a) => a.trim())
        .filter((a) => a.length > 0);
      await updateBrand(brand.id, {
        canonicalName: canonicalName.trim(),
        aliases: aliasesArray,
        country: country.trim() || undefined,
        isActive,
      });
      onSaved();
    } catch (e) {
      setError(
        axios.isAxiosError(e) && e.response?.status === 409
          ? 'Cette marque a déjà été modifiée ailleurs.'
          : 'Une erreur est survenue. Réessayez.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleRejectConfirm = async () => {
    if (!rejectReason.trim()) {
      setError('La raison du rejet est obligatoire.');
      return;
    }
    setIsRejecting(true);
    setError(null);
    try {
      await rejectBrand(brand.id, rejectReason.trim());
      onSaved();
    } catch (e) {
      setError(
        axios.isAxiosError(e) && e.response?.status === 409
          ? 'Cette marque a déjà été traitée ailleurs.'
          : 'Une erreur est survenue. Réessayez.',
      );
    } finally {
      setIsRejecting(false);
    }
  };

  return (
    <tr>
      <td colSpan={colSpan} className="border-l-4 border-primary bg-primary/5 px-4 py-4">
        <p className="mb-3 text-xs font-semibold text-primary">
          ↳ Modifier — {brand.canonical_name}
        </p>

        <div className="grid grid-cols-2 gap-3 lg:grid-cols-3">
          <div>
            <label className="mb-1 block text-xs font-semibold">Nom canonique *</label>
            <input
              type="text"
              value={canonicalName}
              onChange={(e) => setCanonicalName(e.target.value)}
              disabled={isSubmitting}
              placeholder="Nom de la marque…"
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                isSubmitting && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-semibold">Alias (séparés par ,)</label>
            <input
              type="text"
              value={aliases}
              onChange={(e) => setAliases(e.target.value)}
              disabled={isSubmitting}
              placeholder="alias1, alias2, alias3"
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                isSubmitting && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-semibold">Pays</label>
            <input
              type="text"
              value={country}
              onChange={(e) => setCountry(e.target.value)}
              disabled={isSubmitting}
              placeholder="ex. TN"
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                isSubmitting && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            />
          </div>

          <div>
            <label className="mb-1 block text-xs font-semibold">Statut</label>
            <select
              value={isActive ? 'actif' : 'inactif'}
              onChange={(e) => setIsActive(e.target.value === 'actif')}
              disabled={isSubmitting}
              className={cn(
                'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                isSubmitting && 'bg-soft opacity-50 cursor-not-allowed',
              )}
            >
              <option value="actif">Actif</option>
              <option value="inactif">Inactif</option>
            </select>
          </div>
        </div>

        {showRejectPrompt && (
          <div className="mt-3 flex items-start gap-3 border-t border-line pt-3">
            <textarea
              value={rejectReason}
              onChange={(e) => setRejectReason(e.target.value)}
              disabled={isRejecting}
              rows={2}
              placeholder="Raison du rejet *"
              className={cn(
                'max-w-sm flex-1 rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                'focus:border-primary focus:ring-2 focus:ring-primary/20',
                isRejecting && 'opacity-50 cursor-not-allowed',
              )}
            />
            <button
              onClick={() => void handleRejectConfirm()}
              disabled={isRejecting || !rejectReason.trim()}
              className={cn(
                'rounded-md px-3 py-2 text-sm font-semibold transition',
                isRejecting || !rejectReason.trim()
                  ? 'bg-red-100 text-red-500 cursor-not-allowed'
                  : 'bg-red-600 text-white hover:bg-red-700',
              )}
            >
              {isRejecting ? '…' : 'Confirmer le rejet'}
            </button>
            <button
              onClick={() => {
                setShowRejectPrompt(false);
                setRejectReason('');
                setError(null);
              }}
              disabled={isRejecting}
              className="rounded-md border border-line px-3 py-2 text-sm transition hover:bg-soft disabled:opacity-50"
            >
              Annuler
            </button>
          </div>
        )}

        {error && <p className="mt-2 text-xs text-red-600">{error}</p>}

        <div className="mt-3 flex items-center gap-2 border-t border-line pt-3">
          <button
            onClick={() => void handleSave()}
            disabled={isSubmitting}
            className={cn(
              'rounded-md px-3 py-2 text-sm font-semibold transition',
              isSubmitting
                ? 'bg-primary/50 text-white cursor-not-allowed'
                : 'bg-primary text-white hover:bg-primary-600',
            )}
          >
            {isSubmitting ? '…' : 'Enregistrer'}
          </button>
          <button
            onClick={onCancel}
            disabled={isSubmitting || isRejecting}
            className="rounded-md border border-line px-3 py-2 text-sm transition hover:bg-soft disabled:opacity-50"
          >
            Annuler l&apos;édition
          </button>
          <button
            onClick={() => setShowRejectPrompt(true)}
            disabled={isSubmitting || isRejecting || showRejectPrompt}
            className={cn(
              'ml-auto rounded-md px-3 py-2 text-sm font-semibold transition',
              showRejectPrompt || isSubmitting || isRejecting
                ? 'bg-red-50 text-red-500 cursor-not-allowed'
                : 'bg-red-50 text-red-700 hover:bg-red-100',
            )}
          >
            Rejeter
          </button>
        </div>
      </td>
    </tr>
  );
}
