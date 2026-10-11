import { fireEvent, render, screen, waitFor } from '@testing-library/react';
import { beforeEach, expect, it, vi } from 'vitest';
import { IssueSubscriptionDocument } from '@/components/admin/subscriptions/IssueSubscriptionDocument';
import { issueSubscriptionBillingDocument } from '@/lib/services/subscriptions.service';

vi.mock('@/lib/services/subscriptions.service', () => ({ issueSubscriptionBillingDocument: vi.fn() }));

beforeEach(() => vi.clearAllMocks());

it('emits the period document and links to manual payment management', async () => {
  vi.mocked(issueSubscriptionBillingDocument).mockResolvedValue({ document_number: 'MS-2026-000123' } as Awaited<ReturnType<typeof issueSubscriptionBillingDocument>>);
  render(<IssueSubscriptionDocument subscriptionId="sub-1" amountTnd="375.000" />);
  fireEvent.click(screen.getByRole('button', { name: 'Émettre le document de période' }));
  await waitFor(() => expect(issueSubscriptionBillingDocument).toHaveBeenCalledWith('sub-1'));
  expect(await screen.findByText(/MS-2026-000123/)).toBeInTheDocument();
  expect(screen.getByRole('link', { name: 'Enregistrer le paiement manuel' })).toHaveAttribute('href', '/admin/facturation');
});

it('does not offer to issue a debt for a free period', () => {
  render(<IssueSubscriptionDocument subscriptionId="sub-1" amountTnd="0.000" />);
  expect(screen.getByText(/Aucun paiement à demander/)).toBeInTheDocument();
  expect(screen.queryByRole('button')).not.toBeInTheDocument();
});

it('shows a retryable error when document creation fails', async () => {
  vi.mocked(issueSubscriptionBillingDocument).mockRejectedValue(new Error('offline'));
  render(<IssueSubscriptionDocument subscriptionId="sub-1" amountTnd="375.000" />);
  fireEvent.click(screen.getByRole('button', { name: 'Émettre le document de période' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Impossible');
  expect(screen.getByRole('button', { name: 'Émettre le document de période' })).toBeEnabled();
});
