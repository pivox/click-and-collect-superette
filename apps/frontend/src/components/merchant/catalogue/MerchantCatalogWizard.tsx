'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/Button';
import {
  commitMerchantCatalogPhotoImport,
  previewMerchantCatalogPhotoImport,
} from '@/lib/services/merchant-catalog.service';
import type {
  MerchantCatalogCsvImportResult,
  MerchantCatalogPhotoImportPreviewItem,
  MerchantCatalogPhotoImportPreviewResult,
  MerchantCatalogPhotoImportSourceType,
} from '@/lib/types/merchant-catalog.types';

interface MerchantCatalogWizardProps {
  isOpen: boolean;
  storeId: string | null;
  onClose: () => void;
  onOpenLocalProduct: () => void;
  onOpenReferenceSearch: () => void;
  onCatalogChanged: () => void;
}

interface PhotoImportDraftItem extends MerchantCatalogPhotoImportPreviewItem {
  selected: boolean;
  price_tnd: string;
  is_available: boolean;
  is_visible: boolean;
}

const steps = [
  {
    title: '1. Chercher',
    description:
      'Recherche d’abord le produit dans le référentiel pour éviter les doublons dans la supérette.',
  },
  {
    title: '2. Configurer',
    description:
      'Renseigne le prix en TND, la disponibilité, la visibilité et la catégorie marchand.',
  },
  {
    title: '3. Publier',
    description:
      'Rends le produit visible quand il est prêt pour les clients qui préparent leur Kadhia.',
  },
];

const productUnits = [
  { value: 'piece', label: 'Pièce' },
  { value: 'paquet', label: 'Paquet' },
  { value: 'gramme', label: 'Gramme' },
  { value: 'kilogramme', label: 'Kilogramme' },
  { value: 'millilitre', label: 'Millilitre' },
  { value: 'litre', label: 'Litre' },
];

const normalizePhotoImportPrice = (value: string): string => value.trim().replace(',', '.');

