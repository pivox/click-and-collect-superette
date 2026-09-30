import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminPlatformSettingsPage from '@/app/admin/parametres/page';
import {
  getAdminPlatformSettings,
  updateAdminPlatformSettings,
} from '@/lib/services/admin/platform-settings.service';

vi.mock('@/lib/services/admin/platform-settings.service', () => ({
  getAdminPlatformSettings: vi.fn(),
  updateAdminPlatformSettings: vi.fn(),
}));

const SETTINGS = {
  id: 'platform-settings',
  frontendOrigin: 'http://localhost:3000',
  updatedAt: '2026-06-23T13:00:00+01:00',
};

describe('AdminPlatformSettingsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(getAdminPlatformSettings).mockResolvedValue(SETTINGS);
    vi.mocked(updateAdminPlatformSettings).mockResolvedValue({
      ...SETTINGS,
      frontendOrigin: 'https://demo.kadhia.tn',
      updatedAt: '2026-06-23T13:01:00+01:00',
    });
  });

  it('charge et affiche l’origine frontend utilisée par les QR et liens Kadhia', async () => {
    render(<AdminPlatformSettingsPage />);

    expect(screen.getByText('Chargement des paramètres plateforme...')).toBeInTheDocument();
    expect(await screen.findByRole('heading', { name: 'Paramètres plateforme' })).toBeInTheDocument();
    expect(screen.getByLabelText('Origine frontend')).toHaveValue('http://localhost:3000');
    expect(screen.getByText('Utilisée pour les QR magasin, liens de partage Kadhia et emails frontend.')).toBeInTheDocument();
  });

  it('enregistre une origine frontend normalisée côté backend', async () => {
    render(<AdminPlatformSettingsPage />);

    await screen.findByRole('heading', { name: 'Paramètres plateforme' });
    fireEvent.change(screen.getByLabelText('Origine frontend'), {
      target: { value: 'https://demo.kadhia.tn/' },
    });
    fireEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    await waitFor(() => {
      expect(updateAdminPlatformSettings).toHaveBeenCalledWith({
        frontendOrigin: 'https://demo.kadhia.tn/',
      });
    });
    expect(await screen.findByText('Paramètres plateforme enregistrés.')).toBeInTheDocument();
    expect(screen.getByLabelText('Origine frontend')).toHaveValue('https://demo.kadhia.tn');
  });

  it('affiche les erreurs de chargement et de sauvegarde', async () => {
    vi.mocked(getAdminPlatformSettings).mockRejectedValueOnce(new Error('network'));

    const { unmount } = render(<AdminPlatformSettingsPage />);

    expect(await screen.findByText('Impossible de charger les paramètres plateforme.')).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /Enregistrer/i })).toBeDisabled();
    unmount();

    vi.mocked(getAdminPlatformSettings).mockResolvedValue(SETTINGS);
    vi.mocked(updateAdminPlatformSettings).mockRejectedValueOnce(new Error('invalid'));
    render(<AdminPlatformSettingsPage />);

    await screen.findByRole('heading', { name: 'Paramètres plateforme' });
    fireEvent.click(screen.getByRole('button', { name: /Enregistrer/i }));

    expect(await screen.findByText('Impossible d’enregistrer les paramètres plateforme. Vérifiez que l’URL est une origine absolue autorisée.')).toBeInTheDocument();
  });
});
