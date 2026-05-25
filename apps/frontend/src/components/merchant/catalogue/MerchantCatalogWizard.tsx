'use client';

import { useCallback, useEffect, useRef } from 'react';
import { Button } from '@/components/ui/Button';

interface MerchantCatalogWizardProps {
  isOpen: boolean;
  onClose: () => void;
  onOpenLocalProduct: () => void;
  onOpenReferenceSearch: () => void;
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

export function MerchantCatalogWizard({
  isOpen,
  onClose,
  onOpenLocalProduct,
  onOpenReferenceSearch,
}: MerchantCatalogWizardProps) {
  const dialogRef = useRef<HTMLDivElement>(null);
  const closeButtonRef = useRef<HTMLButtonElement>(null);
  const previousFocusRef = useRef<HTMLElement | null>(null);

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
              Produit local
            </Button>
            <Button size="md" onClick={handleOpenReferenceSearch}>
              Depuis référentiel
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
