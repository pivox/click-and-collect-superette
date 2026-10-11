import { fireEvent, render, screen } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import { SubscriptionDialog } from '@/components/admin/subscriptions/SubscriptionDialog';

it('keeps the payment link reachable in the keyboard focus cycle', () => {
  render(<SubscriptionDialog title="Abonnement" onClose={vi.fn()}>
    <button type="button">Modifier</button><a href="/admin/facturation">Paiement</a>
  </SubscriptionDialog>);
  const edit = screen.getByRole('button', { name: 'Modifier' });
  edit.focus();
  expect(fireEvent.keyDown(edit, { key: 'Tab' })).toBe(true);
  const payment = screen.getByRole('link', { name: 'Paiement' });
  payment.focus();
  fireEvent.keyDown(payment, { key: 'Tab' });
  expect(screen.getByRole('button', { name: 'Fermer' })).toHaveFocus();
});

it('prevents closing while a save is in progress', () => {
  const close = vi.fn();
  render(<SubscriptionDialog title="Abonnement" onClose={close} busy><p>Enregistrement…</p></SubscriptionDialog>);
  expect(screen.getByRole('button', { name: 'Fermer' })).toBeDisabled();
  fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Escape' });
  expect(close).not.toHaveBeenCalled();
  expect(fireEvent.keyDown(screen.getByRole('dialog'), { key: 'Tab' })).toBe(false);
  expect(screen.getByRole('dialog')).toHaveFocus();
});
