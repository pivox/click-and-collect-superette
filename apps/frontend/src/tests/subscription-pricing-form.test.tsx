import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { SubscriptionPricingForm } from '@/components/admin/subscriptions/SubscriptionPricingForm';
import { createAdminSubscription, previewAdminSubscriptionPricing, previewNewAdminSubscription, updateAdminSubscriptionPricing } from '@/lib/services/subscriptions.service';
import type { MerchantSubscription } from '@/lib/types/subscriptions.types';

vi.mock('@/lib/services/subscriptions.service', () => ({
  previewAdminSubscriptionPricing: vi.fn(),
  previewNewAdminSubscription: vi.fn(),
  updateAdminSubscriptionPricing: vi.fn(),
  createAdminSubscription: vi.fn(),
}));

const subscription: MerchantSubscription = {
  id: 'sub-1', merchant_id: 'm-1', merchant_email: 'ali@example.test', lifecycle: 'active',
  pricing_phase: 'standard', monthly_price_tnd: '50.000', currency: 'TND',
  started_at: '2026-01-01T00:00:00+01:00', current_period_started_at: '2026-10-01T00:00:00+01:00',
  current_period_ends_at: '2026-11-01T00:00:00+01:00', next_phase_change_at: null, created_at: '2026-01-01T00:00:00+01:00',
};

