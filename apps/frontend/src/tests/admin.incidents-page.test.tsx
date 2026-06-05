import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import AdminIncidentsPage from '@/app/admin/incidents/page';
import {
  addAdminIncidentNote,
  closeAdminIncident,
  getAdminIncident,
  listAdminIncidents,
  startAdminIncidentProcessing,
} from '@/lib/services/admin/incidents.service';
import type { Incident } from '@/lib/types/admin/incidents.types';

vi.mock('@/lib/services/admin/incidents.service', () => ({
  listAdminIncidents: vi.fn(),
  getAdminIncident: vi.fn(),
  addAdminIncidentNote: vi.fn(),
  startAdminIncidentProcessing: vi.fn(),
  closeAdminIncident: vi.fn(),
}));

const INCIDENT: Incident = {
  id: 'incident-1',
  order_id: 'order-1',
  merchant_id: 'merchant-1',
  merchant_email: 'ali@example.test',
  customer_id: 'customer-1',
  customer_email: 'client@example.test',
  type: 'missing_product',
  status: 'open',
  occurred_at: '2026-06-05T12:30:00+01:00',
  created_at: '2026-06-05T13:30:00+01:00',
  updated_at: '2026-06-05T13:30:00+01:00',
  closed_at: null,
  notes: [],
  history: [
    {
      event: 'incident.opened',
      occurred_at: '2026-06-05T13:30:00+01:00',
      summary: 'Incident créé.',
    },
  ],
};

describe('AdminIncidentsPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    vi.mocked(listAdminIncidents).mockResolvedValue({
      id: 'admin-incidents',
      items: [INCIDENT],
      page: 1,
      limit: 20,
      total: 1,
    });
    vi.mocked(getAdminIncident).mockResolvedValue(INCIDENT);
    vi.mocked(addAdminIncidentNote).mockResolvedValue({
      ...INCIDENT,
      notes: [
        {
          id: 'note-1',
          note: 'Client appelé',
          created_by_admin_id: 'admin-1',
          created_by_admin_email: 'support@example.test',
          created_at: '2026-06-05T14:00:00+01:00',
        },
      ],
      history: [
        ...(INCIDENT.history ?? []),
        {
          event: 'incident.note_added',
          occurred_at: '2026-06-05T14:00:00+01:00',
          summary: 'Note interne ajoutée.',
        },
      ],
    });
    vi.mocked(startAdminIncidentProcessing).mockResolvedValue({
      ...INCIDENT,
      status: 'in_progress',
      updated_at: '2026-06-05T14:10:00+01:00',
      history: [
        ...(INCIDENT.history ?? []),
        {
          event: 'incident.processing_started',
          occurred_at: '2026-06-05T14:10:00+01:00',
          summary: 'Incident pris en charge.',
        },
      ],
    });
    vi.mocked(closeAdminIncident).mockResolvedValue({
      ...INCIDENT,
      status: 'closed',
      closed_at: '2026-06-05T15:00:00+01:00',
      updated_at: '2026-06-05T15:00:00+01:00',
      history: [
        ...(INCIDENT.history ?? []),
        {
          event: 'incident.closed',
          occurred_at: '2026-06-05T15:00:00+01:00',
          summary: 'Incident clôturé.',
        },
      ],
    });
  });

  it('renders support incidents with merchant, client and status labels', async () => {
    render(<AdminIncidentsPage />);

    expect(await screen.findByText('Produit manquant')).toBeInTheDocument();
    expect(screen.getByText('ali@example.test')).toBeInTheDocument();
    expect(screen.getByText('client@example.test')).toBeInTheDocument();
    expect(screen.getByText('Ouvert')).toBeInTheDocument();
  });

  it('filters incidents by status through the admin API', async () => {
    render(<AdminIncidentsPage />);

    fireEvent.change(await screen.findByLabelText('Filtrer par statut'), {
      target: { value: 'in_progress' },
    });

    await waitFor(() => {
      expect(listAdminIncidents).toHaveBeenLastCalledWith({
        page: 1,
        limit: 20,
        merchant: '',
        client: '',
        status: 'in_progress',
      });
    });
  });

  it('resets to the first page before applying a status filter', async () => {
    vi.mocked(listAdminIncidents).mockResolvedValue({
      id: 'admin-incidents',
      items: [INCIDENT],
      page: 1,
      limit: 20,
      total: 40,
    });

    render(<AdminIncidentsPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Suivant →' }));
    await waitFor(() => {
      expect(listAdminIncidents).toHaveBeenCalledWith({
        page: 2,
        limit: 20,
        merchant: '',
        client: '',
        status: '',
      });
    });

    fireEvent.change(screen.getByLabelText('Filtrer par statut'), {
      target: { value: 'closed' },
    });

    await waitFor(() => {
      expect(listAdminIncidents).toHaveBeenLastCalledWith({
        page: 1,
        limit: 20,
        merchant: '',
        client: '',
        status: 'closed',
      });
    });
    expect(vi.mocked(listAdminIncidents).mock.calls).not.toContainEqual([
      {
        page: 2,
        limit: 20,
        merchant: '',
        client: '',
        status: 'closed',
      },
    ]);
  });

  it('opens incident detail, adds an internal note and updates the status', async () => {
    render(<AdminIncidentsPage />);

    fireEvent.click(await screen.findByRole('button', { name: 'Voir le détail incident' }));

    await waitFor(() => expect(getAdminIncident).toHaveBeenCalledWith('incident-1'));
    const detail = await screen.findByRole('dialog', { name: 'Détail incident' });

    fireEvent.change(within(detail).getByRole('textbox'), {
      target: { value: 'Client appelé' },
    });
    fireEvent.click(within(detail).getByRole('button', { name: 'Ajouter la note' }));

    await waitFor(() => expect(addAdminIncidentNote).toHaveBeenCalledWith('incident-1', 'Client appelé'));
    expect(await within(detail).findByText('Client appelé')).toBeInTheDocument();

    fireEvent.click(within(detail).getByRole('button', { name: 'Prendre en charge' }));
    await waitFor(() => expect(startAdminIncidentProcessing).toHaveBeenCalledWith('incident-1'));
    expect(await within(detail).findByText('En cours')).toBeInTheDocument();

    fireEvent.click(within(detail).getByRole('button', { name: 'Clôturer' }));
    await waitFor(() => expect(closeAdminIncident).toHaveBeenCalledWith('incident-1'));
    expect(await within(detail).findByText('Clôturé')).toBeInTheDocument();
  });
});
