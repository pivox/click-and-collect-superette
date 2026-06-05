'use client';

import { ChangeEvent, FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { AlertTriangle, Camera, CheckCircle, Download, ScanLine, Search, Upload } from 'lucide-react';
import { Button } from '@/components/ui/Button';
import {
  addMerchantCatalogProduct,
  downloadMerchantCatalogCsvTemplate,
  importMerchantCatalogCsv,
  searchMerchantProductReferences,
} from '@/lib/services/merchant-catalog.service';
import type {
  MerchantCatalogCsvImportResult,
  MerchantProductReferenceSearchItem,
} from '@/lib/types/merchant-catalog.types';

interface MerchantCatalogOnboardingToolsProps {
  storeId: string | null;
  disabled?: boolean;
  onCatalogChanged: () => void;
}

type ImportStatus = 'idle' | 'reading' | 'importing' | 'done' | 'error';

type BarcodeDetectorResult = { rawValue?: string };
type BarcodeDetectorInstance = {
  detect: (source: HTMLVideoElement) => Promise<BarcodeDetectorResult[]>;
};
type BarcodeDetectorConstructor = new (options?: { formats?: string[] }) => BarcodeDetectorInstance;

function normalizeBarcode(value: string): string {
  return value.replace(/\D/g, '').slice(0, 14);
}

function validatePrice(value: string): string | null {
  const normalized = value.trim().replace(',', '.');

  if (!/^\d+(?:\.\d{1,3})?$/.test(normalized)) {
    return null;
  }

  const parsed = Number(normalized);

  if (!Number.isFinite(parsed) || parsed <= 0) {
    return null;
  }

  return parsed.toFixed(3);
}

function csvSummary(result: MerchantCatalogCsvImportResult): string {
  return `${result.created} créé${result.created > 1 ? 's' : ''}, ${result.updated} mis à jour, ${result.ignored} ignoré${result.ignored > 1 ? 's' : ''}`;
}

function productFormat(productReference: MerchantProductReferenceSearchItem): string {
  return [productReference.volume, productReference.unit].filter(Boolean).join(' ');
}

export function MerchantCatalogOnboardingTools({
  disabled = false,
  onCatalogChanged,
  storeId,
}: MerchantCatalogOnboardingToolsProps) {
  const [selectedCsvFile, setSelectedCsvFile] = useState<File | null>(null);
  const [importStatus, setImportStatus] = useState<ImportStatus>('idle');
  const [importResult, setImportResult] = useState<MerchantCatalogCsvImportResult | null>(null);
  const [importError, setImportError] = useState<string | null>(null);
  const [barcode, setBarcode] = useState('');
  const [barcodeStatus, setBarcodeStatus] = useState<'idle' | 'searching' | 'adding'>('idle');
  const [barcodeError, setBarcodeError] = useState<string | null>(null);
  const [barcodeResults, setBarcodeResults] = useState<MerchantProductReferenceSearchItem[]>([]);
  const [selectedBarcodeProduct, setSelectedBarcodeProduct] =
    useState<MerchantProductReferenceSearchItem | null>(null);
  const [scannedPriceTnd, setScannedPriceTnd] = useState('');
  const [isScannedProductAvailable, setIsScannedProductAvailable] = useState(true);
  const [isScannedProductVisible, setIsScannedProductVisible] = useState(true);
  const [isCameraActive, setIsCameraActive] = useState(false);
  const [cameraMessage, setCameraMessage] = useState<string | null>(null);
  const videoRef = useRef<HTMLVideoElement>(null);
  const cameraStreamRef = useRef<MediaStream | null>(null);
  const animationFrameRef = useRef<number | null>(null);

  const stopCamera = useCallback(() => {
    if (animationFrameRef.current !== null) {
      window.cancelAnimationFrame(animationFrameRef.current);
      animationFrameRef.current = null;
    }

    cameraStreamRef.current?.getTracks().forEach((track) => track.stop());
    cameraStreamRef.current = null;
    setIsCameraActive(false);
  }, []);

  useEffect(() => {
    return () => stopCamera();
  }, [stopCamera]);

  useEffect(() => {
    if (!isCameraActive) return;

    const video = videoRef.current;
    const stream = cameraStreamRef.current;

    if (!video || !stream) return;

    let isCancelled = false;
    video.srcObject = stream;
    void video.play().catch(() => undefined);

    const BarcodeDetector = (window as Window & { BarcodeDetector?: BarcodeDetectorConstructor })
      .BarcodeDetector;
    if (!BarcodeDetector) {
      return () => {
        isCancelled = true;
        if (video.srcObject === stream) {
          video.srcObject = null;
        }
      };
    }

    const detector = new BarcodeDetector({
      formats: ['ean_13', 'ean_8', 'upc_a', 'upc_e', 'code_128'],
    });

    const scan = async () => {
      if (isCancelled || !cameraStreamRef.current) return;

      try {
        const results = await detector.detect(video);
        const detected = normalizeBarcode(results[0]?.rawValue ?? '');
        if (detected.length >= 8) {
          setBarcode(detected);
          stopCamera();
          setCameraMessage(`Code-barres détecté : ${detected}`);
          return;
        }
      } catch {
        setCameraMessage('Lecture automatique indisponible. Saisis le code-barres manuellement.');
        stopCamera();
        return;
      }

      animationFrameRef.current = window.requestAnimationFrame(scan);
    };

    animationFrameRef.current = window.requestAnimationFrame(scan);

    return () => {
      isCancelled = true;
      if (animationFrameRef.current !== null) {
        window.cancelAnimationFrame(animationFrameRef.current);
        animationFrameRef.current = null;
      }
      if (video.srcObject === stream) {
        video.srcObject = null;
      }
    };
  }, [isCameraActive, stopCamera]);

  const handleCsvFileChange = (event: ChangeEvent<HTMLInputElement>) => {
    const file = event.target.files?.[0] ?? null;
    setSelectedCsvFile(file);
    setImportResult(null);
    setImportError(null);
    setImportStatus('idle');
  };

  const handleImportCsv = async (event: FormEvent<HTMLFormElement>) => {
    event.preventDefault();

    if (!storeId || !selectedCsvFile) return;

    setImportStatus('reading');
    setImportResult(null);
    setImportError(null);

    try {
      const csv = await selectedCsvFile.text();
      setImportStatus('importing');
      const result = await importMerchantCatalogCsv(storeId, csv);
      setImportResult(result);
      setImportStatus('done');
      onCatalogChanged();
    } catch {
      setImportStatus('error');
      setImportError("Impossible d'importer ce fichier CSV.");
    }
  };

  const handleSearchBarcode = async (event?: FormEvent<HTMLFormElement>) => {
    event?.preventDefault();

    if (!storeId) return;

    const normalizedBarcode = normalizeBarcode(barcode);
    setBarcode(normalizedBarcode);

    if (normalizedBarcode.length < 8) {
      setBarcodeError('Le code-barres doit contenir au moins 8 chiffres.');
      setBarcodeResults([]);
      setSelectedBarcodeProduct(null);
      return;
    }

    setBarcodeStatus('searching');
    setBarcodeError(null);
    setBarcodeResults([]);
    setSelectedBarcodeProduct(null);

    try {
      const result = await searchMerchantProductReferences(storeId, {
        barcode: normalizedBarcode,
        page: 1,
        limit: 10,
      });
      setBarcodeResults(result.items);
      if (result.items.length === 0) {
        setBarcodeError('Aucune correspondance référentiel pour ce code-barres.');
      }
    } catch {
      setBarcodeError('Impossible de rechercher ce code-barres.');
    } finally {
      setBarcodeStatus('idle');
    }
  };

  const handleAddScannedProduct = async () => {
    if (!storeId || !selectedBarcodeProduct) return;

    const normalizedPrice = validatePrice(scannedPriceTnd);
    if (!normalizedPrice) {
      setBarcodeError('Le prix doit être supérieur à 0 avec au maximum 3 décimales.');
      return;
    }

    setBarcodeStatus('adding');
    setBarcodeError(null);

    try {
      await addMerchantCatalogProduct(storeId, {
        product_reference_id: selectedBarcodeProduct.id,
        price_tnd: normalizedPrice,
        is_available: isScannedProductAvailable,
        is_visible: isScannedProductVisible,
        merchant_note: null,
        merchant_category_id: null,
      });
      setBarcode('');
      setBarcodeResults([]);
      setSelectedBarcodeProduct(null);
      setScannedPriceTnd('');
      setIsScannedProductAvailable(true);
      setIsScannedProductVisible(true);
      onCatalogChanged();
    } catch {
      setBarcodeError(
        selectedBarcodeProduct.already_in_catalog
          ? 'Ce produit est déjà dans mon catalogue.'
          : "Impossible d'ajouter le produit scanné.",
      );
    } finally {
      setBarcodeStatus('idle');
    }
  };

  const startCamera = async () => {
    if (!navigator.mediaDevices?.getUserMedia) {
      setCameraMessage('Caméra indisponible sur ce navigateur. Saisis le code-barres manuellement.');
      return;
    }

    try {
      const stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false,
      });
      cameraStreamRef.current = stream;
      setIsCameraActive(true);
      setCameraMessage('Caméra ouverte. Cadre le code-barres ou utilise la saisie manuelle.');
    } catch {
      setCameraMessage('Impossible d’ouvrir la caméra. Saisis le code-barres manuellement.');
      stopCamera();
    }
  };

  return (
    <section className="mt-5 rounded-md bg-card p-4 shadow-card">
      <div className="grid gap-5 xl:grid-cols-2">
        <div className="space-y-4">
          <div className="flex items-start gap-3">
            <Upload size={20} aria-hidden="true" className="mt-1 text-primary" />
            <div>
              <h2 className="font-black text-ink">Import CSV catalogue</h2>
              <p className="mt-1 text-sm text-muted">
                Charge un fichier CSV pour créer ou mettre à jour rapidement les produits de la supérette.
              </p>
            </div>
          </div>

          <div className="flex flex-wrap gap-3">
            <Button
              type="button"
              variant="ghost"
              size="md"
              disabled={disabled}
              onClick={downloadMerchantCatalogCsvTemplate}
            >
              <Download size={16} aria-hidden="true" />
              Télécharger modèle CSV
            </Button>
          </div>

          <form className="grid gap-3 sm:grid-cols-[1fr_auto]" onSubmit={handleImportCsv}>
            <div>
              <label htmlFor="merchant-catalog-csv-file" className="mb-1 block text-sm font-bold">
                Fichier CSV catalogue
              </label>
              <input
                id="merchant-catalog-csv-file"
                type="file"
                accept=".csv,text/csv"
                disabled={disabled || importStatus === 'reading' || importStatus === 'importing'}
                onChange={handleCsvFileChange}
                className="block min-h-[44px] w-full rounded-md border border-line bg-white px-3 py-2 text-sm file:mr-3 file:rounded-md file:border-0 file:bg-soft file:px-3 file:py-2 file:text-sm file:font-black"
              />
            </div>
            <Button
              type="submit"
              size="md"
              disabled={
                disabled ||
                !selectedCsvFile ||
                importStatus === 'reading' ||
                importStatus === 'importing'
              }
            >
              {importStatus === 'reading' || importStatus === 'importing' ? 'Import…' : 'Importer CSV'}
            </Button>
          </form>

          {(importStatus === 'reading' || importStatus === 'importing') && (
            <p role="status" className="text-sm text-muted">
              {importStatus === 'reading' ? 'Lecture du fichier CSV…' : 'Import en cours…'}
            </p>
          )}

          {importError && (
            <div role="alert" className="rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
              {importError}
            </div>
          )}

          {importResult && (
            <div className="space-y-3 rounded-md border border-line p-3">
              <p role="status" className="flex items-center gap-2 text-sm font-black text-status-ready">
                <CheckCircle size={16} aria-hidden="true" />
                {csvSummary(importResult)}
              </p>
              {importResult.items.length > 0 && (
                <ul className="space-y-1 text-sm text-muted">
                  {importResult.items.map((item) => (
                    <li key={`${item.line}-${item.merchant_product_id}`}>
                      Ligne {item.line} · {item.status} · {item.name_fr}
                    </li>
                  ))}
                </ul>
              )}
              {importResult.errors.length > 0 && (
                <ul className="space-y-1 text-sm text-status-cancel">
                  {importResult.errors.map((error) => (
                    <li key={`${error.line}-${error.code}`}>
                      Ligne {error.line} · {error.field ?? error.code} · {error.message}
                    </li>
                  ))}
                </ul>
              )}
            </div>
          )}
        </div>

        <div className="space-y-4">
          <div className="flex items-start gap-3">
            <ScanLine size={20} aria-hidden="true" className="mt-1 text-primary" />
            <div>
              <h2 className="font-black text-ink">Scan code-barres</h2>
              <p className="mt-1 text-sm text-muted">
                Scanne ou saisis un code-barres, puis ajoute la correspondance au catalogue avec son prix TND.
              </p>
            </div>
          </div>

          <div className="flex flex-wrap gap-3">
            <Button
              type="button"
              variant="ghost"
              size="md"
              disabled={disabled || isCameraActive}
              onClick={() => void startCamera()}
            >
              <Camera size={16} aria-hidden="true" />
              Ouvrir caméra
            </Button>
            {isCameraActive && (
              <Button type="button" variant="ghost" size="md" onClick={stopCamera}>
                Fermer caméra
              </Button>
            )}
          </div>

          {(isCameraActive || cameraMessage) && (
            <div className="space-y-2">
              {isCameraActive && (
                <video
                  ref={videoRef}
                  muted
                  playsInline
                  className="aspect-video w-full rounded-md bg-ink object-cover"
                />
              )}
              {cameraMessage && <p className="text-sm text-muted">{cameraMessage}</p>}
            </div>
          )}

          <form className="grid gap-3 sm:grid-cols-[1fr_auto]" onSubmit={handleSearchBarcode}>
            <div>
              <label htmlFor="merchant-barcode-input" className="mb-1 block text-sm font-bold">
                Code-barres
              </label>
              <input
                id="merchant-barcode-input"
                type="text"
                inputMode="numeric"
                value={barcode}
                disabled={disabled || barcodeStatus === 'searching'}
                onChange={(event) => setBarcode(normalizeBarcode(event.target.value))}
                className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
              />
            </div>
            <Button
              type="submit"
              size="md"
              disabled={disabled || barcodeStatus === 'searching'}
            >
              <Search size={16} aria-hidden="true" />
              {barcodeStatus === 'searching' ? 'Recherche…' : 'Rechercher le code-barres'}
            </Button>
          </form>

          {barcodeError && (
            <div role="alert" className="flex gap-2 rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel">
              <AlertTriangle size={16} aria-hidden="true" className="mt-0.5 shrink-0" />
              <span>{barcodeError}</span>
            </div>
          )}

          {barcodeResults.length > 0 && (
            <div className="space-y-2">
              {barcodeResults.map((productReference) => (
                <article key={productReference.id} className="rounded-md border border-line p-3">
                  <div className="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                      <h3 className="font-black text-ink">{productReference.name_fr}</h3>
                      <p className="mt-1 text-sm text-muted">
                        {productReference.brand} · {productReference.category}
                      </p>
                      <p className="mt-1 text-sm text-muted">
                        {productFormat(productReference)}
                        {productReference.barcode ? ` · ${productReference.barcode}` : ''}
                      </p>
                    </div>
                    <Button
                      type="button"
                      variant="ghost"
                      size="md"
                      disabled={productReference.already_in_catalog}
                      onClick={() => {
                        setSelectedBarcodeProduct(productReference);
                        setScannedPriceTnd('');
                        setIsScannedProductAvailable(true);
                        setIsScannedProductVisible(true);
                        setBarcodeError(null);
                      }}
                    >
                      Choisir {productReference.name_fr}
                    </Button>
                  </div>
                </article>
              ))}
            </div>
          )}

          {selectedBarcodeProduct && (
            <div className="space-y-3 border-t border-line pt-4">
              <p className="text-sm font-black text-ink">
                Produit sélectionné : {selectedBarcodeProduct.name_fr}
              </p>
              <div>
                <label htmlFor="merchant-barcode-price" className="mb-1 block text-sm font-bold">
                  Prix TND pour le code-barres
                </label>
                <input
                  id="merchant-barcode-price"
                  type="text"
                  inputMode="decimal"
                  value={scannedPriceTnd}
                  onChange={(event) => setScannedPriceTnd(event.target.value)}
                  className="h-11 w-full rounded-md border border-line bg-white px-3 text-sm outline-none focus:border-primary focus:ring-2 focus:ring-primary/20"
                />
              </div>
              <div className="flex flex-wrap gap-4">
                <label className="flex min-h-[44px] items-center gap-2 text-sm font-bold">
                  <input
                    type="checkbox"
                    checked={isScannedProductAvailable}
                    onChange={(event) => setIsScannedProductAvailable(event.target.checked)}
                    className="h-5 w-5 rounded border-line"
                  />
                  Disponible
                </label>
                <label className="flex min-h-[44px] items-center gap-2 text-sm font-bold">
                  <input
                    type="checkbox"
                    checked={isScannedProductVisible}
                    onChange={(event) => setIsScannedProductVisible(event.target.checked)}
                    className="h-5 w-5 rounded border-line"
                  />
                  Visible
                </label>
              </div>
              <Button
                type="button"
                size="md"
                disabled={disabled || barcodeStatus === 'adding'}
                onClick={() => void handleAddScannedProduct()}
              >
                {barcodeStatus === 'adding' ? 'Ajout…' : 'Ajouter le produit scanné'}
              </Button>
            </div>
          )}
        </div>
      </div>
    </section>
  );
}
