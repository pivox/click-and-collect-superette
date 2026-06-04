import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import { ClientLocaleProvider, useClientLocale } from '@/lib/i18n/ClientLocaleContext';

function Consumer() {
  const { locale, dir, setLocale, t } = useClientLocale();
  return (
    <div>
      <span data-testid="locale">{locale}</span>
      <span data-testid="dir">{dir}</span>
      <span data-testid="home">{t('client.nav.home')}</span>
      <button onClick={() => setLocale('ar')}>to-ar</button>
      <button onClick={() => setLocale('fr')}>to-fr</button>
    </div>
  );
}

function renderConsumer() {
  return render(
    <ClientLocaleProvider>
      <Consumer />
    </ClientLocaleProvider>,
  );
}

describe('ClientLocaleContext (S14-004)', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('defaults to French (ltr) when nothing is stored', async () => {
    renderConsumer();
    await waitFor(() => expect(screen.getByTestId('locale').textContent).toBe('fr'));
    expect(screen.getByTestId('dir').textContent).toBe('ltr');
    expect(screen.getByTestId('home').textContent).toBe('Accueil');
  });

  it('restores the stored locale and applies RTL for Arabic', async () => {
    localStorage.setItem('client:lang', 'ar');
    renderConsumer();
    await waitFor(() => expect(screen.getByTestId('locale').textContent).toBe('ar'));
    expect(screen.getByTestId('dir').textContent).toBe('rtl');
    expect(screen.getByTestId('home').textContent).toBe('الرئيسية');
  });

  it('setLocale persists the choice under the client-scoped key', async () => {
    renderConsumer();
    await waitFor(() => screen.getByRole('button', { name: 'to-ar' }));

    act(() => screen.getByRole('button', { name: 'to-ar' }).click());
    await waitFor(() => expect(screen.getByTestId('dir').textContent).toBe('rtl'));
    expect(localStorage.getItem('client:lang')).toBe('ar');
  });

  it('preserves business vocabulary (Kadhia) untranslated in Arabic', async () => {
    localStorage.setItem('client:lang', 'ar');
    function KadhiaLabel() {
      const { t } = useClientLocale();
      return <span data-testid="kadhia">{t('client.nav.kadhia')}</span>;
    }
    render(
      <ClientLocaleProvider>
        <KadhiaLabel />
      </ClientLocaleProvider>,
    );
    await waitFor(() => expect(screen.getByTestId('kadhia').textContent).toBe('Kadhia'));
  });

  it('throws when used outside the provider', () => {
    const err = console.error;
    console.error = () => {};
    expect(() => render(<Consumer />)).toThrow();
    console.error = err;
  });
});
