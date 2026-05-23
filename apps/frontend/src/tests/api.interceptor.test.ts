import { beforeEach, describe, expect, it } from 'vitest';

function installLocalStorageMock() {
  const values = new Map<string, string>();
  const storage = {
    getItem: (key: string) => values.get(key) ?? null,
    setItem: (key: string, value: string) => values.set(key, value),
    removeItem: (key: string) => values.delete(key),
  };

  Object.defineProperty(globalThis, 'localStorage', {
    configurable: true,
    value: storage,
  });
}

describe('apiClient response interceptor', () => {
  beforeEach(() => {
    installLocalStorageMock();
    window.history.pushState({}, '', '/');
  });

  it('does not redirect on 401 from the merchant login page', async () => {
    const { apiClient } = await import('@/lib/api');
    const responseInterceptor = (
      apiClient.interceptors.response as unknown as {
        handlers: Array<{ rejected?: (error: unknown) => Promise<never> }>;
      }
    ).handlers.at(-1);
    const error = { response: { status: 401 } };
    localStorage.setItem('merchant_token', 'stale-token');
    window.history.pushState({}, '', '/merchant/login');

    await expect(responseInterceptor?.rejected?.(error)).rejects.toBe(error);

    expect(localStorage.getItem('merchant_token')).toBe('stale-token');
    expect(window.location.pathname).toBe('/merchant/login');
  });
});
