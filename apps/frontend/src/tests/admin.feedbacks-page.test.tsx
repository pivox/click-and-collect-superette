import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminFeedbacksPage from '@/app/admin/feedbacks/page';
import {
  getAdminFeedback,
  listAdminFeedbacks,
  markAdminFeedbackRead,
  markAdminFeedbackUnread,
  reopenAdminFeedback,
  resolveAdminFeedback,
} from '@/lib/services/admin/feedbacks.service';
import { listStores } from '@/lib/services/admin/stores.service';
import type { FeedbackEntry } from '@/lib/types/admin/feedbacks.types';
import type { Store } from '@/lib/types/admin/stores.types';

vi.mock('@/lib/services/admin/feedbacks.service', () => ({
  getAdminFeedback: vi.fn(),
  listAdminFeedbacks: vi.fn(),
  markAdminFeedbackRead: vi.fn(),
  markAdminFeedbackUnread: vi.fn(),
  reopenAdminFeedback: vi.fn(),
  resolveAdminFeedback: vi.fn(),
}));

vi.mock('@/lib/services/admin/stores.service', () => ({
  listStores: vi.fn(),
}));

const STORE: Store = {
  id: 'store-1',
  name: 'Supérette El Manar',
  slug: 'superette-el-manar',
  city: 'Tunis',
  is_active: true,
  qr_code_token: 'qr-store-1',
  created_at: '2026-06-01T10:00:00+01:00',
  owner: { id: 'merchant-1', email: 'marchand@example.test' },
  products_count: 12,
  archived_at: null,
};

const FEEDBACK: FeedbackEntry = {
  id: 'feedback-1',
  status: 'unread',
  type: 'bug',
  message: 'Le bouton de validation de la Kadhia ne répond pas sur mobile.',
  messageExcerpt: 'Le bouton de validation...',
  appArea: 'merchant',
  appSubArea: 'orders',
  userRole: 'merchant',
  user: { id: 'user-1', email: 'marchand@example.test', name: 'Marchand Test' },
  shop: { id: STORE.id, name: STORE.name },
  pageUrl: '/merchant/commandes',
  routeName: 'merchant.orders',
  pageTitle: 'Commandes marchand',
  locale: 'fr',
  viewportWidth: 390,
  viewportHeight: 844,
  userAgent: 'Mozilla/5.0 Mobile',
  contactConsent: true,
  adminNote: 'À vérifier sur mobile',
  readAt: null,
  resolvedAt: null,
  createdAt: '2026-06-12T09:30:00+01:00',
  updatedAt: '2026-06-12T09:35:00+01:00',
};

const READ_FEEDBACK: FeedbackEntry = {
  ...FEEDBACK,
  status: 'read',
  readAt: '2026-06-12T10:00:00+01:00',
};

const RESOLVED_FEEDBACK: FeedbackEntry = {
  ...FEEDBACK,
  status: 'resolved',
  readAt: '2026-06-12T10:00:00+01:00',
  resolvedAt: '2026-06-12T11:00:00+01:00',
};

function mockFeedbackList(items: FeedbackEntry[] = [FEEDBACK], total = items.length) {
  vi.mocked(listAdminFeedbacks).mockResolvedValue({
    id: 'admin-feedbacks',
    items,
    page: 1,
    limit: 20,
    total,
  });
}

