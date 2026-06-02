import { beforeEach, describe, expect, it } from 'vitest';
import { hasActiveKadhia } from '@/lib/store/hasActiveKadhia';

describe('hasActiveKadhia', () => {
  beforeEach(() => { localStorage.clear(); });

  it('retourne false quand aucune clé en localStorage', () => {
    expect(hasActiveKadhia('store-1')).toBe(false);
  });

  it('retourne true si kadhia:active:{storeId} existe (mode réel)', () => {
    localStorage.setItem('kadhia:active:store-1', 'kadhia-uuid-123');
    expect(hasActiveKadhia('store-1')).toBe(true);
  });

  it('retourne false si kadhia:active: existe pour un autre store', () => {
    localStorage.setItem('kadhia:active:store-2', 'kadhia-uuid-456');
    expect(hasActiveKadhia('store-1')).toBe(false);
  });

  it('retourne true si kadhia:current mock contient des lignes pour ce store', () => {
    localStorage.setItem('kadhia:current', JSON.stringify({
      shopId: 'store-1',
      lines: [{ id: 'l1' }],
    }));
    expect(hasActiveKadhia('store-1')).toBe(true);
  });

  it('retourne false si kadhia:current mock a 0 lignes', () => {
    localStorage.setItem('kadhia:current', JSON.stringify({ shopId: 'store-1', lines: [] }));
    expect(hasActiveKadhia('store-1')).toBe(false);
  });

  it('retourne false si kadhia:current mock appartient à un autre store', () => {
    localStorage.setItem('kadhia:current', JSON.stringify({ shopId: 'store-2', lines: [{ id: 'l1' }] }));
    expect(hasActiveKadhia('store-1')).toBe(false);
  });

  it('retourne false si kadhia:current contient du JSON invalide', () => {
    localStorage.setItem('kadhia:current', '{invalid-json');
    expect(hasActiveKadhia('store-1')).toBe(false);
  });
});
