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

  async function getRejectedHandler() {
    const { apiClient } = await import('@/lib/api');
    return (
      apiClient.interceptors.response as unknown as {
        handlers: Array<{ rejected?: (error: unknown) => Promise<never> }>;
      }
    ).handlers.at(-1)?.rejected;
  }

  it('does not redirect on 401 from the merchant login page', async () => {
    const rejected = await getRejectedHandler();
    const error = { response: { status: 401 } };
    localStorage.setItem('merchant_token', 'stale-token');
    window.history.pushState({}, '', '/merchant/login');

    await expect(rejected?.(error)).rejects.toBe(error);

    expect(localStorage.getItem('merchant_token')).toBe('stale-token');
    expect(window.location.pathname).toBe('/merchant/login');
  });

  // Regression for #226: a public page (catalog) makes an optional authenticated
  // call. A 401 must NOT clear the session or trigger a redirect when the request
  // opted out via skipAuthRedirect — the caller handles it instead.
  it('does not clear token or redirect on 401 when skipAuthRedirect is set', async () => {
    const rejected = await getRejectedHandler();
    const error = { response: { status: 401 }, config: { skipAuthRedirect: true } };
    localStorage.setItem('jwt_token', 'client-token');
    window.history.pushState({}, '', '/stores/shop-1/catalog');

    await expect(rejected?.(error)).rejects.toBe(error);

    expect(localStorage.getItem('jwt_token')).toBe('client-token');
    expect(window.location.pathname).toBe('/stores/shop-1/catalog');
  });
});
