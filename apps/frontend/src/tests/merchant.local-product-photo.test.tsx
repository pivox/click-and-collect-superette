import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MerchantLocalProductPhotoSection } from '@/components/merchant/catalogue/MerchantLocalProductPhotoSection';
import { MerchantLocaleProvider } from '@/lib/i18n/MerchantLocaleContext';
import {
  deleteMerchantLocalProductPhoto,
  uploadMerchantLocalProductPhoto,
} from '@/lib/services/merchant-local-product-photo.service';
import type { MerchantCatalogProductImage } from '@/lib/types/merchant-catalog.types';

vi.mock('@/lib/services/merchant-local-product-photo.service', async (importOriginal) => {
  const actual =
    await importOriginal<typeof import('@/lib/services/merchant-local-product-photo.service')>();
  return {
    ...actual,
    uploadMerchantLocalProductPhoto: vi.fn(),
    deleteMerchantLocalProductPhoto: vi.fn(),
  };
});

const storedImage: MerchantCatalogProductImage = {
  original_url: '/uploads/products/i-1/original.jpg',
  thumbnail_url: '/uploads/products/i-1/200.webp',
  card_url: '/uploads/products/i-1/400.webp',
  detail_url: '/uploads/products/i-1/800.webp',
  zoom_url: '/uploads/products/i-1/1200.webp',
  fallback_jpeg_url: '/uploads/products/i-1/fallback.jpg',
  alt: 'Harissa maison',
  status: 'candidate',
  license_code: 'merchant_authorized',
};

function renderSection(
  props: Partial<React.ComponentProps<typeof MerchantLocalProductPhotoSection>> = {},
) {
  return render(
    <MerchantLocaleProvider>
      <MerchantLocalProductPhotoSection
        storeId="store-1"
        localProductId="lp-1"
        productName="Harissa maison"
        categoryName="Épicerie"
        image={null}
        {...props}
      />
    </MerchantLocaleProvider>,
  );
}

function selectFile(file: File) {
  const input = document.getElementById('local-product-photo-lp-1') as HTMLInputElement;
  fireEvent.change(input, { target: { files: [file] } });
}

describe('MerchantLocalProductPhotoSection (#583)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    vi.stubGlobal('URL', {
      ...URL,
      createObjectURL: vi.fn(() => 'blob:preview-1'),
      revokeObjectURL: vi.fn(),
    });
  });

  it('shows the shooting rules and the file input with camera capture', () => {
    renderSection();

    expect(screen.getByText('Règles de prise de vue')).toBeInTheDocument();
    expect(screen.getByText('Un seul produit par photo, bien cadré')).toBeInTheDocument();
    expect(screen.getByText('Pas de visage ni de données personnelles')).toBeInTheDocument();
    expect(screen.getByText('Fond neutre et propre')).toBeInTheDocument();

    const input = document.getElementById('local-product-photo-lp-1') as HTMLInputElement;
    expect(input).toHaveAttribute('accept', 'image/jpeg,image/png,image/webp');
    expect(input).toHaveAttribute('capture', 'environment');
    expect(screen.getByText('Aucune photo pour ce produit local.')).toBeInTheDocument();
  });

  it('previews the selected file before sending, then uploads it', async () => {
    vi.mocked(uploadMerchantLocalProductPhoto).mockResolvedValue(storedImage);
    const onChanged = vi.fn();
    renderSection({ onChanged });

    const file = new File(['fake-bytes'], 'photo.jpg', { type: 'image/jpeg' });
    selectFile(file);

    // Local preview via URL.createObjectURL, before any network call.
    expect(URL.createObjectURL).toHaveBeenCalledWith(file);
    expect(screen.getByAltText("Aperçu de la photo avant envoi")).toHaveAttribute(
      'src',
      'blob:preview-1',
    );
    expect(uploadMerchantLocalProductPhoto).not.toHaveBeenCalled();

    fireEvent.click(screen.getByRole('button', { name: 'Envoyer la photo' }));

    await waitFor(() =>
      expect(uploadMerchantLocalProductPhoto).toHaveBeenCalledWith('store-1', 'lp-1', file),
    );
    await waitFor(() => expect(onChanged).toHaveBeenCalled());
    // The stored variant replaces the local preview.
    expect(screen.getByAltText('Harissa maison')).toHaveAttribute(
      'src',
      'http://localhost:8000/uploads/products/i-1/400.webp',
    );
  });

  it('shows a precise message when the backend rejects a too-small photo (422)', async () => {
    vi.mocked(uploadMerchantLocalProductPhoto).mockRejectedValue({
      response: { status: 422, data: { detail: 'MERCHANT_PHOTO_TOO_SMALL' } },
    });
    renderSection();

    selectFile(new File(['tiny'], 'tiny.jpg', { type: 'image/jpeg' }));
    fireEvent.click(screen.getByRole('button', { name: 'Envoyer la photo' }));

    await waitFor(() =>
      expect(screen.getByRole('alert')).toHaveTextContent(
        'Photo trop petite : minimum 320×320 pixels.',
      ),
    );
  });

  it('displays the current photo and deletes it on demand', async () => {
    vi.mocked(deleteMerchantLocalProductPhoto).mockResolvedValue(undefined);
    const onChanged = vi.fn();
    renderSection({ image: storedImage, onChanged });

    expect(screen.getByAltText('Harissa maison')).toHaveAttribute(
      'src',
      'http://localhost:8000/uploads/products/i-1/400.webp',
    );

    fireEvent.click(screen.getByRole('button', { name: 'Supprimer la photo' }));

    await waitFor(() =>
      expect(deleteMerchantLocalProductPhoto).toHaveBeenCalledWith('store-1', 'lp-1'),
    );
    await waitFor(() => expect(onChanged).toHaveBeenCalled());
    expect(screen.getByText('Aucune photo pour ce produit local.')).toBeInTheDocument();
  });
});