describe('SubscriptionPricingForm', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(previewAdminSubscriptionPricing).mockResolvedValue({
      ...subscription, billing_interval: 'annual', period_price_tnd: '450.000',
      current_period_started_at: '2026-11-01T00:00:00+01:00', current_period_ends_at: '2027-11-01T00:00:00+01:00',
    });
    vi.mocked(updateAdminSubscriptionPricing).mockResolvedValue(subscription);
  });

  it('previews annual monthly discounts, requires a fresh preview, then schedules them', async () => {
    const onSaved = vi.fn();
    render(<SubscriptionPricingForm subscription={subscription} onSaved={onSaved} onCancel={vi.fn()} />);
    expect(screen.getByRole('button', { name: 'Programmer les conditions' })).toBeDisabled();
    fireEvent.change(screen.getByLabelText('Périodicité'), { target: { value: 'annual' } });
    fireEvent.change(screen.getByLabelText('Tarif annuel de base (TND)'), { target: { value: '600' } });
    fireEvent.change(screen.getByLabelText('Conditions tarifaires'), { target: { value: 'discounted' } });
    fireEvent.change(screen.getByLabelText('Du mois — remise 1'), { target: { value: '1' } });
    fireEvent.change(screen.getByLabelText('Au mois — remise 1'), { target: { value: '3' } });
    fireEvent.change(screen.getByLabelText('Valeur — remise 1'), { target: { value: '100' } });
    fireEvent.change(screen.getByLabelText('Motif de la modification'), { target: { value: 'Lancement' } });
    // happy-dom misreports fractional number steps; submit the form directly.
    fireEvent.submit(screen.getByRole('button', { name: 'Calculer l’aperçu' }).closest('form')!);
    expect(await screen.findByText('450,000 TND')).toBeInTheDocument();
    expect(previewAdminSubscriptionPricing).toHaveBeenCalledWith('sub-1', {
      billing_interval: 'annual', pricing_mode: 'discounted', base_price_tnd: '600', free_months: null,
      discounts: [{ from_month: 1, to_month: 3, type: 'percentage', value: '100' }], reason: 'Lancement',
    });
    fireEvent.change(screen.getByLabelText('Tarif annuel de base (TND)'), { target: { value: '720' } });
    expect(screen.getByRole('button', { name: 'Programmer les conditions' })).toBeDisabled();
    // happy-dom misreports fractional number steps; submit the form directly.
    fireEvent.submit(screen.getByRole('button', { name: 'Calculer l’aperçu' }).closest('form')!);
    await waitFor(() => expect(screen.getByRole('button', { name: 'Programmer les conditions' })).toBeEnabled());
    fireEvent.click(screen.getByRole('button', { name: 'Programmer les conditions' }));
    await waitFor(() => expect(onSaved).toHaveBeenCalledWith(subscription));
    expect(updateAdminSubscriptionPricing).toHaveBeenCalledWith('sub-1', expect.objectContaining({ base_price_tnd: '720' }));
  });

  it('rejects overlapping month ranges before calling the API', async () => {
    render(<SubscriptionPricingForm subscription={subscription} onSaved={vi.fn()} onCancel={vi.fn()} />);
    fireEvent.change(screen.getByLabelText('Conditions tarifaires'), { target: { value: 'discounted' } });
    fireEvent.change(screen.getByLabelText('Au mois — remise 1'), { target: { value: '3' } });
    fireEvent.click(screen.getByRole('button', { name: 'Ajouter une remise' }));
    fireEvent.change(screen.getByLabelText('Du mois — remise 2'), { target: { value: '2' } });
    fireEvent.change(screen.getByLabelText('Au mois — remise 2'), { target: { value: '4' } });
    fireEvent.change(screen.getByLabelText('Motif de la modification'), { target: { value: 'Test' } });
    // happy-dom misreports fractional number steps; submit the form directly.
    fireEvent.submit(screen.getByRole('button', { name: 'Calculer l’aperçu' }).closest('form')!);
    expect(await screen.findByRole('alert')).toHaveTextContent('se chevauchent');
    expect(previewAdminSubscriptionPricing).not.toHaveBeenCalled();
  });

  it('keeps editing available after a preview failure without allowing a save', async () => {
    vi.mocked(previewAdminSubscriptionPricing).mockRejectedValue(new Error('offline'));
    render(<SubscriptionPricingForm subscription={subscription} onSaved={vi.fn()} onCancel={vi.fn()} />);
    fireEvent.change(screen.getByLabelText('Motif de la modification'), { target: { value: 'Ajustement' } });
    // happy-dom misreports fractional number steps; submit the form directly.
    fireEvent.submit(screen.getByRole('button', { name: 'Calculer l’aperçu' }).closest('form')!);
    expect(await screen.findByRole('alert')).toHaveTextContent('Impossible');
    expect(screen.getByRole('button', { name: 'Programmer les conditions' })).toBeDisabled();
    expect(screen.getByRole('button', { name: 'Calculer l’aperçu' })).toBeEnabled();
  });

  it('creates a temporarily free annual subscription after preview', async () => {
    const onSaved = vi.fn();
    vi.mocked(previewNewAdminSubscription).mockResolvedValue({ ...subscription, period_price_tnd: '450.000', billing_interval: 'annual' });
    vi.mocked(createAdminSubscription).mockResolvedValue(subscription);
    render(<SubscriptionPricingForm merchantId="m-new" onSaved={onSaved} onCancel={vi.fn()} />);
    fireEvent.change(screen.getByLabelText('Périodicité'), { target: { value: 'annual' } });
    fireEvent.change(screen.getByLabelText('Tarif annuel de base (TND)'), { target: { value: '600' } });
    fireEvent.change(screen.getByLabelText('Conditions tarifaires'), { target: { value: 'free' } });
    fireEvent.change(screen.getByLabelText(/Durée de gratuité/), { target: { value: '3' } });
    fireEvent.change(screen.getByLabelText('Motif de la modification'), { target: { value: 'Découverte' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Calculer l’aperçu' }).closest('form')!);
    expect(await screen.findByText('450,000 TND')).toBeInTheDocument();
    fireEvent.click(screen.getByRole('button', { name: 'Créer l’abonnement' }));
    await waitFor(() => expect(onSaved).toHaveBeenCalledWith(subscription));
    expect(createAdminSubscription).toHaveBeenCalledWith({
      merchant_id: 'm-new', billing_interval: 'annual', pricing_mode: 'free', base_price_tnd: '600',
      free_months: 3, discounts: [], reason: 'Découverte',
    });
  });

  it('sends a fixed TND discount per month without converting it to a percentage', async () => {
    render(<SubscriptionPricingForm subscription={subscription} onSaved={vi.fn()} onCancel={vi.fn()} />);
    fireEvent.change(screen.getByLabelText('Conditions tarifaires'), { target: { value: 'discounted' } });
    fireEvent.change(screen.getByLabelText('Type — remise 1'), { target: { value: 'fixed' } });
    fireEvent.change(screen.getByLabelText('Valeur — remise 1'), { target: { value: '15.500' } });
    fireEvent.change(screen.getByLabelText('Motif de la modification'), { target: { value: 'Geste commercial' } });
    fireEvent.submit(screen.getByRole('button', { name: 'Calculer l’aperçu' }).closest('form')!);
    await waitFor(() => expect(previewAdminSubscriptionPricing).toHaveBeenCalledWith('sub-1', expect.objectContaining({
      discounts: [{ from_month: 1, to_month: 1, type: 'fixed', value: '15.500' }],
    })));
  });
});
