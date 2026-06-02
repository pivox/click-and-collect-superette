export function hasActiveKadhia(storeId: string): boolean {
  if (typeof window === 'undefined') return false;
  if (localStorage.getItem(`kadhia:active:${storeId}`)) return true;
  try {
    const mock = JSON.parse(localStorage.getItem('kadhia:current') ?? 'null') as {
      shopId?: string;
      lines?: unknown[];
    } | null;
    return mock?.shopId === storeId && (mock?.lines?.length ?? 0) > 0;
  } catch {
    return false;
  }
}
