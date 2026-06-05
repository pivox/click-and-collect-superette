import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminOpsMessengerPage from '@/app/admin/ops/messenger/page';
import { getAdminMessengerMonitoring } from '@/lib/services/admin/ops.service';
import type { AdminMessengerMonitoring } from '@/lib/types/admin/ops.types';

vi.mock('@/lib/services/admin/ops.service', () => ({
  getAdminMessengerMonitoring: vi.fn(),
}));

const HEALTHY: AdminMessengerMonitoring = {
  status: 'ok',
  pending: 0,
  failed: 0,
  oldest_age_s: null,
  last_consumed_at: '2026-06-05T18:40:00+01:00',
  thresholds: {
    pending: 100,
    oldest_age_s: 900,
  },
  checked_at: '2026-06-05T18:45:00+01:00',
};

const DEGRADED: AdminMessengerMonitoring = {
  status: 'degraded',
  pending: 4,
  failed: 2,
  oldest_age_s: 1260,
  last_consumed_at: null,
  thresholds: {
    pending: 100,
    oldest_age_s: 900,
  },
  checked_at: '2026-06-05T18:45:00+01:00',
};

describe('AdminOpsMessengerPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(getAdminMessengerMonitoring).mockResolvedValue(HEALTHY);
  });

  it('affiche une santé Messenger OK avec les compteurs et seuils', async () => {
    render(<AdminOpsMessengerPage />);

    expect(await screen.findByRole('heading', { name: 'Santé plateforme' })).toBeInTheDocument();
    expect(screen.getByText('Messenger OK')).toBeInTheDocument();
    expect(screen.getByText('File en attente')).toBeInTheDocument();
    expect(screen.getByText('Messages en échec')).toBeInTheDocument();
    expect(screen.getAllByText('0')).toHaveLength(2);
    expect(screen.getByText('Aucun message en attente')).toBeInTheDocument();
    expect(screen.getByText('Dernière activité worker')).toBeInTheDocument();
    expect(screen.getByText('Seuil attente')).toBeInTheDocument();
    expect(screen.getByText('100 messages')).toBeInTheDocument();
    expect(screen.getByText('Seuil ancienneté')).toBeInTheDocument();
    expect(screen.getByText('15 min')).toBeInTheDocument();
  });

  it('rend clairement un état dégradé et les actions de diagnostic', async () => {
    vi.mocked(getAdminMessengerMonitoring).mockResolvedValue(DEGRADED);

    render(<AdminOpsMessengerPage />);

    expect(await screen.findByText('Messenger dégradé')).toBeInTheDocument();
    expect(screen.getByText('File en attente')).toBeInTheDocument();
    expect(screen.getByText('4')).toBeInTheDocument();
    expect(screen.getByText('Messages en échec')).toBeInTheDocument();
    expect(screen.getByText('2')).toBeInTheDocument();
    expect(screen.getByText('21 min')).toBeInTheDocument();
    expect(screen.getByText('Inspecter les messages en échec avec le runbook worker Messenger.')).toBeInTheDocument();
    expect(screen.getByText('Vérifier que le worker consomme la file async.')).toBeInTheDocument();
  });

  it('gère une erreur API puis recharge au retry', async () => {
    vi.mocked(getAdminMessengerMonitoring)
      .mockRejectedValueOnce(new Error('api down'))
      .mockResolvedValueOnce(HEALTHY);

    render(<AdminOpsMessengerPage />);

    expect(await screen.findByText('Impossible de charger la santé plateforme.')).toBeInTheDocument();

    fireEvent.click(screen.getByRole('button', { name: 'Réessayer' }));

    await waitFor(() => expect(getAdminMessengerMonitoring).toHaveBeenCalledTimes(2));
    const status = await screen.findByText('Messenger OK');
    expect(within(status.closest('section') ?? document.body).getByText('Messenger OK')).toBeInTheDocument();
  });
});
