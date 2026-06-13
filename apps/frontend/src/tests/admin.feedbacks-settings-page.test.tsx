import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminFeedbackSettingsPage from '@/app/admin/feedbacks/settings/page';
import {
  getAdminFeedbackSettings,
  updateAdminFeedbackSettings,
} from '@/lib/services/admin/feedbacks.service';
import type { AdminFeedbackSettings } from '@/lib/types/admin/feedbacks.types';

vi.mock('@/lib/services/admin/feedbacks.service', () => ({
  getAdminFeedbackSettings: vi.fn(),
  updateAdminFeedbackSettings: vi.fn(),
}));

const SETTINGS: AdminFeedbackSettings = {
  id: 'feedback-settings',
  globalEnabled: false,
  clientEnabled: true,
  merchantEnabled: false,
  adminEnabled: true,
  clientAreaEnabled: true,
  merchantAreaEnabled: false,
  adminAreaEnabled: true,
  requireAuthenticatedUser: true,
  updatedAt: '2026-06-12T10:00:00+01:00',
};

function deferredSettings() {
  let resolve!: (value: AdminFeedbackSettings) => void;
  const promise = new Promise<AdminFeedbackSettings>((next) => {
    resolve = next;
  });

  return { promise, resolve };
}

describe('AdminFeedbackSettingsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(getAdminFeedbackSettings).mockResolvedValue(SETTINGS);
    vi.mocked(updateAdminFeedbackSettings).mockResolvedValue({
      ...SETTINGS,
      globalEnabled: true,
      merchantEnabled: true,
      merchantAreaEnabled: true,
    });
  });

  it('charge et affiche les paramètres Feedback avec libellés FR', async () => {
    const deferred = deferredSettings();
    vi.mocked(getAdminFeedbackSettings).mockReturnValueOnce(deferred.promise);

    render(<AdminFeedbackSettingsPage />);

    expect(screen.getByText('Chargement des paramètres Feedback...')).toBeInTheDocument();
    deferred.resolve(SETTINGS);
    expect(await screen.findByRole('heading', { name: 'Paramètres Feedback' })).toBeInTheDocument();
    expect(screen.getByText('Activation globale')).toBeInTheDocument();
    expect(screen.getByText('Rôles')).toBeInTheDocument();
    expect(screen.getByText('Zones')).toBeInTheDocument();
    expect(screen.getByText('Utilisateurs connectés uniquement')).toBeInTheDocument();
    expect(screen.getByText('Verrouillé à oui pour le MVP.')).toBeInTheDocument();
    expect(screen.getByLabelText('Utilisateurs connectés uniquement')).toBeDisabled();
  });

  it('toggle activation globale, rôles et zones puis sauvegarde requireAuthenticatedUser à true', async () => {
    render(<AdminFeedbackSettingsPage />);

    await screen.findByRole('heading', { name: 'Paramètres Feedback' });

    fireEvent.click(screen.getByLabelText('Activation globale'));
    fireEvent.click(screen.getByLabelText('Marchand'));
    fireEvent.click(screen.getByLabelText('Zone marchand'));
    fireEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    await waitFor(() => {
      expect(updateAdminFeedbackSettings).toHaveBeenCalledWith({
        globalEnabled: true,
        clientEnabled: true,
        merchantEnabled: true,
        adminEnabled: true,
        clientAreaEnabled: true,
        merchantAreaEnabled: true,
        adminAreaEnabled: true,
        requireAuthenticatedUser: true,
      });
    });
    expect(await screen.findByText('Paramètres Feedback enregistrés.')).toBeInTheDocument();
  });

  it('affiche les états erreur de chargement et succès sauvegarde', async () => {
    vi.mocked(getAdminFeedbackSettings).mockRejectedValueOnce(new Error('network'));

    const { unmount } = render(<AdminFeedbackSettingsPage />);

    expect(await screen.findByText('Impossible de charger les paramètres Feedback.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Enregistrer/i })).toBeDisabled();
    expect(screen.getByLabelText('Activation globale')).toBeDisabled();
    unmount();

    vi.mocked(getAdminFeedbackSettings).mockResolvedValue(SETTINGS);
    vi.mocked(updateAdminFeedbackSettings).mockResolvedValue(SETTINGS);
    render(<AdminFeedbackSettingsPage />);

    await screen.findByRole('heading', { name: 'Paramètres Feedback' });
    fireEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    expect(await screen.findByText('Paramètres Feedback enregistrés.')).toBeInTheDocument();
  });
});