describe('AdminFeedbacksPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    mockFeedbackList();
    vi.mocked(listStores).mockResolvedValue({
      id: 'admin-stores',
      items: [STORE],
      page: 1,
      limit: 50,
      total: 1,
    });
    vi.mocked(getAdminFeedback).mockResolvedValue(FEEDBACK);
    vi.mocked(markAdminFeedbackRead).mockResolvedValue(READ_FEEDBACK);
    vi.mocked(markAdminFeedbackUnread).mockResolvedValue({ ...FEEDBACK, status: 'unread' });
    vi.mocked(resolveAdminFeedback).mockResolvedValue(RESOLVED_FEEDBACK);
    vi.mocked(reopenAdminFeedback).mockResolvedValue(READ_FEEDBACK);
  });

  it('affiche la liste des Feedbacks avec extrait, statut, rôle, zone, utilisateur et supérette', async () => {
    render(<AdminFeedbacksPage />);

    expect(await screen.findByText('Le bouton de validation...')).toBeInTheDocument();
    expect(screen.getByText('Non lu')).toBeInTheDocument();
    expect(screen.getAllByText('Marchand').length).toBeGreaterThan(0);
    expect(screen.getAllByText('Supérette El Manar')[0]).toBeInTheDocument();
    expect(screen.getByText('marchand@example.test')).toBeInTheDocument();
  });

  it('envoie les filtres statut, rôle, type, zone, période, supérette et contact à listAdminFeedbacks', async () => {
    render(<AdminFeedbacksPage />);

    await screen.findByText('Le bouton de validation...');

    fireEvent.change(screen.getByLabelText('Filtrer par statut'), { target: { value: 'read' } });
    fireEvent.change(screen.getByLabelText('Filtrer par rôle'), { target: { value: 'merchant' } });
    fireEvent.change(screen.getByLabelText('Filtrer par type'), { target: { value: 'bug' } });
    fireEvent.change(screen.getByLabelText('Filtrer par zone'), { target: { value: 'merchant' } });
    fireEvent.change(screen.getByLabelText('Filtrer à partir du'), { target: { value: '2026-06-01' } });
    fireEvent.change(screen.getByLabelText("Filtrer jusqu'au"), { target: { value: '2026-06-12' } });
    fireEvent.change(screen.getByLabelText('Choisir une supérette'), { target: { value: STORE.id } });
    fireEvent.change(screen.getByLabelText('Filtrer par consentement contact'), {
      target: { value: 'true' },
    });

    await waitFor(() => {
      expect(listAdminFeedbacks).toHaveBeenLastCalledWith(
        expect.objectContaining({
          page: 1,
          limit: 20,
          status: 'read',
          userRole: 'merchant',
          type: 'bug',
          appArea: 'merchant',
          createdFrom: '2026-06-01',
          createdTo: '2026-06-12',
          shop: STORE.id,
          contactConsent: true,
        }),
      );
    });
  });

  it('permet de filtrer par ID supérette absent de la liste chargée', async () => {
    render(<AdminFeedbacksPage />);

    await screen.findByText('Le bouton de validation...');
    await waitFor(() => expect(listStores).toHaveBeenCalledWith({ page: 1, limit: 50, isActive: true }));

    fireEvent.change(screen.getByRole('textbox', { name: 'Filtrer par supérette' }), {
      target: { value: 'archived-store-99' },
    });

    await waitFor(() => {
      expect(listAdminFeedbacks).toHaveBeenLastCalledWith(
        expect.objectContaining({
          page: 1,
          shop: 'archived-store-99',
        }),
      );
    });
  });

  it('remet la pagination à 1 lors d’un changement de filtre', async () => {
    mockFeedbackList([FEEDBACK], 21);
    render(<AdminFeedbacksPage />);

    await screen.findByText('Le bouton de validation...');

    fireEvent.click(screen.getByRole('button', { name: 'Suivant →' }));

    await waitFor(() => {
      expect(listAdminFeedbacks).toHaveBeenLastCalledWith(expect.objectContaining({ page: 2 }));
    });

    fireEvent.change(screen.getByLabelText('Filtrer par statut'), { target: { value: 'resolved' } });

    await waitFor(() => {
      expect(listAdminFeedbacks).toHaveBeenLastCalledWith(
        expect.objectContaining({ page: 1, status: 'resolved' }),
      );
    });
  });

  it('ouvre le détail via getAdminFeedback et rend les informations complètes', async () => {
    render(<AdminFeedbacksPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Voir détail' }));

    await waitFor(() => expect(getAdminFeedback).toHaveBeenCalledWith('feedback-1'));
    const dialog = await screen.findByRole('dialog', { name: 'Détail feedback' });
    expect(within(dialog).getByText('Message complet')).toBeInTheDocument();
    expect(within(dialog).getByText(FEEDBACK.message ?? '')).toBeInTheDocument();
    expect(within(dialog).getByText('Contexte page et appareil')).toBeInTheDocument();
    expect(within(dialog).getByText('Utilisateur')).toBeInTheDocument();
    expect(within(dialog).getByText('Supérette')).toBeInTheDocument();
    expect(within(dialog).getByText('Consentement contact')).toBeInTheDocument();
    expect(within(dialog).getByText('Note admin')).toBeInTheDocument();
    expect(within(dialog).getByText('Lu le')).toBeInTheDocument();
    expect(within(dialog).getByText('Résolu le')).toBeInTheDocument();
    expect(within(dialog).getByText('Créé le')).toBeInTheDocument();
  });

  it('exécute les actions admin avec succès visible et rafraîchissement liste', async () => {
    render(<AdminFeedbacksPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Voir détail' }));
    const dialog = await screen.findByRole('dialog', { name: 'Détail feedback' });

    fireEvent.click(within(dialog).getByRole('button', { name: 'Lu' }));

    expect(await within(dialog).findByText('Retour marqué comme lu.')).toBeInTheDocument();
    await waitFor(() => expect(markAdminFeedbackRead).toHaveBeenCalledWith('feedback-1'));
    await waitFor(() => expect(listAdminFeedbacks).toHaveBeenCalledTimes(2));

    fireEvent.click(within(dialog).getByRole('button', { name: /Non lu/i }));

    expect(await screen.findByText('Retour marqué comme non lu.')).toBeInTheDocument();
    await waitFor(() => expect(markAdminFeedbackUnread).toHaveBeenCalledWith('feedback-1'));

    fireEvent.change(within(dialog).getByLabelText('Note admin'), {
      target: { value: 'Traité par support' },
    });
    fireEvent.click(within(dialog).getByRole('button', { name: /Résolu/i }));

    expect(await screen.findByText('Retour résolu.')).toBeInTheDocument();
    await waitFor(() => {
      expect(resolveAdminFeedback).toHaveBeenCalledWith('feedback-1', 'Traité par support');
    });

    fireEvent.click(within(dialog).getByRole('button', { name: /Réouvrir/i }));

    expect(await screen.findByText('Retour réouvert.')).toBeInTheDocument();
    await waitFor(() => expect(reopenAdminFeedback).toHaveBeenCalledWith('feedback-1'));
  });

  it('ne propose que la réouverture sur un feedback déjà résolu', async () => {
    vi.mocked(getAdminFeedback).mockResolvedValue(RESOLVED_FEEDBACK);

    render(<AdminFeedbacksPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Voir détail' }));

    const dialog = await screen.findByRole('dialog', { name: 'Détail feedback' });
    expect(within(dialog).getByRole('button', { name: /Réouvrir/i })).toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: 'Lu' })).not.toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: /Non lu/i })).not.toBeInTheDocument();
    expect(within(dialog).queryByRole('button', { name: /Résolu/i })).not.toBeInTheDocument();
  });

  it('affiche les états vide, erreur liste et erreur action', async () => {
    vi.mocked(listAdminFeedbacks).mockResolvedValueOnce({
      id: 'admin-feedbacks',
      items: [],
      page: 1,
      limit: 20,
      total: 0,
    });
    const { unmount } = render(<AdminFeedbacksPage />);
    expect(await screen.findByText('Aucun feedback à afficher.')).toBeInTheDocument();
    unmount();

    vi.clearAllMocks();
    vi.mocked(listStores).mockResolvedValue({ id: 'admin-stores', items: [], page: 1, limit: 50, total: 0 });
    vi.mocked(listAdminFeedbacks).mockRejectedValueOnce(new Error('network'));
    const failedList = render(<AdminFeedbacksPage />);
    expect(await screen.findByText('Impossible de charger les feedbacks.')).toBeInTheDocument();
    failedList.unmount();

    vi.clearAllMocks();
    mockFeedbackList();
    vi.mocked(listStores).mockResolvedValue({ id: 'admin-stores', items: [STORE], page: 1, limit: 50, total: 1 });
    vi.mocked(getAdminFeedback).mockResolvedValue(FEEDBACK);
    vi.mocked(markAdminFeedbackRead).mockRejectedValue(new Error('action down'));
    render(<AdminFeedbacksPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Voir détail' }));
    const dialog = await screen.findByRole('dialog', { name: 'Détail feedback' });
    fireEvent.click(within(dialog).getByRole('button', { name: 'Lu' }));

    expect(await screen.findByText('Impossible de marquer ce retour comme lu.')).toBeInTheDocument();
  });
});
