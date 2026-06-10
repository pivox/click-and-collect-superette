'use client';
import { useState, useEffect, useRef } from 'react';
import axios from 'axios';
import {
  approveProposal,
  rejectProposal,
  listProductReferences,
} from '@/lib/services/admin/proposals.service';
import { createBrand } from '@/lib/services/admin/brands.service';
import { createCategory } from '@/lib/services/admin/categories.service';
import { InlineCreateCombobox } from '@/components/admin/ui/InlineCreateCombobox';
import type {
  Proposal,
  ProductReference,
  Brand,
  Category,
} from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

type ApproveMode = 'link' | 'create';

interface ProposalEditRowProps {
  proposal: Proposal;
  brands: Brand[];
  categories: Category[];
  colSpan: number;
  onSaved: () => void;
  onCancel: () => void;
}

export function ProposalEditRow({
  proposal,
  brands: initialBrands,
  categories: initialCategories,
  colSpan,
  onSaved,
  onCancel,
}: ProposalEditRowProps) {
  const [approveMode, setApproveMode] = useState<ApproveMode>('link');
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<ProductReference[]>([]);
  const [selectedRef, setSelectedRef] = useState<ProductReference | null>(null);
  const [isSearching, setIsSearching] = useState(false);
  const [newNameFr, setNewNameFr] = useState('');
  const [newBrandId, setNewBrandId] = useState('');
  const [newCategoryId, setNewCategoryId] = useState('');
  const [brands, setBrands] = useState<Brand[]>(initialBrands);
  const [categories, setCategories] = useState<Category[]>(initialCategories);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [showRejectPrompt, setShowRejectPrompt] = useState(false);
  const [rejectReason, setRejectReason] = useState('');
  const [isRejecting, setIsRejecting] = useState(false);
  const searchContainerRef = useRef<HTMLDivElement>(null);

  useEffect(() => {
    setBrands(initialBrands);
  }, [initialBrands]);

  useEffect(() => {
    setCategories(initialCategories);
  }, [initialCategories]);

  useEffect(() => {
    if (!searchQuery || searchQuery.length < 2) {
      setSearchResults([]);
      return;
    }
    const t = setTimeout(async () => {
      setIsSearching(true);
      try {
        const data = await listProductReferences({ q: searchQuery, limit: 10 });
        setSearchResults(data.items);
      } catch {
        setError('Impossible de rechercher les produits. Réessayez.');
        setSearchResults([]);
      } finally {
        setIsSearching(false);
      }
    }, 300);
    return () => clearTimeout(t);
  }, [searchQuery]);

  useEffect(() => {
    const handleMouseDown = (e: MouseEvent) => {
      if (searchContainerRef.current && !searchContainerRef.current.contains(e.target as Node)) {
        setSearchResults([]);
      }
    };
    document.addEventListener('mousedown', handleMouseDown);
    return () => document.removeEventListener('mousedown', handleMouseDown);
  }, []);

  const handleApproveLink = async () => {
    if (!selectedRef) {
      setError('Sélectionnez un produit existant.');
      return;
    }
    setIsSubmitting(true);
    setError(null);
    try {
      await approveProposal(proposal.id, { productReferenceId: selectedRef.id });
      onSaved();
    } catch (e) {
      setError(
        axios.isAxiosError(e) && e.response?.status === 409
          ? 'Cette proposition a déjà été traitée.'
          : 'Une erreur est survenue.',
      );
    } finally {
      setIsSubmitting(false);
    }
  };

  const handleApproveCreate = async () => {
    if (!newNameFr.trim() || !newBrandId || !newCategoryId) {
      setError('Nom FR, Marque et Catégorie sont obligatoires.');
      return;
    }
    setIsSubmitting(true);
    setError(null);
    try {
      await approveProposal(proposal.id, {
        canonicalData: {
          nameFr: newNameFr.trim(),
          brandId: newBrandId,
          categoryId: newCategoryId,
        },
      });
      onSaved();
    } catch (e) {
      setError(
        axios.isAxiosError(e) && e.response?.status === 409
          ? 'Cette proposition a déjà été traitée.'
          : 'Une erreur est survenue.',
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
      await rejectProposal(proposal.id, rejectReason.trim());
      onSaved();
    } catch (e) {
      setError(
        axios.isAxiosError(e) && e.response?.status === 409
          ? 'Cette proposition a déjà été traitée.'
          : 'Une erreur est survenue.',
      );
    } finally {
      setIsRejecting(false);
    }
  };

  const handleCreateBrand = async (name: string) => {
    const created = await createBrand({ canonicalName: name });
    setBrands((prev) => [...prev, created]);
    return created;
  };

  const handleCreateCategory = async (name: string) => {
    const created = await createCategory({ nameFr: name });
    setCategories((prev) => [...prev, created]);
    return created;
  };

  return (
    <tr>
      <td colSpan={colSpan} className="border-l-4 border-green-400 bg-green-50 px-4 py-4">
        <p className="mb-3 text-xs font-semibold text-green-700">
          ↳ Approuver ou rejeter — {proposal.name_fr}
        </p>

        <div className="mb-3 flex w-fit overflow-hidden rounded-md border border-line">
          <button
            onClick={() => setApproveMode('link')}
            disabled={isSubmitting || isRejecting}
            className={cn(
              'px-3 py-1.5 text-xs transition',
              approveMode === 'link' && !isSubmitting && !isRejecting
                ? 'bg-primary text-white'
                : 'bg-white text-muted',
              (isSubmitting || isRejecting) && 'opacity-50',
            )}
          >
            Lier existant
          </button>
          <button
            onClick={() => setApproveMode('create')}
            disabled={isSubmitting || isRejecting}
            className={cn(
              'px-3 py-1.5 text-xs transition',
              approveMode === 'create' && !isSubmitting && !isRejecting
                ? 'bg-primary text-white'
                : 'bg-white text-muted',
              (isSubmitting || isRejecting) && 'opacity-50',
            )}
          >
            Créer nouveau
          </button>
        </div>

        {approveMode === 'link' ? (
          <div className="mb-3 flex items-start gap-3">
            <div ref={searchContainerRef} className="relative max-w-sm flex-1">
              <input
                type="text"
                value={selectedRef ? selectedRef.name_fr : searchQuery}
                onChange={(e) => {
                  setSelectedRef(null);
                  setSearchQuery(e.target.value);
                }}
                disabled={isSubmitting || isRejecting}
                placeholder="Rechercher un produit…"
                className={cn(
                  'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                  'focus:border-primary focus:ring-2 focus:ring-primary/20',
                  (isSubmitting || isRejecting) && 'opacity-50 cursor-not-allowed',
                )}
              />
              {isSearching && (
                <span className="absolute right-3 top-2.5 text-xs text-muted">…</span>
              )}
              {searchResults.length > 0 && !selectedRef && (
                <ul className="absolute z-10 mt-1 w-full rounded-md border border-line bg-card shadow-floating">
                  {searchResults.map((ref) => (
                    <li key={ref.id}>
                      <button
                        onClick={() => {
                          setSelectedRef(ref);
                          setSearchResults([]);
                        }}
                        className="block w-full px-3 py-2 text-left text-sm hover:bg-soft"
                      >
                        {ref.name_fr}
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>
            <button
              onClick={() => void handleApproveLink()}
              disabled={isSubmitting || !selectedRef}
              className={cn(
                'rounded-md px-3 py-2 text-sm font-semibold transition',
                !selectedRef || isSubmitting
                  ? 'bg-green-100 text-green-500 cursor-not-allowed'
                  : 'bg-green-600 text-white hover:bg-green-700',
              )}
            >
              {isSubmitting ? '…' : 'Lier'}
            </button>
          </div>
        ) : (
          <div className="mb-3 grid grid-cols-2 gap-3 lg:grid-cols-3">
            <div>
              <label className="mb-1 block text-xs font-semibold">Nom FR *</label>
              <input
                type="text"
                value={newNameFr}
                onChange={(e) => setNewNameFr(e.target.value)}
                disabled={isSubmitting || isRejecting}
                placeholder="Nom du produit…"
                className={cn(
                  'w-full rounded-md border border-line px-3 py-2 text-sm outline-none transition',
                  'focus:border-primary focus:ring-2 focus:ring-primary/20',
                  (isSubmitting || isRejecting) && 'opacity-50 cursor-not-allowed',
                )}
              />
            </div>
            <div>
              <label className="mb-1 block text-xs font-semibold">Marque *</label>
              <InlineCreateCombobox
                items={brands}
                value={newBrandId}
                onChange={setNewBrandId}
                getId={(b) => b.id}
                getLabel={(b) => b.canonical_name}
                placeholder="Marque…"
                onCreate={handleCreateBrand}
                disabled={isSubmitting || isRejecting}
              />
            </div>
            <div>
              <label className="mb-1 block text-xs font-semibold">Catégorie *</label>
              <InlineCreateCombobox
                items={categories}
                value={newCategoryId}
                onChange={setNewCategoryId}
                getId={(c) => c.id}
                getLabel={(c) => c.name_fr}
                placeholder="Catégorie…"
                onCreate={handleCreateCategory}
                disabled={isSubmitting || isRejecting}
              />
            </div>
          </div>
        )}

        {showRejectPrompt && (
          <div className="mb-3 flex items-start gap-3 border-t border-line pt-3">
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

        {error && <p className="text-xs text-red-600">{error}</p>}

        <div className="mt-3 flex items-center gap-2 border-t border-line pt-3">
          {approveMode === 'link' ? (
            <button
              onClick={() => void handleApproveLink()}
              disabled={isSubmitting || !selectedRef}
              className={cn(
                'rounded-md px-3 py-2 text-sm font-semibold transition',
                !selectedRef || isSubmitting
                  ? 'bg-green-100 text-green-500 cursor-not-allowed'
                  : 'bg-green-600 text-white hover:bg-green-700',
              )}
            >
              {isSubmitting ? '…' : 'Lier et approuver'}
            </button>
          ) : (
            <button
              onClick={() => void handleApproveCreate()}
              disabled={isSubmitting || !newNameFr.trim() || !newBrandId || !newCategoryId}
              className={cn(
                'rounded-md px-3 py-2 text-sm font-semibold transition',
                isSubmitting || !newNameFr.trim() || !newBrandId || !newCategoryId
                  ? 'bg-green-100 text-green-500 cursor-not-allowed'
                  : 'bg-green-600 text-white hover:bg-green-700',
              )}
            >
              {isSubmitting ? '…' : 'Créer et approuver'}
            </button>
          )}
          <button
            onClick={onCancel}
            disabled={isSubmitting || isRejecting}
            className="rounded-md border border-line px-3 py-2 text-sm transition hover:bg-soft disabled:opacity-50"
          >
            Annuler
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
