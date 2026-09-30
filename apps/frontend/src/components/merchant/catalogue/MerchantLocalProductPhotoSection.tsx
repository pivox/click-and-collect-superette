'use client';

import { useCallback, useEffect, useRef, useState } from 'react';
import { categoryPlaceholder } from '@/lib/category-placeholder';
import { mediaUrl } from '@/lib/media';
import { useMerchantLocale } from '@/lib/i18n/MerchantLocaleContext';
import {
  deleteMerchantLocalProductPhoto,
  extractMerchantPhotoErrorCode,
  uploadMerchantLocalProductPhoto,
} from '@/lib/services/merchant-local-product-photo.service';
import type { MerchantCatalogProductImage } from '@/lib/types/merchant-catalog.types';

// PRODUCT-IMAGE-003 (#583): merchant photo block for a local/vrac/reconditioned
// product. The photo illustrates the product in this shop's catalogue only —
// promotion to the shared referential image stays an explicit admin act.

interface MerchantLocalProductPhotoSectionProps {
  storeId: string;
  localProductId: string;
  productName: string;
  categoryName?: string | null;
  image?: MerchantCatalogProductImage | null;
  onChanged?: () => void;
}

const RULE_KEYS = ['single', 'readable', 'noPeople', 'background', 'packaging'] as const;

