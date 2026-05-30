import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { clientLog } from '@/lib/logger/clientLogger';

const originalFetch = global.fetch;
const originalConsoleError = console.error;
const originalConsoleWarn = console.warn;
const originalConsoleInfo = console.info;

function setProductionMode() {
  process.env.NEXT_PUBLIC_ENVIRONMENT = 'production';
}

function setDevMode() {
  delete process.env.NEXT_PUBLIC_ENVIRONMENT;
}

describe('clientLogger', () => {
  beforeEach(() => {
    vi.spyOn(console, 'error').mockImplementation(() => {});
    vi.spyOn(console, 'warn').mockImplementation(() => {});
    vi.spyOn(console, 'info').mockImplementation(() => {});
    global.fetch = vi.fn().mockResolvedValue({ ok: true });
    setDevMode();
  });

  afterEach(() => {
    console.error = originalConsoleError;
    console.warn = originalConsoleWarn;
    console.info = originalConsoleInfo;
    global.fetch = originalFetch;
    setDevMode();
  });

  describe('dev mode (NEXT_PUBLIC_ENVIRONMENT unset)', () => {
    it('logs error to console.error', () => {
      clientLog('error', 'order_creation_failed', 'Could not create order');

      expect(console.error).toHaveBeenCalledWith(
        '[order_creation_failed]',
        'Could not create order',
        {},
      );
    });

    it('logs warning to console.warn', () => {
      clientLog('warning', 'api_slow', 'Request took too long');

      expect(console.warn).toHaveBeenCalledWith('[api_slow]', 'Request took too long', {});
    });

    it('logs info to console.info', () => {
      clientLog('info', 'store_loaded', 'Store data loaded');

      expect(console.info).toHaveBeenCalledWith('[store_loaded]', 'Store data loaded', {});
    });

    it('does not send HTTP request in dev mode', () => {
      clientLog('error', 'order_creation_failed', 'Could not create order');

      expect(global.fetch).not.toHaveBeenCalled();
    });
  });

  describe('production mode (NEXT_PUBLIC_ENVIRONMENT=production)', () => {
    beforeEach(() => {
      setProductionMode();
    });

    it('sends warning to backend', () => {
      clientLog('warning', 'checkout_slot_unavailable', 'Slot no longer available');

      expect(global.fetch).toHaveBeenCalledOnce();
      const [url, options] = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0] as [
        string,
        RequestInit,
      ];
      expect(url).toContain('/api/client-logs');
      const body = JSON.parse(options.body as string);
      expect(body.level).toBe('warning');
      expect(body.event).toBe('checkout_slot_unavailable');
    });

    it('sends error to backend', () => {
      clientLog('error', 'order_creation_failed', 'POST /api/me/orders returned 500');

      expect(global.fetch).toHaveBeenCalledOnce();
      const [, options] = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0] as [
        string,
        RequestInit,
      ];
      const body = JSON.parse(options.body as string);
      expect(body.level).toBe('error');
    });

    it('does not send debug to backend', () => {
      clientLog('debug', 'component_mounted', 'Catalog mounted');

      expect(global.fetch).not.toHaveBeenCalled();
    });

    it('does not send info to backend', () => {
      clientLog('info', 'store_loaded', 'Store data loaded');

      expect(global.fetch).not.toHaveBeenCalled();
    });

    it('sanitizes forbidden fields from context before sending', () => {
      clientLog('error', 'auth_login_failed', 'Login failed', {
        password: 'secret123',
        token: 'eyJhbGc...',
        route: '/login',
        statusCode: 401,
      });

      const [, options] = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0] as [
        string,
        RequestInit,
      ];
      const body = JSON.parse(options.body as string);
      expect(body.context).not.toHaveProperty('password');
      expect(body.context).not.toHaveProperty('token');
      expect(body.context.route).toBe('/login');
      expect(body.context.statusCode).toBe(401);
    });

    it('uses keepalive on the fetch call', () => {
      clientLog('error', 'order_creation_failed', 'Failed');

      const [, options] = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0] as [
        string,
        RequestInit,
      ];
      expect(options.keepalive).toBe(true);
    });

    it('includes event, message, level and createdAt in the payload', () => {
      clientLog('error', 'merchant_scan_qr_failed', 'QR scan error', { orderId: 'ord-1' });

      const [, options] = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0] as [
        string,
        RequestInit,
      ];
      const body = JSON.parse(options.body as string);
      expect(body.event).toBe('merchant_scan_qr_failed');
      expect(body.message).toBe('QR scan error');
      expect(body.level).toBe('error');
      expect(body.createdAt).toMatch(/^\d{4}-\d{2}-\d{2}T/);
    });

    it('does not throw if fetch rejects', () => {
      global.fetch = vi.fn().mockRejectedValue(new Error('Network error'));

      expect(() => clientLog('error', 'order_creation_failed', 'Failed')).not.toThrow();
    });
  });

  describe('context sanitization', () => {
    beforeEach(() => setProductionMode());

    it('limits context to 20 keys', () => {
      const largeContext = Object.fromEntries(
        Array.from({ length: 30 }, (_, i) => [`key${i}`, `value${i}`]),
      );

      clientLog('error', 'test_event', 'message', largeContext);

      const [, options] = (global.fetch as ReturnType<typeof vi.fn>).mock.calls[0] as [
        string,
        RequestInit,
      ];
      const body = JSON.parse(options.body as string);
      expect(Object.keys(body.context).length).toBeLessThanOrEqual(20);
    });
  });
});
