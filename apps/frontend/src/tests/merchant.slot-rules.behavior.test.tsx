import { act, fireEvent, render, screen, waitFor } from '@testing-library/react';
import { expect, it, vi } from 'vitest';
import { RuleAccordion } from '@/components/merchant/creneaux/RuleAccordion';
import { RuleForm } from '@/components/merchant/creneaux/RuleForm';

const rule = { id: 'r1', weekday: 1, start_time: '09:00', end_time: '12:00', capacity: 5, is_active: true };
const result = { store_id: 's1', generated_count: 0, skipped_existing_count: 8, skipped_closure_count: 2, horizon_start: '2028-01-31T00:00:00+01:00', horizon_end: '2028-02-29T00:00:00+01:00' };

it('keeps multi-day failures visible and retries only the failed weekdays', async () => {
  const create = vi.fn().mockImplementation(async ({ weekday }: { weekday: number }) => {
    if (weekday === 2 && create.mock.calls.length < 8) throw new Error('duplicate');
  });
  render(<RuleAccordion rules={[]} onCreateRule={create} onDeleteRule={vi.fn()} onGenerate={vi.fn()} />);
  fireEvent.click(screen.getByRole('button', { name: 'Nouvelle règle' }));
  fireEvent.click(screen.getByRole('button', { name: 'Ajouter 7 règles' }));
  expect(await screen.findByRole('alert')).toHaveTextContent('Mar');
  expect(create).toHaveBeenCalledTimes(7);
  fireEvent.click(screen.getByRole('button', { name: 'Ajouter la règle' }));
  await waitFor(() => expect(create).toHaveBeenCalledTimes(8));
  expect(create.mock.calls[7][0].weekday).toBe(2);
  await waitFor(() => expect(screen.queryByLabelText('Heure début')).not.toBeInTheDocument());
});

it('serializes simultaneous form submits and disables editing during the batch', async () => {
  let resolve!: () => void;
  const create = vi.fn().mockImplementationOnce(() => new Promise<void>((done) => { resolve = done; })).mockResolvedValue(undefined);
  render(<RuleForm onSubmit={create} onCancel={vi.fn()} />);
  const form = screen.getByRole('button', { name: 'Ajouter 7 règles' }).closest('form')!;
  act(() => { fireEvent.submit(form); fireEvent.submit(form); });
  expect(create).toHaveBeenCalledTimes(1);
  expect(screen.getByLabelText('Heure début')).toBeDisabled();
  expect(screen.getByRole('button', { name: 'Annuler' })).toBeDisabled();
  await act(async () => resolve());
  await waitFor(() => expect(create).toHaveBeenCalledTimes(7));
});

it('does not generate when every rule is inactive', () => {
  const generate = vi.fn();
  render(<RuleAccordion rules={[{ ...rule, is_active: false }]} onCreateRule={vi.fn()} onDeleteRule={vi.fn()} onGenerate={generate} />);
  fireEvent.click(screen.getByRole('button', { name: 'Règles récurrentes' }));
  expect(screen.getByRole('button', { name: 'Générer 1 mois' })).toBeDisabled();
  expect(screen.getByRole('button', { name: 'Générer 3 mois' })).toBeDisabled();
});

it('locks both generation horizons synchronously and shows all actual server counters and dates', async () => {
  let resolve!: (value: typeof result) => void;
  const generate = vi.fn(() => new Promise<typeof result>((done) => { resolve = done; }));
  render(<RuleAccordion rules={[rule]} onCreateRule={vi.fn()} onDeleteRule={vi.fn()} onGenerate={generate} />);
  fireEvent.click(screen.getByRole('button', { name: 'Règles récurrentes' }));
  const one = screen.getByRole('button', { name: 'Générer 1 mois' });
  const three = screen.getByRole('button', { name: 'Générer 3 mois' });
  act(() => { fireEvent.click(one); fireEvent.click(three); });
  expect(generate).toHaveBeenCalledTimes(1);
  await act(async () => resolve(result));
  const status = screen.getByRole('status');
  expect(status).toHaveTextContent('8 existants');
  expect(status).toHaveTextContent('2 fermetures');
  expect(status).toHaveTextContent('31/01/2028');
  expect(status).toHaveTextContent('29/02/2028 exclu');
  expect(status).toHaveTextContent('Aucun nouveau créneau');
});

it('explains that DELETE deactivates future generations and preserves appointments', () => {
  render(<RuleAccordion rules={[rule]} onCreateRule={vi.fn()} onDeleteRule={vi.fn()} onGenerate={vi.fn()} />);
  fireEvent.click(screen.getByRole('button', { name: 'Règles récurrentes' }));
  fireEvent.click(screen.getByLabelText(/Désactiver la règle/));
  expect(screen.getByText(/rendez-vous existants sont conservés/)).toBeInTheDocument();
});
