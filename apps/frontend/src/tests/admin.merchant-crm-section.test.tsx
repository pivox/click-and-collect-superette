import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { MerchantCrmSection } from '@/components/admin/marchands/MerchantCrmSection';
import {
  updateMerchantCrm,
  addMerchantCrmContact,
} from '@/lib/services/admin/merchants.service';
import type { Merchant, MerchantCrm } from '@/lib/types/admin/merchants.types';

vi.mock('@/lib/services/admin/merchants.service', () => ({
  updateMerchantCrm: vi.fn(),
  addMerchantCrmContact: vi.fn(),
}));

const CRM: MerchantCrm = {
  commercial_owner: 'Sami',
  status: 'client',
  last_contact_at: '2026-06-10T11:30:00+00:00',
  next_action_at: null,
  next_action_note: null,
  commercial_note: 'Marchand fidèle.',
  contacts: [
    {
      id: 'contact-1',
      contacted_at: '2026-06-10T11:30:00+00:00',
      channel: 'whatsapp',
      note: 'Relance pour activer les créneaux.',
      author_email: 'admin@example.test',
      created_at: '2026-06-10T11:31:00+00:00',
    },
  ],
};

const MERCHANT_RESPONSE: Merchant = {
  id: 'merchant-1',
  email: 'ali@example.test',
  first_name: 'Ali',
  last_name: 'Ben Salah',
  phone: null,
  is_active: true,
  subscription_lifecycle: null,
  created_at: '2026-06-01T10:00:00+01:00',
  stores_count: 1,
  crm: CRM,
};

describe('MerchantCrmSection', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('affiche les valeurs par défaut prospect sans profil CRM', () => {
    render(<MerchantCrmSection merchantId="merchant-1" initialCrm={null} />);

    expect(screen.getByText('Suivi commercial')).toBeInTheDocument();
    // 'Prospect' apparaît dans le badge et dans l'option du select statut
    expect(screen.getAllByText('Prospect').length).toBeGreaterThanOrEqual(2);
    expect(screen.getByLabelText('Statut')).toHaveValue('prospect');
    expect(screen.getByText('Aucun contact enregistré.')).toBeInTheDocument();
    expect(screen.getByText(/Dernière relance : —/)).toBeInTheDocument();
  });

  it('pré-remplit le formulaire avec le profil CRM existant et affiche l’historique', () => {
    render(<MerchantCrmSection merchantId="merchant-1" initialCrm={CRM} />);

    expect(screen.getByLabelText('Responsable commercial')).toHaveValue('Sami');
    expect(screen.getByLabelText('Statut')).toHaveValue('client');
    expect(screen.getByLabelText('Note commerciale')).toHaveValue('Marchand fidèle.');
    const historyItem = screen.getByRole('listitem');
    expect(within(historyItem).getByText('Relance pour activer les créneaux.')).toBeInTheDocument();
    expect(within(historyItem).getByText('par admin@example.test')).toBeInTheDocument();
    expect(within(historyItem).getByText('WhatsApp')).toBeInTheDocument();
  });

  it('enregistre le suivi commercial avec le bon payload', async () => {
    vi.mocked(updateMerchantCrm).mockResolvedValue(MERCHANT_RESPONSE);

    render(<MerchantCrmSection merchantId="merchant-1" initialCrm={null} />);

    fireEvent.change(screen.getByLabelText('Responsable commercial'), {
      target: { value: 'Sami' },
    });
    fireEvent.change(screen.getByLabelText('Statut'), { target: { value: 'client' } });
    fireEvent.change(screen.getByLabelText('Note commerciale'), {
      target: { value: 'Marchand fidèle.' },
    });
    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le suivi' }));

    await waitFor(() => {
      expect(updateMerchantCrm).toHaveBeenCalledWith('merchant-1', {
        commercial_owner: 'Sami',
        status: 'client',
        next_action_at: null,
        next_action_note: null,
        commercial_note: 'Marchand fidèle.',
      });
    });

    expect(await screen.findByText('Suivi commercial enregistré.')).toBeInTheDocument();
  });

  it('ajoute un contact et met à jour l’historique depuis la réponse', async () => {
    vi.mocked(addMerchantCrmContact).mockResolvedValue(MERCHANT_RESPONSE);

    render(<MerchantCrmSection merchantId="merchant-1" initialCrm={null} />);

    fireEvent.change(screen.getByLabelText('Canal'), { target: { value: 'whatsapp' } });
    fireEvent.change(screen.getByLabelText('Note de contact'), {
      target: { value: 'Relance pour activer les créneaux.' },
    });
    fireEvent.click(screen.getByRole('button', { name: '+ Ajouter' }));

    await waitFor(() => {
      expect(addMerchantCrmContact).toHaveBeenCalledWith('merchant-1', {
        channel: 'whatsapp',
        note: 'Relance pour activer les créneaux.',
      });
    });

    expect(await screen.findByText('Relance pour activer les créneaux.')).toBeInTheDocument();
    expect(screen.getByText('par admin@example.test')).toBeInTheDocument();
  });

  it('notifie le parent après chaque mutation CRM réussie', async () => {
    vi.mocked(updateMerchantCrm).mockResolvedValue(MERCHANT_RESPONSE);
    vi.mocked(addMerchantCrmContact).mockResolvedValue(MERCHANT_RESPONSE);
    const onCrmChanged = vi.fn();

    render(
      <MerchantCrmSection merchantId="merchant-1" initialCrm={null} onCrmChanged={onCrmChanged} />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le suivi' }));
    await waitFor(() => {
      expect(onCrmChanged).toHaveBeenCalledTimes(1);
    });

    fireEvent.change(screen.getByLabelText('Note de contact'), {
      target: { value: 'Relance.' },
    });
    fireEvent.click(screen.getByRole('button', { name: '+ Ajouter' }));
    await waitFor(() => {
      expect(onCrmChanged).toHaveBeenCalledTimes(2);
    });
  });

  it('ne notifie pas le parent quand la mutation échoue', async () => {
    vi.mocked(updateMerchantCrm).mockRejectedValue(new Error('boom'));
    const onCrmChanged = vi.fn();

    render(
      <MerchantCrmSection merchantId="merchant-1" initialCrm={null} onCrmChanged={onCrmChanged} />,
    );

    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le suivi' }));

    expect(
      await screen.findByText('Impossible d’enregistrer le suivi commercial.'),
    ).toBeInTheDocument();
    expect(onCrmChanged).not.toHaveBeenCalled();
  });

  it('refuse un contact sans note', async () => {
    render(<MerchantCrmSection merchantId="merchant-1" initialCrm={null} />);

    fireEvent.click(screen.getByRole('button', { name: '+ Ajouter' }));

    expect(await screen.findByText('La note de contact est obligatoire.')).toBeInTheDocument();
    expect(addMerchantCrmContact).not.toHaveBeenCalled();
  });

  it('affiche une erreur quand l’enregistrement échoue', async () => {
    vi.mocked(updateMerchantCrm).mockRejectedValue(new Error('boom'));

    render(<MerchantCrmSection merchantId="merchant-1" initialCrm={null} />);

    fireEvent.click(screen.getByRole('button', { name: 'Enregistrer le suivi' }));

    expect(
      await screen.findByText('Impossible d’enregistrer le suivi commercial.'),
    ).toBeInTheDocument();
  });
});
