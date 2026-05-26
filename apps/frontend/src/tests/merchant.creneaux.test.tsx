import { fireEvent, render, screen, waitFor, within } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { DayStrip } from '@/components/merchant/creneaux/DayStrip';
import { SlotCard } from '@/components/merchant/creneaux/SlotCard';
import { RuleAccordion } from '@/components/merchant/creneaux/RuleAccordion';
import { GenerateBanner } from '@/components/merchant/creneaux/GenerateBanner';
import { ClosureAccordion } from '@/components/merchant/creneaux/ClosureAccordion';
import MerchantCreneauxPage from '@/app/merchant/creneaux/page';
import {
  listMerchantSlotRules,
  createMerchantSlotRule,
} from '@/lib/services/merchant-slot-rules.service';
import {
  listMerchantSlots,
} from '@/lib/services/merchant-slots.service';
import {
  listMerchantClosures,
} from '@/lib/services/merchant-closures.service';
import type {
  MerchantPickupSlot,
  MerchantPickupSlotRule,
  MerchantExceptionalClosure,
} from '@/lib/types/merchant-slots.types';

vi.mock('@/lib/auth/MerchantAuthContext', () => ({
  useMerchantAuth: () => ({
    merchant: { store: { id: 'store-1', name: 'Supérette Test', active: true } },
  }),
}));

vi.mock('@/lib/services/merchant-slot-rules.service', () => ({
  listMerchantSlotRules: vi.fn(),
  createMerchantSlotRule: vi.fn(),
  deleteMerchantSlotRule: vi.fn(),
  generateMerchantSlots: vi.fn(),
}));
vi.mock('@/lib/services/merchant-slots.service', () => ({
  listMerchantSlots: vi.fn(),
  createMerchantSlot: vi.fn(),
  patchMerchantSlot: vi.fn(),
  deleteMerchantSlot: vi.fn(),
}));
vi.mock('@/lib/services/merchant-closures.service', () => ({
  listMerchantClosures: vi.fn(),
  createMerchantClosure: vi.fn(),
  deleteMerchantClosure: vi.fn(),
}));

const today = new Date();
today.setHours(0, 0, 0, 0);

function makeSlot(overrides: Partial<MerchantPickupSlot> = {}): MerchantPickupSlot {
  const d = new Date(today);
  d.setHours(17, 0, 0, 0);
  const e = new Date(today);
  e.setHours(18, 0, 0, 0);
  return {
    id: 'slot-1',
    starts_at: d.toISOString(),
    ends_at: e.toISOString(),
    capacity: 6,
    booked_count: 2,
    is_active: true,
    ...overrides,
  };
}

const rule: MerchantPickupSlotRule = {
  id: 'rule-1',
  weekday: 3,
  start_time: '17:00',
  end_time: '19:00',
  capacity: 6,
  is_active: true,
};

const closure: MerchantExceptionalClosure = {
  id: 'closure-1',
  starts_at: new Date(today.getFullYear(), today.getMonth() + 1, 1).toISOString(),
  ends_at: new Date(today.getFullYear(), today.getMonth() + 1, 1, 23, 59).toISOString(),
  reason: 'Aïd el-Fitr',
  is_active: true,
};

function setupPageMocks() {
  vi.mocked(listMerchantSlotRules).mockResolvedValue({ total: 0, items: [] });
  vi.mocked(listMerchantSlots).mockResolvedValue([]);
  vi.mocked(listMerchantClosures).mockResolvedValue({ total: 0, items: [] });
}

// ─── DayStrip ───────────────────────────────────────────────────────────────

