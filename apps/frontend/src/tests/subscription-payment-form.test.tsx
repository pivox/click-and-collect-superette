import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SubscriptionPaymentForm } from '@/components/admin/subscriptions/SubscriptionPaymentForm';
import { createAdminSubscriptionPayment } from '@/lib/services/subscription-payments.service';
import { PAYMENT_DOCUMENT } from './fixtures/subscription-payment';

vi.mock('@/lib/services/subscription-payments.service', () => ({ createAdminSubscriptionPayment: vi.fn() }));

function prepare(amount = '20.001') {
  fireEvent.change(screen.getByLabelText('Montant du règlement (TND)'), { target: { value: amount } });
  // Submit directly: happy-dom incorrectly validates decimal step values.
  fireEvent.submit(screen.getByRole('form', { name: 'Paiement manuel' }));
}

describe('SubscriptionPaymentForm', () => {
  beforeEach(() => { vi.resetAllMocks(); sessionStorage.clear(); });

  it('confirms a partial amount with a unique request key', async () => {
    vi.mocked(createAdminSubscriptionPayment).mockResolvedValue({ id: 'payment-1' } as never);
    const onSaved = vi.fn();
    render(<SubscriptionPaymentForm document={PAYMENT_DOCUMENT} onSaved={onSaved} />);
    prepare();
    expect(screen.getByText(/29,999 TND/)).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Confirmer le paiement manuel' }));
    await waitFor(() => expect(onSaved).toHaveBeenCalledOnce());
    expect(createAdminSubscriptionPayment).toHaveBeenCalledWith(expect.objectContaining({
      billing_document_id: 'doc-1', amount_tnd: '20.001', method: 'cash',
      idempotency_key: expect.stringMatching(/^[0-9a-f-]{36}$/),
    }));
  });

  it('rejects zero and overpayment before making a request', () => {
    render(<SubscriptionPaymentForm document={PAYMENT_DOCUMENT} onSaved={vi.fn()} />);
    prepare('50.001');
    expect(screen.getByRole('alert')).toHaveTextContent(/solde/);
    prepare('0');
    expect(screen.getByRole('alert')).toHaveTextContent(/positif/);
    expect(createAdminSubscriptionPayment).not.toHaveBeenCalled();
  });

  it('retries the identical request after a network error, even after reopening', async () => {
    vi.mocked(createAdminSubscriptionPayment).mockRejectedValueOnce(new Error('Network'))
      .mockResolvedValueOnce({ id: 'payment-1' } as never);
    const first = render(<SubscriptionPaymentForm document={PAYMENT_DOCUMENT} onSaved={vi.fn()} />);
    prepare();
    fireEvent.click(screen.getByRole('button', { name: 'Confirmer le paiement manuel' }));
    await screen.findByRole('alert');
    const payload = vi.mocked(createAdminSubscriptionPayment).mock.calls[0][0];
    first.unmount();
    const saved = vi.fn();
    render(<SubscriptionPaymentForm document={PAYMENT_DOCUMENT} onSaved={saved} />);
    fireEvent.click(screen.getByRole('button', { name: 'Réessayer le même règlement' }));
    await waitFor(() => expect(saved).toHaveBeenCalledOnce());
    expect(vi.mocked(createAdminSubscriptionPayment).mock.calls[1][0]).toEqual(payload);
  });

  it('does not repeat a confirmed payment when refreshing the page data fails', async () => {
    vi.mocked(createAdminSubscriptionPayment).mockResolvedValue({ id: 'payment-1' } as never);
    render(<SubscriptionPaymentForm document={PAYMENT_DOCUMENT} onSaved={vi.fn().mockRejectedValue(new Error('Reload'))} />);
    prepare();
    fireEvent.click(screen.getByRole('button', { name: 'Confirmer le paiement manuel' }));
    expect(await screen.findByRole('status')).toHaveTextContent(/enregistré/);
    expect(screen.queryByRole('button', { name: 'Confirmer le paiement manuel' })).not.toBeInTheDocument();
    expect(createAdminSubscriptionPayment).toHaveBeenCalledOnce();
  });

  it.each([401, 403, 409])('preserves an ambiguous request across status %i and reopening', async (status) => {
    vi.mocked(createAdminSubscriptionPayment).mockRejectedValueOnce(new Error('Network'))
      .mockRejectedValueOnce({ response: { status, data: { detail: 'SUBSCRIPTION_PAYMENT_IDEMPOTENCY_CONFLICT' } } })
      .mockResolvedValueOnce({ id: 'payment-1' } as never);
    const first = render(<SubscriptionPaymentForm document={PAYMENT_DOCUMENT} onSaved={vi.fn()} />);
    prepare();
    fireEvent.click(screen.getByRole('button', { name: 'Confirmer le paiement manuel' }));
    await screen.findByRole('alert');
    fireEvent.click(screen.getByRole('button', { name: 'Réessayer le même règlement' }));
    await waitFor(() => expect(createAdminSubscriptionPayment).toHaveBeenCalledTimes(2));
    await waitFor(() => expect(screen.getByRole('button', { name: 'Réessayer le même règlement' })).toBeEnabled());
    first.unmount();
    render(<SubscriptionPaymentForm document={PAYMENT_DOCUMENT} onSaved={vi.fn()} />);
    fireEvent.click(screen.getByRole('button', { name: 'Réessayer le même règlement' }));
    await screen.findByRole('status');
    const calls = vi.mocked(createAdminSubscriptionPayment).mock.calls;
    expect(calls[2][0]).toEqual(calls[0][0]);
  });
});
