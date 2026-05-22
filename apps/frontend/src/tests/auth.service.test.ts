import { describe, it, expect } from 'vitest';
import { decodeJwtPayload } from '@/lib/services/auth.service';

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

  it('handles URL-safe base64 characters', () => {
    const token = makeToken({ email: 'test+1@kadhia.tn', roles: ['ROLE_ADMIN'] });
    const result = decodeJwtPayload(token);
    expect(result.email).toBe('test+1@kadhia.tn');
  });

  it('throws on a token with fewer than 3 parts', () => {
    expect(() => decodeJwtPayload('notavalidtoken')).toThrow();
  });
});