describe('DayStrip', () => {
  const days = [today, new Date(today.getTime() + 86400000)];

  it('renders all days', () => {
    render(
      React.createElement(DayStrip, {
        days,
        selectedDate: today,
        slots: [],
        closures: [],
        onSelectDate: vi.fn(),
      }),
    );
    expect(screen.getAllByRole('listitem')).toHaveLength(2);
  });

  it('marks selected day as pressed', () => {
    render(
      React.createElement(DayStrip, {
        days,
        selectedDate: today,
        slots: [],
        closures: [],
        onSelectDate: vi.fn(),
      }),
    );
    const items = screen.getAllByRole('listitem');
    expect(within(items[0]).getByRole('button')).toHaveAttribute('aria-pressed', 'true');
    expect(within(items[1]).getByRole('button')).toHaveAttribute('aria-pressed', 'false');
  });

  it('shows slot count badge for day with active slots', () => {
    const slot = makeSlot();
    render(
      React.createElement(DayStrip, {
        days,
        selectedDate: today,
        slots: [slot],
        closures: [],
        onSelectDate: vi.fn(),
      }),
    );
    expect(screen.getByLabelText('1 créneau')).toBeInTheDocument();
  });

  it('calls onSelectDate when a day is clicked', () => {
    const onSelect = vi.fn();
    render(
      React.createElement(DayStrip, {
        days,
        selectedDate: today,
        slots: [],
        closures: [],
        onSelectDate: onSelect,
      }),
    );
    fireEvent.click(within(screen.getAllByRole('listitem')[1]).getByRole('button'));
    expect(onSelect).toHaveBeenCalledWith(days[1]);
  });

  it('shows closure indicator for a same-day partial closure (e.g. 14:00–18:00)', () => {
    // Closure starts at 14:00 today — not at midnight, so the old `date >= start` check would miss it
    const partialClosure: MerchantExceptionalClosure = {
      id: 'closure-partial',
      starts_at: new Date(today.getFullYear(), today.getMonth(), today.getDate(), 14, 0).toISOString(),
      ends_at: new Date(today.getFullYear(), today.getMonth(), today.getDate(), 18, 0).toISOString(),
      reason: 'Pause déjeuner',
      is_active: true,
    };
    render(
      React.createElement(DayStrip, {
        days,
        selectedDate: today,
        slots: [],
        closures: [partialClosure],
        onSelectDate: vi.fn(),
      }),
    );
    expect(screen.getByLabelText('Fermeture exceptionnelle')).toBeInTheDocument();
  });
});

// ─── SlotCard ────────────────────────────────────────────────────────────────

describe('SlotCard', () => {
  it('shows time range and reservation count', () => {
    const slot = makeSlot();
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete: vi.fn() }));
    expect(screen.getByText(/2\/6 réservé/)).toBeInTheDocument();
  });

  it('shows Complet badge when fully booked', () => {
    const slot = makeSlot({ capacity: 4, booked_count: 4 });
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete: vi.fn() }));
    expect(screen.getByText('Complet')).toBeInTheDocument();
  });

  it('shows Inactif badge when is_active is false', () => {
    const slot = makeSlot({ is_active: false });
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete: vi.fn() }));
    expect(screen.getByText('Inactif')).toBeInTheDocument();
  });

  it('blocks deletion with error when slot has reservations', async () => {
    const onDelete = vi.fn();
    const slot = makeSlot({ booked_count: 2 });
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete }));

    fireEvent.click(screen.getByLabelText('Supprimer ce créneau'));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('réservations');
    });
    expect(onDelete).not.toHaveBeenCalled();
  });

  it('calls onDelete when slot has no reservations', async () => {
    const onDelete = vi.fn().mockResolvedValue(undefined);
    const slot = makeSlot({ booked_count: 0 });
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete }));

    fireEvent.click(screen.getByLabelText('Supprimer ce créneau'));
    await waitFor(() => expect(onDelete).toHaveBeenCalledWith('slot-1'));
  });

  it('shows error message when onDelete rejects (network failure)', async () => {
    const onDelete = vi.fn().mockRejectedValue(new Error('network'));
    const slot = makeSlot({ booked_count: 0 });
    render(React.createElement(SlotCard, { slot, onPatch: vi.fn(), onDelete }));

    fireEvent.click(screen.getByLabelText('Supprimer ce créneau'));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Impossible de supprimer');
    });
  });
});

// ─── RuleAccordion ───────────────────────────────────────────────────────────

