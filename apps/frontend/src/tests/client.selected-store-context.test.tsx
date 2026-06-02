import { render, screen, act, waitFor } from '@testing-library/react';
import React from 'react';
import { beforeEach, describe, expect, it } from 'vitest';
import { SelectedStoreProvider, useSelectedStore } from '@/lib/store/SelectedStoreContext';

function Consumer() {
  const { selectedStore, selectStore, clearStore } = useSelectedStore();
  return (
    <div>
      <span data-testid="name">{selectedStore?.name ?? 'none'}</span>
      <button onClick={() => selectStore({ id: 's1', name: 'Aziza Montplaisir' })}>select</button>
      <button onClick={() => clearStore()}>clear</button>
    </div>
  );
}

describe('SelectedStoreContext', () => {
  beforeEach(() => { localStorage.clear(); });

  it('selectedStore est null sans localStorage', async () => {
    render(<SelectedStoreProvider><Consumer /></SelectedStoreProvider>);
    await waitFor(() => expect(screen.getByTestId('name').textContent).toBe('none'));
  });

  it('restaure le store depuis localStorage au montage', async () => {
    localStorage.setItem('selected_store', JSON.stringify({ id: 's1', name: 'Aziza Montplaisir' }));
    render(<SelectedStoreProvider><Consumer /></SelectedStoreProvider>);
    await waitFor(() => expect(screen.getByTestId('name').textContent).toBe('Aziza Montplaisir'));
  });

  it('selectStore met à jour le state et localStorage', async () => {
    render(<SelectedStoreProvider><Consumer /></SelectedStoreProvider>);
    await waitFor(() => screen.getByRole('button', { name: 'select' }));
    act(() => screen.getByRole('button', { name: 'select' }).click());
    await waitFor(() => expect(screen.getByTestId('name').textContent).toBe('Aziza Montplaisir'));
    expect(localStorage.getItem('selected_store')).toContain('Aziza Montplaisir');
  });

  it('clearStore remet selectedStore à null et vide localStorage', async () => {
    localStorage.setItem('selected_store', JSON.stringify({ id: 's1', name: 'Aziza Montplaisir' }));
    render(<SelectedStoreProvider><Consumer /></SelectedStoreProvider>);
    await waitFor(() => expect(screen.getByTestId('name').textContent).toBe('Aziza Montplaisir'));
    act(() => screen.getByRole('button', { name: 'clear' }).click());
    await waitFor(() => expect(screen.getByTestId('name').textContent).toBe('none'));
    expect(localStorage.getItem('selected_store')).toBeNull();
  });

  it('useSelectedStore throw hors du provider', () => {
    const err = console.error;
    console.error = () => {};
    expect(() => render(<Consumer />)).toThrow();
    console.error = err;
  });
});
