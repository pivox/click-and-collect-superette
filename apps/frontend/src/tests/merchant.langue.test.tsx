import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import MerchantLanguagePage from '@/app/merchant/parametres/langue/page';
import { MerchantLocaleProvider } from '@/lib/i18n/MerchantLocaleContext';

function renderPage() {
  return render(
    <MerchantLocaleProvider>
      <MerchantLanguagePage />
    </MerchantLocaleProvider>,
  );
}

describe('MerchantLanguagePage (MS-004)', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('marks French as the selected option by default', async () => {
    renderPage();
    await waitFor(() => screen.getByRole('radio', { name: /Français/i }));
    expect(screen.getByRole('radio', { name: /Français/i })).toHaveAttribute(
      'aria-checked',
      'true',
    );
    expect(screen.getByRole('radio', { name: /العربية/i })).toHaveAttribute(
      'aria-checked',
      'false',
    );
  });

  it('switches to Arabic, persists the choice and confirms the change', async () => {
    renderPage();
    await waitFor(() => screen.getByRole('radio', { name: /العربية/i }));

    act(() => screen.getByRole('radio', { name: /العربية/i }).click());

    await waitFor(() =>
      expect(screen.getByRole('radio', { name: /العربية/i })).toHaveAttribute(
        'aria-checked',
        'true',
      ),
    );
    expect(localStorage.getItem('merchant:lang')).toBe('ar');
    // Confirmation message uses the Arabic translation after the switch.
    expect(screen.getByRole('status').textContent).toBe('تم تحديث اللغة.');
  });

  it('links back to the settings hub', async () => {
    renderPage();
    await waitFor(() => screen.getByRole('link'));
    expect(screen.getByRole('link')).toHaveAttribute('href', '/merchant/parametres');
  });
});
