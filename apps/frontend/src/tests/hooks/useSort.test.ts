import { renderHook, act } from '@testing-library/react';
import { useSort } from '@/lib/hooks/useSort';

const items = [
  { id: '1', name: 'Banane', count: 10 },
  { id: '2', name: 'Abricot', count: 5 },
  { id: '3', name: 'Cerise', count: 20 },
];

describe('useSort', () => {
  it('retourne les items dans l\'ordre original sans tri', () => {
    const { result } = renderHook(() => useSort(items));
    expect(result.current.sorted.map((i) => i.id)).toEqual(['1', '2', '3']);
    expect(result.current.sortKey).toBeNull();
  });

  it('trie en ordre ascendant au premier clic', () => {
    const { result } = renderHook(() => useSort(items));
    act(() => result.current.toggleSort('name'));
    expect(result.current.sorted.map((i) => i.id)).toEqual(['2', '1', '3']);
    expect(result.current.sortDir).toBe('asc');
  });

  it('bascule en ordre descendant au deuxième clic sur la même colonne', () => {
    const { result } = renderHook(() => useSort(items));
    act(() => result.current.toggleSort('name'));
    act(() => result.current.toggleSort('name'));
    expect(result.current.sorted.map((i) => i.id)).toEqual(['3', '1', '2']);
    expect(result.current.sortDir).toBe('desc');
  });

  it('repart en asc et change la clé quand on clique sur une autre colonne', () => {
    const { result } = renderHook(() => useSort(items));
    act(() => result.current.toggleSort('name'));
    act(() => result.current.toggleSort('name')); // desc
    act(() => result.current.toggleSort('count')); // nouvelle clé → asc
    expect(result.current.sortDir).toBe('asc');
    expect(result.current.sortKey).toBe('count');
    expect(result.current.sorted.map((i) => i.id)).toEqual(['2', '1', '3']); // 5, 10, 20
  });

  it('place les valeurs null en fin de liste', () => {
    const withNull = [
      { id: '1', name: 'Banane' as string | null },
      { id: '2', name: null },
      { id: '3', name: 'Abricot' as string | null },
    ];
    const { result } = renderHook(() => useSort(withNull));
    act(() => result.current.toggleSort('name'));
    expect(result.current.sorted.map((i) => i.id)).toEqual(['3', '1', '2']);
  });
});