export function MerchantCatalogWizard({
  isOpen,
  storeId,
  onClose,
  onOpenLocalProduct,
  onOpenReferenceSearch,
  onCatalogChanged,
}: MerchantCatalogWizardProps) {
  const dialogRef = useRef<HTMLDivElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);
  const [photo, setPhoto] = useState<File | null>(null);
  const [sourceType, setSourceType] = useState<MerchantCatalogPhotoImportSourceType>('receipt');
  const [photoPreview, setPhotoPreview] = useState<MerchantCatalogPhotoImportPreviewResult | null>(null);
  const [photoDraftItems, setPhotoDraftItems] = useState<PhotoImportDraftItem[]>([]);
  const [photoCommitResult, setPhotoCommitResult] = useState<MerchantCatalogCsvImportResult | null>(null);
  const [photoError, setPhotoError] = useState<string | null>(null);
  const [isPhotoSubmitting, setIsPhotoSubmitting] = useState(false);
  const [isPhotoCommitting, setIsPhotoCommitting] = useState(false);

  const handleOpenReferenceSearch = () => {
    onClose();
    onOpenReferenceSearch();
  };

  const handleOpenLocalProduct = () => {
    onClose();
    onOpenLocalProduct();
  };

  const handleClose = useCallback(() => {
    onClose();
  }, [onClose]);

  const handlePhotoPreview = async () => {
    if (!storeId || !photo) return;

    setIsPhotoSubmitting(true);
    setPhotoError(null);
    setPhotoPreview(null);
    setPhotoDraftItems([]);
    setPhotoCommitResult(null);

    try {
      const preview = await previewMerchantCatalogPhotoImport(storeId, photo, sourceType);
      setPhotoPreview(preview);
      setPhotoDraftItems(
        preview.items.map((item) => ({
          ...item,
          selected: !item.already_in_catalog,
          price_tnd: item.suggested_price_tnd ?? '',
          is_available: true,
          is_visible: true,
        })),
      );
    } catch {
      setPhotoError("Impossible d'analyser cette photo.");
    } finally {
      setIsPhotoSubmitting(false);
    }
  };

  const updatePhotoDraftItem = (
    line: number,
    patch: Partial<
      Pick<
        PhotoImportDraftItem,
        'selected' | 'name_fr' | 'brand' | 'volume' | 'unit' | 'price_tnd' | 'is_available' | 'is_visible'
      >
    >,
  ) => {
    setPhotoDraftItems((currentItems) =>
      currentItems.map((item) => (item.line === line ? { ...item, ...patch } : item)),
    );
  };

  const handlePhotoCommit = async () => {
    if (!storeId || photoDraftItems.length === 0) return;

    setIsPhotoCommitting(true);
    setPhotoError(null);
    setPhotoCommitResult(null);

    try {
      const result = await commitMerchantCatalogPhotoImport(storeId, {
        items: photoDraftItems
          .filter((item) => item.selected)
          .map((item) => ({
            line: item.line,
            selected: item.selected,
            status: item.status,
            product_reference_id: item.product_reference_id,
            name_fr: item.name_fr,
            brand: item.brand,
            volume: item.volume,
            unit: item.unit,
            barcode: item.barcode,
            price_tnd: normalizePhotoImportPrice(item.price_tnd),
            is_available: item.is_available,
            is_visible: item.is_visible,
            merchant_note: null,
            pack_quantity: 1,
          })),
      });
      setPhotoCommitResult(result);
      onCatalogChanged();
    } catch {
      setPhotoError("Impossible de valider l'import photo.");
    } finally {
      setIsPhotoCommitting(false);
    }
  };

  useEffect(() => {
    if (!isOpen) return;

    previousFocusRef.current =
      document.activeElement instanceof HTMLElement ? document.activeElement : null;
    closeButtonRef.current?.focus();

    return () => {
      const previousFocus = previousFocusRef.current;
      if (previousFocus && document.contains(previousFocus)) {
        previousFocus.focus();
      }
      previousFocusRef.current = null;
    };
  }, [isOpen]);

  useEffect(() => {
    if (!isOpen) return;

    const handler = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        handleClose();
        return;
      }

      if (event.key !== 'Tab') return;

      const dialog = dialogRef.current;
      if (!dialog) return;

      const focusableElements = Array.from(
        dialog.querySelectorAll<HTMLElement>(
          'a[href], button:not([disabled]), input:not([disabled]), textarea:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])',
        ),
      ).filter((element) => !element.hasAttribute('hidden'));

      if (focusableElements.length === 0) return;

      const firstElement = focusableElements[0];
      const lastElement = focusableElements[focusableElements.length - 1];
      const activeElement =
        document.activeElement instanceof HTMLElement ? document.activeElement : null;

      if (!activeElement || !dialog.contains(activeElement)) {
        event.preventDefault();
        firstElement.focus();
        return;
      }

      if (event.shiftKey && activeElement === firstElement) {
        event.preventDefault();
        lastElement.focus();
        return;
      }

      if (!event.shiftKey && activeElement === lastElement) {
        event.preventDefault();
        firstElement.focus();
      }
    };

    document.addEventListener('keydown', handler);

    return () => document.removeEventListener('keydown', handler);
  }, [handleClose, isOpen]);

  const hasSelectedPhotoDraftItems = photoDraftItems.some((item) => item.selected);
  const hasSelectedPhotoDraftItemWithoutPrice = photoDraftItems.some(
    (item) => item.selected && normalizePhotoImportPrice(item.price_tnd) === '',
  );

  if (!isOpen) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-start justify-center bg-ink/40 px-4 py-8 md:items-center">
      <div
        ref={dialogRef}
        role="dialog"
        aria-modal="true"
        aria-label="Assistant catalogue"
        className="w-full max-w-2xl rounded-md bg-card shadow-card"
      >
        <div className="flex items-start justify-between gap-4 border-b border-line px-5 py-4">
          <div>
            <h2 className="text-xl font-black">Assistant catalogue</h2>
            <p className="mt-1 text-sm text-muted">
              Suis le parcours d’enrichissement sans quitter les actions catalogue existantes.
            </p>
          </div>
          <Button ref={closeButtonRef} variant="ghost" size="md" onClick={onClose}>
            Fermer
          </Button>
        </div>

        <div className="px-5 py-5">
          <ol className="grid gap-3 md:grid-cols-3">
            {steps.map((step) => (
              <li key={step.title} className="rounded-md border border-line bg-white p-4">
                <h3 className="text-sm font-black">{step.title}</h3>
                <p className="mt-2 text-sm text-muted">{step.description}</p>
              </li>
            ))}
          </ol>

          <div className="mt-5 flex flex-col gap-3 border-t border-line pt-4 sm:flex-row sm:justify-end">
            <Button variant="ghost" size="md" onClick={handleOpenLocalProduct}>
              Créer un produit de ma supérette
            </Button>
            <Button size="md" onClick={handleOpenReferenceSearch}>
              Ajouter un produit connu
            </Button>
          </div>

          <div className="mt-5 border-t border-line pt-4">
            <div className="grid gap-3 md:grid-cols-[1fr_180px_auto] md:items-end">
              <div>
                <label htmlFor="catalog-photo-import" className="mb-1 block text-sm font-bold">
                  Photo ticket, rayon ou liste papier
                </label>
                <input
                  id="catalog-photo-import"
                  type="file"
                  accept="image/jpeg,image/png,image/webp"
                  className="block w-full rounded-md border border-line bg-white px-3 py-2 text-sm"
                  onChange={(event) => {
                    setPhoto(event.target.files?.[0] ?? null);
                    setPhotoPreview(null);
                    setPhotoDraftItems([]);
                    setPhotoCommitResult(null);
                    setPhotoError(null);
                  }}
                />
              </div>
              <div>
                <label htmlFor="catalog-photo-source" className="mb-1 block text-sm font-bold">
                  Source photo
                </label>
                <select
                  id="catalog-photo-source"
                  value={sourceType}
                  onChange={(event) => setSourceType(event.target.value as MerchantCatalogPhotoImportSourceType)}
                  className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm"
                >
                  <option value="receipt">Ticket</option>
                  <option value="shelf">Rayon</option>
                  <option value="cash_register_export">Export caisse</option>
                  <option value="paper_list">Liste papier</option>
                </select>
              </div>
              <Button
                size="md"
                onClick={() => void handlePhotoPreview()}
                disabled={!storeId || !photo || isPhotoSubmitting}
              >
                {isPhotoSubmitting ? 'Analyse…' : 'Analyser la photo'}
              </Button>
            </div>

            {photoError && (
              <div className="mt-3 rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
                {photoError}
              </div>
            )}

            {photoPreview && (
              <div className="mt-4 rounded-md border border-line bg-white p-3">
                <p className="text-sm font-black">
                  {photoPreview.detected_count} produits détectés · {photoPreview.matched_reference_count} match référentiel · {photoPreview.local_candidate_count} à créer localement
                </p>
                <ul className="mt-3 space-y-2">
                  {photoPreview.items.map((item) => (
                    <li key={`${item.line}-${item.name_fr}`} className="text-sm text-muted">
                      Ligne {item.line} · {photoStatusLabel(item.status)} · {item.name_fr}
                      {item.suggested_price_tnd ? ` · ${item.suggested_price_tnd} TND` : ''}
                    </li>
                  ))}
                </ul>
                <div className="mt-4 space-y-3">
                  {photoDraftItems.map((item) => (
                    <div
                      key={`draft-${item.line}-${item.name_fr}`}
                      className="grid gap-3 rounded-md border border-line bg-soft p-3 md:grid-cols-[1fr_120px_auto_auto]"
                    >
                      <label className="flex items-center gap-2 text-sm font-bold">
                        <input
                          type="checkbox"
                          checked={item.selected}
                          disabled={item.already_in_catalog}
                          onChange={(event) => updatePhotoDraftItem(item.line, { selected: event.target.checked })}
                        />
                        Ligne {item.line} · {item.name_fr}
                      </label>
                      {item.status === 'local_candidate' && (
                        <div className="grid gap-3 md:col-span-4 md:grid-cols-[minmax(160px,1fr)_minmax(120px,160px)_110px_130px]">
                          <label className="text-xs font-bold text-muted">
                            Nom produit
                            <input
                              type="text"
                              value={item.name_fr}
                              disabled={!item.selected}
                              onChange={(event) => updatePhotoDraftItem(item.line, { name_fr: event.target.value })}
                              className="mt-1 h-10 w-full rounded-md border border-line bg-white px-2 text-sm font-normal text-ink"
                            />
                          </label>
                          <label className="text-xs font-bold text-muted">
                            Marque
                            <input
                              type="text"
                              value={item.brand ?? ''}
                              disabled={!item.selected}
                              onChange={(event) => updatePhotoDraftItem(item.line, { brand: event.target.value })}
                              className="mt-1 h-10 w-full rounded-md border border-line bg-white px-2 text-sm font-normal text-ink"
                            />
                          </label>
                          <label className="text-xs font-bold text-muted">
                            Volume
                            <input
                              type="text"
                              inputMode="decimal"
                              value={item.volume ?? ''}
                              disabled={!item.selected}
                              onChange={(event) => updatePhotoDraftItem(item.line, { volume: event.target.value })}
                              className="mt-1 h-10 w-full rounded-md border border-line bg-white px-2 text-sm font-normal text-ink"
                            />
                          </label>
                          <label className="text-xs font-bold text-muted">
                            Unité
                            <select
                              value={item.unit ?? ''}
                              disabled={!item.selected}
                              onChange={(event) => updatePhotoDraftItem(item.line, { unit: event.target.value || null })}
                              className="mt-1 h-10 w-full rounded-md border border-line bg-white px-2 text-sm font-normal text-ink"
                            >
                              <option value="">Choisir</option>
                              {productUnits.map((unit) => (
                                <option key={unit.value} value={unit.value}>
                                  {unit.label}
                                </option>
                              ))}
                            </select>
                          </label>
                        </div>
                      )}
                      <label className="text-xs font-bold text-muted">
                        Prix TND
                        <input
                          type="text"
                          inputMode="decimal"
                          value={item.price_tnd}
                          disabled={!item.selected}
                          onChange={(event) => updatePhotoDraftItem(item.line, { price_tnd: event.target.value })}
                          className="mt-1 h-10 w-full rounded-md border border-line bg-white px-2 text-sm font-normal text-ink"
                        />
                      </label>
                      <label className="flex items-center gap-2 text-sm font-bold">
                        <input
                          type="checkbox"
                          checked={item.is_available}
                          disabled={!item.selected}
                          onChange={(event) => updatePhotoDraftItem(item.line, { is_available: event.target.checked })}
                        />
                        Disponible
                      </label>
                      <label className="flex items-center gap-2 text-sm font-bold">
                        <input
                          type="checkbox"
                          checked={item.is_visible}
                          disabled={!item.selected}
                          onChange={(event) => updatePhotoDraftItem(item.line, { is_visible: event.target.checked })}
                        />
                        Visible
                      </label>
                    </div>
                  ))}
                </div>
                <div className="mt-4 flex justify-end">
                  <Button
                    size="md"
                    onClick={() => void handlePhotoCommit()}
                    disabled={isPhotoCommitting || !hasSelectedPhotoDraftItems || hasSelectedPhotoDraftItemWithoutPrice}
                  >
                    {isPhotoCommitting ? 'Validation…' : 'Valider l’import photo'}
                  </Button>
                </div>
              </div>
            )}

            {photoCommitResult && (
              <div className="mt-3 space-y-2 rounded-md bg-status-ready-bg px-3 py-2 text-sm">
                <p role="status" className="text-status-ready">
                  {photoCommitResult.created} créé, {photoCommitResult.updated} mis à jour, {photoCommitResult.ignored} ignoré
                </p>
                {photoCommitResult.errors.length > 0 && (
                  <ul className="space-y-1 text-status-cancel">
                    {photoCommitResult.errors.map((error) => (
                      <li key={`${error.line}-${error.code}`}>
                        Ligne {error.line} · {error.field ?? error.code} · {error.message}
                      </li>
                    ))}
                  </ul>
                )}
              </div>
            )}
          </div>
        </div>
      </div>
    </div>
  );
}

function photoStatusLabel(status: MerchantCatalogPhotoImportPreviewResult['items'][number]['status']) {
  if (status === 'matched_reference') return 'Référentiel';
  if (status === 'already_in_catalog') return 'Déjà au catalogue';

  return 'À créer localement';
}
