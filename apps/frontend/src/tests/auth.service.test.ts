import { describe, it, expect, vi, beforeEach } from 'vitest';
import { decodeJwtPayload, adminLogin } from '@/lib/services/auth.service';
import { apiClient } from '@/lib/api';

vi.mock('@/lib/api', () => ({
  apiClient: {
    post: vi.fn(),
  },
}));

function makeToken(payload: Record<string, unknown>): string {
  const encoded = btoa(JSON.stringify(payload));
  return `header.${encoded}.sig`;
}

describe('decodeJwtPayload', () => {
  it('decodes email and roles from a valid JWT payload', () => {
    const token = makeToken({ email: 'admin@kadhia.tn', roles: ['ROLE_ADMIN'] });
    const result = decodeJwtPayload(token);
    expect(result.email).toBe('admin@kadhia.tn');
    expect(result.roles).toEqual(['ROLE_ADMIN']);
  });

  it('normalises URL-safe base64 characters (- and _)', () => {
    const standardPayload = btoa(JSON.stringify({ email: 'a@b.com', roles: ['ROLE_ADMIN'] }));
    const urlSafePayload = standardPayload.replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    const token = `header.${urlSafePayload}.sig`;
    const result = decodeJwtPayload(token);
    expect(result.email).toBe('a@b.com');
  });

  it('throws on a token with fewer than 3 parts', () => {
    expect(() => decodeJwtPayload('notavalidtoken')).toThrow('Invalid JWT format');
  });

  it('throws on a token with a non-JSON payload', () => {
    expect(() => decodeJwtPayload('header.bm90IGpzb24.sig')).toThrow('Invalid JWT payload');
  });
});

describe('adminLogin', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('returns AdminUser when token contains ROLE_ADMIN', async () => {
    const token = makeToken({ email: 'admin@kadhia.tn', name: 'Admin', roles: ['ROLE_ADMIN'] });
    vi.mocked(apiClient.post).mockResolvedValue({ data: { token } });

    const user = await adminLogin('admin@kadhia.tn', 'secret');
    expect(user.email).toBe('admin@kadhia.tn');
    expect(user.name).toBe('Admin');
    expect(user.token).toBe(token);
  });

  it('throws when token does not contain ROLE_ADMIN', async () => {
    const token = makeToken({ email: 'client@kadhia.tn', roles: ['ROLE_CUSTOMER'] });
    vi.mocked(apiClient.post).mockResolvedValue({ data: { token } });

    await expect(adminLogin('client@kadhia.tn', 'secret')).rejects.toThrow(
      "Accès réservé à l'administration"
    );
  });

  it('propagates network errors', async () => {
    vi.mocked(apiClient.post).mockRejectedValue(new Error('Network Error'));
    await expect(adminLogin('admin@kadhia.tn', 'wrong')).rejects.toThrow('Network Error');
  });
});
