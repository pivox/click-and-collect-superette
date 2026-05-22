'use client';
import { useState, useEffect } from 'react';
import { approveProposal, rejectProposal } from '@/lib/services/admin/proposals.service';
import { listProductReferences } from '@/lib/services/admin/product-references.service';
import { listBrands } from '@/lib/services/admin/brands.service';
import { listCategories } from '@/lib/services/admin/categories.service';
import type { Proposal, ProductReference, Brand, Category } from '@/lib/types/admin/referentiel.types';
import { cn } from '@/lib/cn';

type ExpansionMode = 'approve' | 'reject';
type ApproveMode = 'link' | 'create';

interface ProposalRowProps {
  proposal: Proposal;
  isExpanded: ExpansionMode | null;
  onToggle: (id: string, mode: ExpansionMode | null) => void;
  onProcessed: () => void;
}

export function ProposalRow({ proposal, isExpanded, onToggle, onProcessed }: ProposalRowProps) {
  const [approveMode, setApproveMode] = useState<ApproveMode>('link');
  const [searchQuery, setSearchQuery] = useState('');
  const [searchResults, setSearchResults] = useState<ProductReference[]>([]);
  const [selectedRef, setSelectedRef] = useState<ProductReference | null>(null);
  const [isSearching, setIsSearching] = useState(false);
  const [newNameFr, setNewNameFr] = useState('');
  const [newBrandId, setNewBrandId] = useState('');
  const [newCategoryId, setNewCategoryId] = useState('');
  const [brands, setBrands] = useState<Brand[]>([]);
  const [categories, setCategories] = useState<Category[]>([]);
  const [rejectReason, setRejectReason] = useState('');
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  useEffect(() => {
    if (isExpanded !== 'approve' || approveMode !== 'create') return;
    void Promise.all([listBrands(1, 50), listCategories(1, 50)]).then(([b, c]) => {
      setBrands(b.items);
      setCategories(c.items);
    });
  }, [isExpanded, approveMode]);

  useEffect(() => {
    if (!searchQuery || searchQuery.length < 2) { setSearchResults([]); return; }
    const t = setTimeout(async () => {
      setIsSearching(true);
      try {
        const data = await listProductReferences({ q: searchQuery, limit: 10 });
        setSearchResults(data.items);
      } finally {
        setIsSearching(false);
      }
    }, 300);
    return () => clearTimeout(t);
  }, [searchQuery]);

  const reset = () => {
    setSearchQuery(''); setSelectedRef(null); setSearchResults([]);
    setNewNameFr(''); setNewBrandId(''); setNewCategoryId('');
    setRejectReason(''); setError(null); setApproveMode('link');
  };

  const handleApprove = async () => {
    setError(null);
    if (approveMode === 'link') {
      if (!selectedRef) { setError('Sélectionnez un produit existant.'); return; }
      setIsSubmitting(true);
      try {
        await approveProposal(proposal.id, { productReferenceId: selectedRef.id });
        reset(); onToggle(proposal.id, null); onProcessed();
      } catch (e) {
        const msg = String(e);
        setError(msg.includes('409') ? 'Cette proposition a déjà été traitée.' : 'Une erreur est survenue.');
      } finally { setIsSubmitting(false); }
    } else {
      if (!newNameFr.trim() || !newBrandId || !newCategoryId) {
        setError('Nom FR, Marque et Catégorie sont obligatoires.');
        return;
      }
      setIsSubmitting(true);
      try {
        await approveProposal(proposal.id, {
          canonicalData: { nameFr: newNameFr.trim(), brandId: newBrandId, categoryId: newCategoryId },
        });
        reset(); onToggle(proposal.id, null); onProcessed();
      } catch (e) {
        const msg = String(e);
        setError(msg.includes('409') ? 'Cette proposition a déjà été traitée.' : 'Une erreur est survenue.');
      } finally { setIsSubmitting(false); }
    }
  };

  const handleReject = async () => {
    if (!rejectReason.trim()) { setError('La raison est obligatoire.'); return; }
    setIsSubmitting(true);
    setError(null);
    try {
      await rejectProposal(proposal.id, rejectReason.trim());
      reset(); onToggle(proposal.id, null); onProcessed();
    } catch {
      setError('Une erreur est survenue.');
    } finally { setIsSubmitting(false); }
  };

  const isPending = proposal.status === 'pending';

  return (
    <>
      <tr className={cn('hover:bg-soft/50', isExpanded && 'bg-soft/30')}>
        <td className="px-4 py-3 font-medium text-ink">{proposal.name_fr}</td>
        <td className="px-4 py-3 text-sm text-muted">{proposal.brand_name ?? '—'}</td>
        <td className="px-4 py-3 text-sm text-muted">{proposal.category}</td>
        <td className="px-4 py-3 text-xs text-muted">{proposal.proposed_by}</td>
        <td className="px-4 py-3 text-xs text-muted">
          {new Date(proposal.created_at).toLocaleDateString('fr-FR')}
        </td>
        <td className="px-4 py-3">
          {isPending && (
            <div className="flex gap-2">
              <button
                onClick={() => { reset(); onToggle(proposal.id, isExpanded === 'approve' ? null : 'approve'); }}
                className="rounded bg-green-100 px-2 py-1 text-xs font-semibold text-green-700 hover:bg-green-200"
              >
                ✓
              </button>
              <button
                onClick={() => { reset(); onToggle(proposal.id, isExpanded === 'reject' ? null : 'reject'); }}
                className="rounded bg-red-100 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-200"
              >
                ✗
              </button>
            </div>
          )}
        </td>
      </tr>

      {isExpanded === 'approve' && (
        <tr>
          <td
            colSpan={6}
            className="border-l-4 border-green-400 bg-green-50 px-4 py-3"
          >
            <p className="mb-2 text-xs font-semibold text-green-700">
              ↳ Approuver — lier à un produit existant ou créer nouveau
            </p>
            <div className="mb-3 flex w-fit overflow-hidden rounded-md border border-line">
              <button
                onClick={() => setApproveMode('link')}
                className={cn('px-3 py-1.5 text-xs', approveMode === 'link' ? 'bg-primary text-white' : 'bg-white text-muted')}
              >
                Lier existant
              </button>
              <button
                onClick={() => setApproveMode('create')}
                className={cn('px-3 py-1.5 text-xs', approveMode === 'create' ? 'bg-primary text-white' : 'bg-white text-muted')}
              >
                Créer nouveau
              </button>
            </div>

            {approveMode === 'link' ? (
              <div className="flex items-start gap-3">
                <div className="relative max-w-sm flex-1">
                  <input
                    type="text"
                    value={selectedRef ? selectedRef.name_fr : searchQuery}
                    onChange={(e) => { setSelectedRef(null); setSearchQuery(e.target.value); }}
                    placeholder="Rechercher un produit canonique…"
                    className="w-full rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
                  />
                  {isSearching && (
                    <span className="absolute right-3 top-2.5 text-xs text-muted">…</span>
                  )}
                  {searchResults.length > 0 && !selectedRef && (
                    <ul className="absolute z-10 mt-1 w-full rounded-md border border-line bg-card shadow-floating">
                      {searchResults.map((r) => (
                        <li
                          key={r.id}
                          onClick={() => { setSelectedRef(r); setSearchResults([]); setSearchQuery(''); }}
                          className="cursor-pointer px-3 py-2 text-sm hover:bg-soft"
                        >
                          {r.name_fr}
                          {r.variant_fr && <span className="text-muted"> ({r.variant_fr})</span>}
                          <span className="ml-1 text-xs text-muted">— {r.brand_name}</span>
                        </li>
                      ))}
                    </ul>
                  )}
                </div>
                <button
                  onClick={handleApprove}
                  disabled={isSubmitting}
                  className="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                >
                  {isSubmitting ? '…' : 'Confirmer'}
                </button>
              </div>
            ) : (
              <div className="flex flex-wrap items-start gap-3">
                <input
                  type="text"
                  value={newNameFr}
                  onChange={(e) => setNewNameFr(e.target.value)}
                  placeholder="Nom canonique FR *"
                  className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
                />
                <select
                  value={newBrandId}
                  onChange={(e) => setNewBrandId(e.target.value)}
                  className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
                >
                  <option value="">Marque *</option>
                  {brands.map((b) => <option key={b.id} value={b.id}>{b.canonical_name}</option>)}
                </select>
                <select
                  value={newCategoryId}
                  onChange={(e) => setNewCategoryId(e.target.value)}
                  className="rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
                >
                  <option value="">Catégorie *</option>
                  {categories.map((c) => <option key={c.id} value={c.id}>{c.name_fr}</option>)}
                </select>
                <button
                  onClick={handleApprove}
                  disabled={isSubmitting}
                  className="rounded-md bg-green-600 px-3 py-2 text-sm font-semibold text-white hover:bg-green-700 disabled:opacity-50"
                >
                  {isSubmitting ? '…' : 'Confirmer'}
                </button>
              </div>
            )}
            {error && <p className="mt-2 text-xs text-danger">{error}</p>}
          </td>
        </tr>
      )}

      {isExpanded === 'reject' && (
        <tr>
          <td colSpan={6} className="border-l-4 border-red-400 bg-red-50 px-4 py-3">
            <p className="mb-2 text-xs font-semibold text-red-700">↳ Raison du rejet *</p>
            <div className="flex items-start gap-3">
              <textarea
                value={rejectReason}
                onChange={(e) => setRejectReason(e.target.value)}
                rows={2}
                placeholder="Expliquer le motif du rejet…"
                className="max-w-sm flex-1 rounded-md border border-line px-3 py-2 text-sm outline-none focus:border-primary"
              />
              <button
                onClick={handleReject}
                disabled={isSubmitting}
                className="rounded-md bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700 disabled:opacity-50"
              >
                {isSubmitting ? '…' : 'Confirmer le rejet'}
              </button>
            </div>
            {error && <p className="mt-2 text-xs text-danger">{error}</p>}
          </td>
        </tr>
      )}
    </>
  );
}