describe('RuleAccordion', () => {
  it('is open by default when no rules exist', () => {
    render(
      React.createElement(RuleAccordion, {
        rules: [],
        onCreateRule: vi.fn(),
        onDeleteRule: vi.fn(),
      }),
    );
    expect(screen.getByText(/aucune règle/i)).toBeInTheDocument();
  });

  it('displays rule with weekday label', () => {
    render(
      React.createElement(RuleAccordion, {
        rules: [rule],
        onCreateRule: vi.fn(),
        onDeleteRule: vi.fn(),
      }),
    );
    fireEvent.click(screen.getByText('Règles récurrentes'));
    expect(screen.getByText(/Mercredi/)).toBeInTheDocument();
    expect(screen.getByText(/17:00–19:00/)).toBeInTheDocument();
  });

  it('shows delete confirmation before deleting', async () => {
    const onDelete = vi.fn().mockResolvedValue(undefined);
    render(
      React.createElement(RuleAccordion, {
        rules: [rule],
        onCreateRule: vi.fn(),
        onDeleteRule: onDelete,
      }),
    );
    fireEvent.click(screen.getByText('Règles récurrentes'));
    fireEvent.click(screen.getByLabelText(/Supprimer la règle/));
    expect(screen.getByText('Supprimer ?')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Oui'));
    await waitFor(() => expect(onDelete).toHaveBeenCalledWith('rule-1'));
  });

  it('shows error message when onDeleteRule rejects', async () => {
    const onDelete = vi.fn().mockRejectedValue(new Error('network'));
    render(
      React.createElement(RuleAccordion, {
        rules: [rule],
        onCreateRule: vi.fn(),
        onDeleteRule: onDelete,
      }),
    );
    fireEvent.click(screen.getByText('Règles récurrentes'));
    fireEvent.click(screen.getByLabelText(/Supprimer la règle/));
    fireEvent.click(screen.getByText('Oui'));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Impossible de supprimer cette règle');
    });
  });
});

// ─── GenerateBanner ──────────────────────────────────────────────────────────

describe('GenerateBanner', () => {
  it('renders the generate prompt', () => {
    render(
      React.createElement(GenerateBanner, {
        onGenerate: vi.fn(),
        onDismiss: vi.fn(),
      }),
    );
    expect(screen.getByText(/générer les créneaux/i)).toBeInTheDocument();
    expect(screen.getByRole('button', { name: /générer/i })).toBeInTheDocument();
  });

  it('calls onDismiss when Plus tard is clicked', () => {
    const onDismiss = vi.fn();
    render(
      React.createElement(GenerateBanner, {
        onGenerate: vi.fn(),
        onDismiss,
      }),
    );
    fireEvent.click(screen.getByRole('button', { name: /plus tard/i }));
    expect(onDismiss).toHaveBeenCalled();
  });

  it('shows success message after generation', async () => {
    const onGenerate = vi.fn().mockResolvedValue({
      store_id: 'store-1',
      generated_count: 12,
      skipped_existing_count: 0,
      skipped_closure_count: 0,
      horizon_start: '2026-05-25',
      horizon_end: '2026-06-22',
    });
    render(
      React.createElement(GenerateBanner, {
        onGenerate,
        onDismiss: vi.fn(),
      }),
    );
    fireEvent.click(screen.getByRole('button', { name: /générer/i }));
    await waitFor(() => {
      expect(screen.getByRole('status')).toHaveTextContent('12 créneaux générés');
    });
  });
});

// ─── ClosureAccordion ────────────────────────────────────────────────────────

