import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminAiBudgetPolicyPage from '@/app/admin/parametres-ia/page';
import {
  getAdminAiBudgetPolicy,
  updateAdminAiBudgetPolicy,
} from '@/lib/services/admin/aiBudgetPolicy.service';
import type { AdminAiBudgetPolicy } from '@/lib/types/admin/aiBudgetPolicy.types';

vi.mock('@/lib/services/admin/aiBudgetPolicy.service', () => ({
  getAdminAiBudgetPolicy: vi.fn(),
  updateAdminAiBudgetPolicy: vi.fn(),
}));

const POLICY: AdminAiBudgetPolicy = {
  enabled: false,
  openai_enabled: false,
  gemini_enabled: false,
  mistral_enabled: false,
  qwen_enabled: false,
  currency: 'USD',
  global_period_days: 30,
  alert_threshold_percent: 80,
  max_cost_per_call_amount: null,
  max_cost_per_photo_amount: null,
  max_cost_per_shop_amount: null,
  global_period_cap_amount: null,
  max_output_tokens_per_call: null,
  max_crops_per_photo: null,
  max_attempts_per_photo: null,
  max_call_duration_seconds: null,
  max_concurrent_calls: null,
  updated_at: '2026-10-06T18:00:00+01:00',
};

function deferredPolicy() {
  let resolve!: (value: AdminAiBudgetPolicy) => void;
  const promise = new Promise<AdminAiBudgetPolicy>((next) => {
    resolve = next;
  });

  return { promise, resolve };
}

describe('AdminAiBudgetPolicyPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(getAdminAiBudgetPolicy).mockResolvedValue(POLICY);
    vi.mocked(updateAdminAiBudgetPolicy).mockResolvedValue({
      ...POLICY,
      enabled: true,
      openai_enabled: true,
      max_cost_per_call_amount: '0.05',
    });
  });

  it('charge et affiche les paramètres IA désactivés par défaut', async () => {
    const deferred = deferredPolicy();
    vi.mocked(getAdminAiBudgetPolicy).mockReturnValueOnce(deferred.promise);

    render(<AdminAiBudgetPolicyPage />);

    expect(screen.getByText('Chargement des paramètres IA...')).toBeInTheDocument();
    deferred.resolve(POLICY);
    expect(await screen.findByRole('heading', { name: 'Paramètres IA' })).toBeInTheDocument();
    expect(screen.getByLabelText('Autoriser les appels IA payants')).not.toBeChecked();
    expect(screen.getByLabelText('OpenAI')).not.toBeChecked();
    expect(screen.getByLabelText('Plafond par appel')).toHaveValue('');
  });

  it('active le coupe-circuit, configure un plafond et sauvegarde', async () => {
    render(<AdminAiBudgetPolicyPage />);

    await screen.findByRole('heading', { name: 'Paramètres IA' });

    fireEvent.click(screen.getByLabelText('Autoriser les appels IA payants'));
    fireEvent.click(screen.getByLabelText('OpenAI'));
    fireEvent.change(screen.getByLabelText('Plafond par appel'), { target: { value: '0.05' } });
    fireEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    await waitFor(() => {
      expect(updateAdminAiBudgetPolicy).toHaveBeenCalledWith(
        expect.objectContaining({
          enabled: true,
          openai_enabled: true,
          max_cost_per_call_amount: '0.05',
        }),
      );
    });
    expect(await screen.findByText('Paramètres IA enregistrés.')).toBeInTheDocument();
  });

  it('affiche les états erreur de chargement et succès sauvegarde', async () => {
    vi.mocked(getAdminAiBudgetPolicy).mockRejectedValueOnce(new Error('network'));

    const { unmount } = render(<AdminAiBudgetPolicyPage />);

    expect(await screen.findByText('Impossible de charger les paramètres IA.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Enregistrer/i })).toBeDisabled();
    unmount();

    vi.mocked(getAdminAiBudgetPolicy).mockResolvedValue(POLICY);
    vi.mocked(updateAdminAiBudgetPolicy).mockResolvedValue(POLICY);
    render(<AdminAiBudgetPolicyPage />);

    await screen.findByRole('heading', { name: 'Paramètres IA' });
    fireEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    expect(await screen.findByText('Paramètres IA enregistrés.')).toBeInTheDocument();
  });
});
