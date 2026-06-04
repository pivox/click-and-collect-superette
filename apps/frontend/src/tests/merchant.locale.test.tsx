import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import {
  MerchantLocaleProvider,
  useMerchantLocale,
} from '@/lib/i18n/MerchantLocaleContext';

function Consumer() {
  const { locale, dir, setLocale, t } = useMerchantLocale();
  return (
    <div>
      <span data-testid="locale">{locale}</span>
      <span data-testid="dir">{dir}</span>
      <span data-testid="title">{t('merchant.settings.title')}</span>
      <button onClick={() => setLocale('ar')}>to-ar</button>
      <button onClick={() => setLocale('fr')}>to-fr</button>
    </div>
  );
}

function renderConsumer() {
  return render(
    <MerchantLocaleProvider>
      <Consumer />
    </MerchantLocaleProvider>,
  );
}

describe('MerchantLocaleContext (MS-004)', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('defaults to French (ltr) when nothing is stored', async () => {
    renderConsumer();
    await waitFor(() => expect(screen.getByTestId('locale').textContent).toBe('fr'));
    expect(screen.getByTestId('dir').textContent).toBe('ltr');
    expect(screen.getByTestId('title').textContent).toBe('Paramètres');
  });

  it('restores the stored locale on mount and applies RTL for Arabic', async () => {
    localStorage.setItem('merchant:lang', 'ar');
    renderConsumer();
    await waitFor(() => expect(screen.getByTestId('locale').textContent).toBe('ar'));
    expect(screen.getByTestId('dir').textContent).toBe('rtl');
    expect(screen.getByTestId('title').textContent).toBe('الإعدادات');
  });

  it('setLocale persists the choice and switches direction', async () => {
    renderConsumer();
    await waitFor(() => screen.getByRole('button', { name: 'to-ar' }));

    act(() => screen.getByRole('button', { name: 'to-ar' }).click());
    await waitFor(() => expect(screen.getByTestId('dir').textContent).toBe('rtl'));
    expect(localStorage.getItem('merchant:lang')).toBe('ar');

    act(() => screen.getByRole('button', { name: 'to-fr' }).click());
    await waitFor(() => expect(screen.getByTestId('dir').textContent).toBe('ltr'));
    expect(localStorage.getItem('merchant:lang')).toBe('fr');
  });

  it('ignores an invalid stored value and keeps the default locale', async () => {
    localStorage.setItem('merchant:lang', 'es');
    renderConsumer();
    await waitFor(() => expect(screen.getByTestId('locale').textContent).toBe('fr'));
  });

  it('falls back to the key when a translation is missing', async () => {
    function MissingKey() {
      const { t } = useMerchantLocale();
      return <span data-testid="missing">{t('merchant.settings.unknown.key')}</span>;
    }
    render(
      <MerchantLocaleProvider>
        <MissingKey />
      </MerchantLocaleProvider>,
    );
    await waitFor(() =>
      expect(screen.getByTestId('missing').textContent).toBe('merchant.settings.unknown.key'),
    );
  });

  it('throws when used outside the provider', () => {
    const err = console.error;
    console.error = () => {};
    expect(() => render(<Consumer />)).toThrow();
    console.error = err;
  });
});
