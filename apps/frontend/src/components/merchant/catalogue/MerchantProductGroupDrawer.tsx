'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import {
  getMerchantProductGroup,
  importMerchantProductGroup,
  listMerchantProductGroups,
} from '@/lib/services/merchant-catalog.service';
import type {
  MerchantProductGroup,
  MerchantProductGroupImportResult,
  MerchantProductGroupItem,
} from '@/lib/types/merchant-catalog.types';

interface MerchantProductGroupDrawerProps {
  isOpen: boolean;
  storeId: string | null;
  onClose: () => void;
  onImported?: () => void | Promise<void>;
}

const lineStatusLabel: Record<MerchantProductGroupItem['status'], string> = {
  new: 'Nouveau',
  already_present: 'Déjà présent',
  non_importable: 'Non importable',
};

function productFormat(item: MerchantProductGroupItem): string {
  return [
    item.product_reference.brand_name,
    item.product_reference.category_name_fr,
    item.product_reference.volume,
    item.product_reference.unit,
  ]
    .filter(Boolean)
    .join(' · ');
}

export function MerchantProductGroupDrawer({
  isOpen,
  onClose,
  onImported,
  storeId,
}: MerchantProductGroupDrawerProps) {
  const [groups, setGroups] = useState<MerchantProductGroup[]>([]);
  const [selectedGroup, setSelectedGroup] = useState<MerchantProductGroup | null>(null);
  const [selectedReferenceIds, setSelectedReferenceIds] = useState<string[]>([]);
  const [importResult, setImportResult] = useState<MerchantProductGroupImportResult | null>(null);
  const [isLoadingGroups, setIsLoadingGroups] = useState(false);
  const [isLoadingDetail, setIsLoadingDetail] = useState(false);
  const [isImporting, setIsImporting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const requestRef = useRef(0);

  const reset = useCallback(() => {
    requestRef.current += 1;
    setGroups([]);
    setSelectedGroup(null);
    setSelectedReferenceIds([]);
    setImportResult(null);
    setError(null);
    setIsLoadingGroups(false);
    setIsLoadingDetail(false);
    setIsImporting(false);
  }, []);

  const handleClose = useCallback(() => {
    reset();
    onClose();
  }, [onClose, reset]);

  useEffect(() => {
    if (!isOpen || !storeId) return;

    closeButtonRef.current?.focus();
    const requestId = requestRef.current + 1;
    requestRef.current = requestId;
    setIsLoadingGroups(true);
    setError(null);
    setImportResult(null);

    void listMerchantProductGroups(storeId)
      .then((result) => {
        if (requestRef.current !== requestId) return;
        setGroups(result.items);
      })
      .catch(() => {
        if (requestRef.current !== requestId) return;
        setGroups([]);
        setError('Impossible de charger les groupements.');
      })
      .finally(() => {
        if (requestRef.current !== requestId) return;
        setIsLoadingGroups(false);
      });
  }, [isOpen, storeId]);

  useEffect(() => {
    if (!isOpen) return;

    const handler = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        handleClose();
      }
    };

    document.addEventListener('keydown', handler);

    return () => document.removeEventListener('keydown', handler);
  }, [handleClose, isOpen]);

  if (!isOpen || !storeId) return null;

  const applyGroupDetail = (detail: MerchantProductGroup) => {
    setSelectedGroup(detail);
    setSelectedReferenceIds(
      (detail.items ?? [])
        .filter((item) => item.status === 'new')
        .map((item) => item.product_reference.id),
    );
  };

  const handleSelectGroup = async (groupId: string) => {
    const requestId = requestRef.current + 1;
    requestRef.current = requestId;
    setIsLoadingDetail(true);
    setError(null);
    setImportResult(null);

    try {
      const detail = await getMerchantProductGroup(storeId, groupId);
      if (requestRef.current !== requestId) return;

      applyGroupDetail(detail);
    } catch {
      if (requestRef.current !== requestId) return;
      setSelectedGroup(null);
      setSelectedReferenceIds([]);
      setError('Impossible de charger ce groupement.');
    } finally {
      if (requestRef.current !== requestId) return;
      setIsLoadingDetail(false);
    }
  };

  const handleToggleReference = (referenceId: string) => {
    setImportResult(null);
    setSelectedReferenceIds((currentIds) =>
      currentIds.includes(referenceId)
        ? currentIds.filter((currentId) => currentId !== referenceId)
        : [...currentIds, referenceId],
    );
  };

  const handleImport = async () => {
    if (!selectedGroup || selectedReferenceIds.length === 0 || isImporting) return;

    const requestId = requestRef.current + 1;
    requestRef.current = requestId;
    const groupId = selectedGroup.id;
    setIsImporting(true);
    setImportResult(null);
    setError(null);

    try {
      const result = await importMerchantProductGroup(storeId, {
        groupId,
        selectedProductReferenceIds: selectedReferenceIds,
        skipExisting: true,
        defaultVisibility: false,
        defaultAvailability: true,
      });
      if (requestRef.current !== requestId) return;
      setImportResult(result);

      try {
        const detail = await getMerchantProductGroup(storeId, groupId);
        if (requestRef.current !== requestId) return;
        applyGroupDetail(detail);
      } catch {
        if (requestRef.current !== requestId) return;
        setError('Import effectué, mais impossible de rafraîchir ce groupement.');
      }

      await onImported?.();
    } catch {
      if (requestRef.current !== requestId) return;
      setError("Impossible d'importer ce groupement.");
    } finally {
      if (requestRef.current !== requestId) return;
      setIsImporting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex justify-end bg-ink/40">
      <div
        role="dialog"
        aria-modal="true"
        aria-label="Ajouter par groupement"
        className="flex h-full w-full max-w-3xl flex-col bg-card shadow-card"
      >
        <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
          <div>
            <h2 className="text-xl font-black">Ajouter par groupement</h2>
            <p className="mt-1 text-sm text-muted">
              Sélectionne les références à préparer pour l&apos;import catalogue.
            </p>
          </div>
          <Button ref={closeButtonRef} variant="ghost" size="md" onClick={handleClose}>
            Fermer
          </Button>
        </div>

        <div className="grid min-h-0 flex-1 gap-0 overflow-hidden md:grid-cols-[280px_1fr]">
          <aside className="overflow-y-auto border-b border-line p-4 md:border-b-0 md:border-r">
            {isLoadingGroups ? (
              <p className="text-sm text-muted">Chargement des groupements...</p>
            ) : groups.length === 0 ? (
              <p className="text-sm text-muted">Aucun groupement publié disponible.</p>
            ) : (
              <div className="space-y-2">
                {groups.map((group) => (
                  <button
                    key={group.id}
                    type="button"
                    onClick={() => void handleSelectGroup(group.id)}
                    className={`w-full rounded-md border px-3 py-3 text-left text-sm transition-colors ${
                      selectedGroup?.id === group.id
                        ? 'border-primary bg-primary/10'
                        : 'border-line bg-white hover:bg-soft'
                    }`}
                  >
                    <span className="block font-black">{group.name_fr}</span>
                    <span className="mt-1 block text-xs text-muted">
                      {group.items_count} référence{group.items_count > 1 ? 's' : ''}
                    </span>
                  </button>
                ))}
              </div>
            )}
          </aside>

          <main className="min-h-0 overflow-y-auto p-5">
            {error && (
              <div role="alert" className="mb-4 rounded-md bg-status-cancel-bg px-4 py-3 text-sm text-status-cancel">
                {error}
              </div>
            )}

            {isLoadingDetail && (
              <p className="text-sm text-muted">Chargement du détail...</p>
            )}

            {!isLoadingDetail && !selectedGroup && (
              <p className="text-sm text-muted">Choisis un groupement pour voir les produits.</p>
            )}

            {!isLoadingDetail && selectedGroup && (
              <div className="space-y-4">
                <div>
                  <h3 className="text-lg font-black">{selectedGroup.name_fr}</h3>
                  {selectedGroup.description_fr && (
                    <p className="mt-1 text-sm text-muted">{selectedGroup.description_fr}</p>
                  )}
                </div>

                <div className="divide-y divide-line rounded-md border border-line">
                  {(selectedGroup.items ?? []).map((item) => {
                    const referenceId = item.product_reference.id;
                    const disabled = item.status !== 'new';

                    return (
                      <label
                        key={item.id}
                        className={`flex gap-3 px-4 py-3 text-sm ${
                          disabled ? 'bg-soft text-muted' : 'bg-white'
                        }`}
                      >
                        <input
                          type="checkbox"
                          aria-label={item.product_reference.name_fr}
                          checked={selectedReferenceIds.includes(referenceId)}
                          disabled={disabled}
                          onChange={() => handleToggleReference(referenceId)}
                          className="mt-1 h-4 w-4"
                        />
                        <span className="min-w-0 flex-1">
                          <span className="block font-black text-ink">
                            {item.product_reference.name_fr}
                          </span>
                          <span className="mt-1 block text-xs">{productFormat(item)}</span>
                        </span>
                        <span className="shrink-0 rounded-md bg-soft px-2 py-1 text-xs font-bold">
                          {lineStatusLabel[item.status]}
                        </span>
                      </label>
                    );
                  })}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3">
                  <p className="text-sm text-muted">
                    {selectedReferenceIds.length} référence{selectedReferenceIds.length > 1 ? 's' : ''} sélectionnée{selectedReferenceIds.length > 1 ? 's' : ''}
                  </p>
                  <Button
                    size="md"
                    disabled={selectedReferenceIds.length === 0 || isImporting}
                    onClick={() => void handleImport()}
                  >
                    {isImporting ? 'Import en cours...' : 'Importer la sélection'}
                  </Button>
                </div>

                {importResult && (
                  <div role="status" className="rounded-md bg-status-ready-bg px-4 py-3 text-sm text-status-ready">
                    <p className="font-black">Import terminé</p>
                    <div className="mt-3 grid gap-2 text-xs font-bold sm:grid-cols-2">
                      <span className="rounded-md bg-white px-3 py-2">{importResult.created} créé</span>
                      <span className="rounded-md bg-white px-3 py-2">{importResult.alreadyInCatalog} déjà au catalogue</span>
                      <span className="rounded-md bg-white px-3 py-2">{importResult.skipped} ignoré</span>
                      <span className="rounded-md bg-white px-3 py-2">{importResult.requiresPriceCompletion} prix à compléter</span>
                    </div>
                    {importResult.errors.length > 0 && (
                      <ul className="mt-3 space-y-1 text-xs">
                        {importResult.errors.map((importError) => (
                          <li key={`${importError.productReferenceId}-${importError.code}`}>
                            {importError.message}
                          </li>
                        ))}
                      </ul>
                    )}
                  </div>
                )}
              </div>
            )}
          </main>
        </div>
      </div>
    </div>
  );
}