export function MerchantLocalProductPhotoSection({
  categoryName,
  image,
  localProductId,
  onChanged,
  productName,
  storeId,
}: MerchantLocalProductPhotoSectionProps) {
  const { t } = useMerchantLocale();
  const [currentImage, setCurrentImage] = useState<MerchantCatalogProductImage | null>(
    image ?? null,
  );
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [isSubmitting, setIsSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const fileInputRef = useRef<HTMLInputElement>(null);

  useEffect(() => {
    setCurrentImage(image ?? null);
    setSelectedFile(null);
    setError(null);
  }, [image, localProductId]);

  // Revoke the object URL of the local preview when it is replaced or unmounted.
  useEffect(() => {
    return () => {
      if (previewUrl) URL.revokeObjectURL(previewUrl);
    };
  }, [previewUrl]);

  const handleFileChange = useCallback((files: FileList | null) => {
    const file = files?.[0] ?? null;
    setSelectedFile(file);
    setError(null);
    setPreviewUrl(file ? URL.createObjectURL(file) : null);
  }, []);

  const handleUpload = useCallback(async () => {
    if (!selectedFile || isSubmitting) return;

    setIsSubmitting(true);
    setError(null);
    try {
      const uploaded = await uploadMerchantLocalProductPhoto(
        storeId,
        localProductId,
        selectedFile,
      );
      setCurrentImage(uploaded);
      setSelectedFile(null);
      setPreviewUrl(null);
      if (fileInputRef.current) fileInputRef.current.value = '';
      onChanged?.();
    } catch (uploadError) {
      const code = extractMerchantPhotoErrorCode(uploadError);
      setError(
        code === 'MERCHANT_PHOTO_TOO_SMALL'
          ? t('merchant.catalogPhoto.errors.tooSmall')
          : code === 'PRODUCT_IMAGE_TOO_LARGE'
            ? t('merchant.catalogPhoto.errors.tooLarge')
            : code === 'PRODUCT_IMAGE_UNSUPPORTED_MIME'
              ? t('merchant.catalogPhoto.errors.unsupported')
              : code === 'PRODUCT_IMAGE_UNREADABLE'
                ? t('merchant.catalogPhoto.errors.unreadable')
                : t('merchant.catalogPhoto.errors.generic'),
      );
    } finally {
      setIsSubmitting(false);
    }
  }, [isSubmitting, localProductId, onChanged, selectedFile, storeId, t]);

  const handleDelete = useCallback(async () => {
    if (isSubmitting || !currentImage) return;

    setIsSubmitting(true);
    setError(null);
    try {
      await deleteMerchantLocalProductPhoto(storeId, localProductId);
      setCurrentImage(null);
      onChanged?.();
    } catch {
      setError(t('merchant.catalogPhoto.errors.deleteFailed'));
    } finally {
      setIsSubmitting(false);
    }
  }, [currentImage, isSubmitting, localProductId, onChanged, storeId, t]);

  const handleCancelSelection = useCallback(() => {
    setSelectedFile(null);
    setPreviewUrl(null);
    setError(null);
    if (fileInputRef.current) fileInputRef.current.value = '';
  }, []);

  const inputId = `local-product-photo-${localProductId}`;

  return (
    <div className="rounded-md border border-line bg-soft p-3">
      <p className="text-sm font-black">{t('merchant.catalogPhoto.title')}</p>

      {error && (
        <div
          role="alert"
          className="mt-2 rounded-md bg-status-cancel-bg px-3 py-2 text-sm text-status-cancel"
        >
          {error}
        </div>
      )}

      <div className="mt-3 flex items-start gap-3">
        <div className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-md border border-line bg-white">
          {previewUrl ? (
            // eslint-disable-next-line @next/next/no-img-element -- local blob preview
            <img
              src={previewUrl}
              alt={t('merchant.catalogPhoto.previewAlt')}
              className="h-full w-full object-contain"
            />
          ) : currentImage?.card_url ? (
            // eslint-disable-next-line @next/next/no-img-element -- API-served responsive variant
            <img
              src={mediaUrl(currentImage.card_url) ?? undefined}
              alt={currentImage.alt ?? t('merchant.catalogPhoto.currentAlt')}
              className="h-full w-full object-contain"
            />
          ) : (
            <span aria-hidden="true" className="text-3xl">
              {categoryPlaceholder(null, categoryName ?? productName)}
            </span>
          )}
        </div>

        <div className="flex-1 space-y-2">
          {!currentImage && !selectedFile && (
            <p className="text-sm text-muted">{t('merchant.catalogPhoto.empty')}</p>
          )}

          <label
            htmlFor={inputId}
            className="inline-block cursor-pointer rounded-md border border-line bg-white px-3 py-1.5 text-xs font-bold hover:bg-soft"
          >
            {currentImage || selectedFile
              ? t('merchant.catalogPhoto.replace')
              : t('merchant.catalogPhoto.choose')}
          </label>
          <input
            ref={fileInputRef}
            id={inputId}
            type="file"
            accept="image/jpeg,image/png,image/webp"
            capture="environment"
            disabled={isSubmitting}
            onChange={(event) => handleFileChange(event.target.files)}
            className="sr-only"
          />

          {selectedFile && (
            <div className="flex flex-wrap gap-2">
              <button
                type="button"
                onClick={() => void handleUpload()}
                disabled={isSubmitting}
                className="rounded-md bg-primary px-3 py-1.5 text-xs font-bold text-white disabled:opacity-40"
              >
                {isSubmitting
                  ? t('merchant.catalogPhoto.sending')
                  : t('merchant.catalogPhoto.send')}
              </button>
              <button
                type="button"
                onClick={handleCancelSelection}
                disabled={isSubmitting}
                className="rounded-md border border-line bg-white px-3 py-1.5 text-xs font-bold disabled:opacity-40"
              >
                {t('merchant.catalogPhoto.cancel')}
              </button>
            </div>
          )}

          {currentImage && !selectedFile && (
            <button
              type="button"
              onClick={() => void handleDelete()}
              disabled={isSubmitting}
              className="block text-xs font-black text-status-cancel hover:underline disabled:opacity-40"
            >
              {t('merchant.catalogPhoto.delete')}
            </button>
          )}
        </div>
      </div>

      <div className="mt-3">
        <p className="text-xs font-bold text-muted">
          {t('merchant.catalogPhoto.rulesTitle')}
        </p>
        <ul className="mt-1 list-disc space-y-0.5 ps-4 text-xs text-muted">
          {RULE_KEYS.map((key) => (
            <li key={key}>{t(`merchant.catalogPhoto.rules.${key}`)}</li>
          ))}
        </ul>
      </div>
    </div>
  );
}