describe('ClosureAccordion', () => {
  it('shows no closures message when empty', () => {
    render(
      React.createElement(ClosureAccordion, {
        closures: [],
        onCreateClosure: vi.fn(),
        onDeleteClosure: vi.fn(),
      }),
    );
    fireEvent.click(screen.getByText('Fermetures exceptionnelles'));
    expect(screen.getByText(/aucune fermeture/i)).toBeInTheDocument();
  });

  it('lists closure with reason', () => {
    render(
      React.createElement(ClosureAccordion, {
        closures: [closure],
        onCreateClosure: vi.fn(),
        onDeleteClosure: vi.fn(),
      }),
    );
    fireEvent.click(screen.getByText(/Fermetures exceptionnelles/));
    expect(screen.getByText('Aïd el-Fitr')).toBeInTheDocument();
  });

  it('confirms before deleting a closure', async () => {
    const onDelete = vi.fn().mockResolvedValue(undefined);
    render(
      React.createElement(ClosureAccordion, {
        closures: [closure],
        onCreateClosure: vi.fn(),
        onDeleteClosure: onDelete,
      }),
    );
    fireEvent.click(screen.getByText(/Fermetures exceptionnelles/));
    fireEvent.click(screen.getByLabelText('Supprimer cette fermeture'));
    expect(screen.getByText('Supprimer ?')).toBeInTheDocument();
    fireEvent.click(screen.getByText('Oui'));
    await waitFor(() => expect(onDelete).toHaveBeenCalledWith('closure-1'));
  });

  it('shows error message when onDeleteClosure rejects', async () => {
    const onDelete = vi.fn().mockRejectedValue(new Error('network'));
    render(
      React.createElement(ClosureAccordion, {
        closures: [closure],
        onCreateClosure: vi.fn(),
        onDeleteClosure: onDelete,
      }),
    );
    fireEvent.click(screen.getByText(/Fermetures exceptionnelles/));
    fireEvent.click(screen.getByLabelText('Supprimer cette fermeture'));
    fireEvent.click(screen.getByText('Oui'));
    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Impossible de supprimer cette fermeture');
    });
  });
});

// ─── MerchantCreneauxPage ────────────────────────────────────────────────────

describe('MerchantCreneauxPage', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    setupPageMocks();
  });

  it('renders the page heading', async () => {
    render(React.createElement(MerchantCreneauxPage));
    await waitFor(() => {
      expect(screen.getByRole('heading', { name: 'Créneaux' })).toBeInTheDocument();
    });
  });

  it('shows slots for today filtered from all slots', async () => {
    const todaySlot = makeSlot();
    const tomorrow = new Date(today.getTime() + 86400000);
    tomorrow.setHours(17, 0, 0, 0);
    const tomorrowEnd = new Date(today.getTime() + 86400000);
    tomorrowEnd.setHours(18, 0, 0, 0);
    const tomorrowSlot = makeSlot({
      id: 'slot-2',
      starts_at: tomorrow.toISOString(),
      ends_at: tomorrowEnd.toISOString(),
    });

    vi.mocked(listMerchantSlots).mockResolvedValue([todaySlot, tomorrowSlot]);

    render(React.createElement(MerchantCreneauxPage));

    await waitFor(() => {
      expect(screen.queryAllByText(/17:00/)).toHaveLength(1);
    });
  });

  it('shows error alert when load fails', async () => {
    vi.mocked(listMerchantSlotRules).mockRejectedValue(new Error('network'));

    render(React.createElement(MerchantCreneauxPage));

    await waitFor(() => {
      expect(screen.getByRole('alert')).toHaveTextContent('Impossible de charger');
    });
  });

  it('shows GenerateBanner after creating a rule', async () => {
    vi.mocked(createMerchantSlotRule).mockResolvedValue(rule);

    render(React.createElement(MerchantCreneauxPage));
    // Wait for data to load — RuleAccordion opens by default when rules=[]
    await waitFor(() => screen.getByRole('heading', { name: 'Créneaux' }));
    // Accordion is already open (no rules yet), so click "Nouvelle règle" directly
    await waitFor(() => screen.getByText('Nouvelle règle'));
    fireEvent.click(screen.getByText('Nouvelle règle'));
    fireEvent.click(screen.getByRole('button', { name: /ajouter la règle/i }));

    await waitFor(() => {
      expect(screen.getByText(/générer les créneaux/i)).toBeInTheDocument();
    });
  });
});
