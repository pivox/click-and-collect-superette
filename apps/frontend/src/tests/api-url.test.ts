import { describe, expect, it } from 'vitest';
import { resolveBrowserApiBaseUrl } from '@/lib/api';

describe('resolveBrowserApiBaseUrl', () => {
  it('uses same-origin API routes when a public frontend is configured with a localhost API URL', () => {
    const apiUrl = resolveBrowserApiBaseUrl('http://localhost:8000', {
      hostname: 'limelike-slouchily-laurie.ngrok-free.dev',
    });

    expect(apiUrl).toBe('');
  });

  it('keeps the local API URL on localhost', () => {
    const apiUrl = resolveBrowserApiBaseUrl('http://localhost:8000', {
      hostname: 'localhost',
    });

    expect(apiUrl).toBe('http://localhost:8000');
  });

  it('keeps an explicit public API URL', () => {
    const apiUrl = resolveBrowserApiBaseUrl('https://api.example.test', {
      hostname: 'kadhia.example.test',
    });

    expect(apiUrl).toBe('https://api.example.test');
  });
});
