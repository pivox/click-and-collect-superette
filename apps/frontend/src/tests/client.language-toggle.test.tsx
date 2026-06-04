import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import { LanguageToggle } from '@/components/layout/LanguageToggle';
import { ClientLocaleProvider } from '@/lib/i18n/ClientLocaleContext';

function renderToggle() {
  return render(
    <ClientLocaleProvider>
      <LanguageToggle />
    </ClientLocaleProvider>,
  );
}

describe('LanguageToggle (S14-004)', () => {
  beforeEach(() => {
    localStorage.clear();
  });

  it('marks French as selected by default', async () => {
    renderToggle();
    await waitFor(() => screen.getByRole('radio', { name: 'Français' }));
    expect(screen.getByRole('radio', { name: 'Français' })).toHaveAttribute('aria-checked', 'true');
    expect(screen.getByRole('radio', { name: 'العربية' })).toHaveAttribute('aria-checked', 'false');
  });

  it('switches to Arabic and persists the choice', async () => {
    renderToggle();
    await waitFor(() => screen.getByRole('radio', { name: 'العربية' }));

    act(() => screen.getByRole('radio', { name: 'العربية' }).click());

    await waitFor(() =>
      expect(screen.getByRole('radio', { name: 'العربية' })).toHaveAttribute('aria-checked', 'true'),
    );
    expect(localStorage.getItem('client:lang')).toBe('ar');
  });
});
