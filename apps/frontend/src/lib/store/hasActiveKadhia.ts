export function hasActiveKadhia(storeId: string): boolean {
  if (typeof window === 'undefined') return false;
  try {
    if (localStorage.getItem(`kadhia:active:${storeId}`)) return true;
    const raw = localStorage.getItem('kadhia:current');
    if (!raw) return false;
    const mock = JSON.parse(raw) as { shopId?: string; lines?: unknown[] } | null;
    return mock?.shopId === storeId && (mock?.lines?.length ?? 0) > 0;
  } catch {
    return false;
  }
}
