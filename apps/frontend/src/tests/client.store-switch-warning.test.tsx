import { render, screen } from '@testing-library/react';
import React from 'react';
import { describe, expect, it, vi } from 'vitest';
import { StoreSwitchWarning } from '@/components/store/StoreSwitchWarning';
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext';

describe('StoreSwitchWarning', () => {
  it('affiche le nom du store actuel dans le message', () => {
    render(
      <ClientLocaleProvider>
        <StoreSwitchWarning currentStoreName="Aziza Montplaisir" onConfirm={vi.fn()} onCancel={vi.fn()} />
      </ClientLocaleProvider>,
    );
    expect(screen.getByText('Aziza Montplaisir')).toBeTruthy();
  });

  it('appelle onConfirm au clic sur "Changer quand même"', () => {
    const onConfirm = vi.fn();
    render(
      <ClientLocaleProvider>
        <StoreSwitchWarning currentStoreName="Aziza" onConfirm={onConfirm} onCancel={vi.fn()} />
      </ClientLocaleProvider>,
    );
    screen.getByRole('button', { name: 'Changer quand même' }).click();
    expect(onConfirm).toHaveBeenCalledOnce();
  });

  it('appelle onCancel au clic sur "Annuler"', () => {
    const onCancel = vi.fn();
    render(
      <ClientLocaleProvider>
        <StoreSwitchWarning currentStoreName="Aziza" onConfirm={vi.fn()} onCancel={onCancel} />
      </ClientLocaleProvider>,
    );
    screen.getByRole('button', { name: 'Annuler' }).click();
    expect(onCancel).toHaveBeenCalledOnce();
  });
});
